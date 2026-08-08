<?php

/**
 * One-time migration: convert every catalog caption from WebVTT to SubRip.
 *
 * For each caption referenced by catalog.json, converts the on-disk .vtt to
 * .srt, writes the new file, removes the old one, and rewrites the catalog's
 * captions[].file entry to match.
 *
 * Safety posture follows migrate_caption_filenames.php: dry run by default,
 * flock()-protected read-modify-write of catalog.json, and catalog.json is
 * only rewritten if every single file converted successfully — a partial disk
 * state never gets recorded as if it were complete.
 *
 * Conversion is verified cue-by-cue in memory before anything is written, so a
 * file that would lose or alter a cue aborts the run rather than being saved.
 *
 * Idempotent: captions already ending in .srt are skipped, so an interrupted
 * run can simply be repeated.
 *
 * Usage:
 *   php studio/scripts/migrate_captions_vtt_to_srt.php                    # dry run
 *   php studio/scripts/migrate_captions_vtt_to_srt.php --apply            # convert + rewrite catalog
 *   php studio/scripts/migrate_captions_vtt_to_srt.php --data-dir=/path   # run against a copy
 *
 * --data-dir exists so the whole --apply path can be rehearsed end to end on a
 * throwaway copy of data/ before it is pointed at the live one.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Studio\SrtParser;
use Studio\VttParser;
use Studio\VttToSrtConverter;

const TIMING_TOLERANCE_SECONDS = 0.0005;

$apply = in_array('--apply', $argv, true);

$dataDirArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--data-dir=')) {
        $dataDirArg = substr($arg, strlen('--data-dir='));
    }
}

$dataDir     = realpath($dataDirArg ?? (__DIR__ . '/../../data'));
$catalogPath = $dataDir !== false ? $dataDir . '/catalog.json' : '';
$captionsDir = $dataDir !== false ? $dataDir . '/captions' : '';

if ($dataDir === false || !is_file($catalogPath) || !is_dir($captionsDir)) {
    fwrite(STDERR, "catalog.json or captions dir not found.\n");
    exit(1);
}

$fp = fopen($catalogPath, 'c+');
if ($fp === false) {
    fwrite(STDERR, "Could not open catalog.json for writing.\n");
    exit(1);
}

flock($fp, LOCK_EX);

$raw = stream_get_contents($fp);
$catalog = json_decode($raw ?: '', true);
if (!is_array($catalog) || !isset($catalog['videos']) || !is_array($catalog['videos'])) {
    fwrite(STDERR, "Invalid catalog JSON.\n");
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}

/** @return array{ok: true, srt: string}|array{ok: false, reason: string} */
function convertVerified(string $path): array
{
    try {
        $source = (new VttParser())->parse($path)['cues'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'reason' => 'cannot parse source: ' . $e->getMessage()];
    }

    if ($source === []) {
        return ['ok' => false, 'reason' => 'source parses to zero cues'];
    }

    try {
        $srt = (new VttToSrtConverter())->convert($path);
        $converted = (new SrtParser())->parseString($srt)['cues'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'reason' => 'conversion failed: ' . $e->getMessage()];
    }

    if (count($converted) !== count($source)) {
        return ['ok' => false, 'reason' => sprintf('cue count %d -> %d', count($source), count($converted))];
    }

    foreach ($source as $i => $cue) {
        $actual = $converted[$i];
        if (abs($cue['start'] - $actual['start']) > TIMING_TOLERANCE_SECONDS
            || abs($cue['end'] - $actual['end']) > TIMING_TOLERANCE_SECONDS) {
            return ['ok' => false, 'reason' => sprintf('cue %d timing drifted', $i + 1)];
        }
        if ($cue['text'] !== $actual['text']) {
            return ['ok' => false, 'reason' => sprintf('cue %d text differs', $i + 1)];
        }
    }

    return ['ok' => true, 'srt' => $srt];
}

$planned = [];
$skipped = 0;
$errors = [];

foreach ($catalog['videos'] as $vi => $video) {
    foreach ($video['captions'] ?? [] as $ci => $caption) {
        $file = (string) ($caption['file'] ?? '');
        if ($file === '') {
            continue;
        }

        if (str_ends_with(strtolower($file), '.srt')) {
            $skipped++;
            continue;
        }

        $vimeoId = (string) ($video['vimeo_id'] ?? '?');
        $lang    = (string) ($caption['lang'] ?? '?');
        $oldPath = $captionsDir . '/' . $file;
        $newFile = preg_replace('/\.vtt$/i', '', $file) . '.srt';
        $newPath = $captionsDir . '/' . $newFile;

        if (!is_file($oldPath)) {
            $errors[] = "[$vimeoId/$lang] source missing on disk: $file";
            continue;
        }
        if (is_file($newPath)) {
            $errors[] = "[$vimeoId/$lang] destination already exists, refusing to overwrite: $newFile";
            continue;
        }

        $result = convertVerified($oldPath);
        if ($result['ok'] !== true) {
            $errors[] = "[$vimeoId/$lang] $file: {$result['reason']}";
            continue;
        }

        $planned[] = [
            'videoIndex'   => $vi,
            'captionIndex' => $ci,
            'vimeoId'      => $vimeoId,
            'lang'         => $lang,
            'oldPath'      => $oldPath,
            'newPath'      => $newPath,
            'newFile'      => $newFile,
            'srt'          => $result['srt'],
            'label'        => "$file -> $newFile",
        ];
    }
}

echo ($apply ? 'Applying' : 'Planned (dry run — pass --apply to execute)')
    . ' ' . count($planned) . " conversion(s)"
    . ($skipped > 0 ? ", $skipped already SubRip" : '') . ":\n";

foreach ($planned as $item) {
    echo "  [{$item['vimeoId']}/{$item['lang']}] {$item['label']}\n";
}

if ($errors !== []) {
    fwrite(STDERR, "\n" . count($errors) . " problem(s) found — nothing has been changed:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - $e\n");
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}

if ($planned === []) {
    echo "Nothing to migrate.\n";
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}

if (!$apply) {
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}

/*
 * Every conversion is verified by this point, so writes should not fail. If one
 * does, stop immediately and leave catalog.json untouched: the already-written
 * .srt files are additive (the .vtt originals are only removed afterwards), so
 * re-running picks up where this left off.
 */
$written = [];
foreach ($planned as $item) {
    if (file_put_contents($item['newPath'], $item['srt']) === false) {
        fwrite(STDERR, "ERROR: could not write {$item['newPath']}\n");
        fwrite(STDERR, "Aborting before catalog.json is touched. Re-run to continue.\n");
        flock($fp, LOCK_UN);
        fclose($fp);
        exit(1);
    }
    $written[] = $item;
}

foreach ($written as $item) {
    $catalog['videos'][$item['videoIndex']]['captions'][$item['captionIndex']]['file'] = $item['newFile'];
}

ftruncate($fp, 0);
fseek($fp, 0);
fwrite($fp, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// Only once the catalog points at the .srt files do the originals come off disk.
$removeFailures = [];
foreach ($written as $item) {
    if (!unlink($item['oldPath'])) {
        $removeFailures[] = $item['oldPath'];
    }
}

echo "Done. " . count($written) . " caption(s) converted and catalog.json updated.\n";

if ($removeFailures !== []) {
    fwrite(STDERR, "\nCatalog is correct, but " . count($removeFailures) . " old .vtt file(s) could not be deleted:\n");
    foreach ($removeFailures as $path) {
        fwrite(STDERR, "  - $path\n");
    }
    exit(1);
}

exit(0);
