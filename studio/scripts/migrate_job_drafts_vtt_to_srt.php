<?php

/**
 * One-time migration: convert the job pipeline's drafts from WebVTT to SubRip.
 *
 * Covers every JobManager root, because there are three and they are easy to
 * miss: data/jobs (transcription), data/shorten-jobs, and
 * data/caption-translation/<vimeoId>. Each holds current/draft.srt plus
 * current/draft_{lang}.srt. Nothing else needs updating: the sibling
 * translation.json records per-language status only, never filenames.
 *
 * Scope matters here — an earlier version of this script globbed only
 * caption-translation and only draft_*.vtt, which stranded a finished
 * transcription job in data/jobs: its files stayed WebVTT while the renamed
 * JobManager looked for SubRip, so hasDraft() returned false and the pipeline
 * reported "transcribing" forever.
 *
 * Must ship in the same deploy as the JobManager rename — after that rename the
 * code looks for draft_{lang}.srt, and any leftover .vtt is orphaned job state.
 *
 * Same safety posture as migrate_captions_vtt_to_srt.php: dry run by default,
 * every conversion verified cue-by-cue in memory before anything is written,
 * and the whole run aborts before touching disk if any file would lose a cue.
 * Idempotent, so an interrupted run can just be repeated.
 *
 * Usage:
 *   php studio/scripts/migrate_translation_drafts_vtt_to_srt.php                  # dry run
 *   php studio/scripts/migrate_translation_drafts_vtt_to_srt.php --apply          # convert
 *   php studio/scripts/migrate_translation_drafts_vtt_to_srt.php --data-dir=/path # run against a copy
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

$dataDir = realpath($dataDirArg ?? (__DIR__ . '/../../data'));
if ($dataDir === false || !is_dir($dataDir)) {
    fwrite(STDERR, "data dir not found.\n");
    exit(1);
}

/* Both naming patterns, since draft_*.vtt does not match a bare draft.vtt. */
$patterns = [
    '/jobs/current/draft.vtt',
    '/jobs/current/draft_*.vtt',
    '/shorten-jobs/current/draft.vtt',
    '/shorten-jobs/current/draft_*.vtt',
    '/caption-translation/*/current/draft.vtt',
    '/caption-translation/*/current/draft_*.vtt',
];

$drafts = [];
foreach ($patterns as $pattern) {
    foreach (glob($dataDir . $pattern) ?: [] as $path) {
        $drafts[$path] = true;
    }
}
$drafts = array_keys($drafts);
sort($drafts);

/* Anything left behind would be invisible to the code after the rename. */
$strays = [];
foreach (glob($dataDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
    if (basename($dir) === 'captions') {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'vtt' && !isset(array_flip($drafts)[$file->getPathname()])) {
            $strays[] = $file->getPathname();
        }
    }
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
$errors = [];

foreach ($drafts as $oldPath) {
    $newPath = preg_replace('/\.vtt$/i', '.srt', $oldPath);
    $label = str_replace($dataDir . '/caption-translation/', '', $oldPath);

    if (is_file($newPath)) {
        $errors[] = "$label: destination already exists, refusing to overwrite";
        continue;
    }

    $result = convertVerified($oldPath);
    if ($result['ok'] !== true) {
        $errors[] = "$label: {$result['reason']}";
        continue;
    }

    $planned[] = ['oldPath' => $oldPath, 'newPath' => $newPath, 'srt' => $result['srt'], 'label' => $label];
}

$existingSrt = 0;
foreach ($patterns as $pattern) {
    $existingSrt += count(glob($dataDir . str_replace('.vtt', '.srt', $pattern)) ?: []);
}

echo ($apply ? 'Applying' : 'Planned (dry run — pass --apply to execute)')
    . ' ' . count($planned) . " conversion(s)"
    . ($existingSrt > 0 ? ", $existingSrt already SubRip" : '') . ".\n";

if ($strays !== []) {
    printf("\n%d WebVTT file(s) under data/ outside the known draft patterns:\n", count($strays));
    foreach ($strays as $stray) {
        echo '  - ' . str_replace($dataDir . '/', '', $stray) . "\n";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "\n" . count($errors) . " problem(s) found — nothing has been changed:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - $e\n");
    }
    exit(1);
}

if ($planned === [] || !$apply) {
    if ($planned === []) {
        echo "Nothing to migrate.\n";
    }
    exit(0);
}

/*
 * Write every .srt first, then remove the originals. An abort in between leaves
 * both files present, which the destination-exists guard above turns into a
 * clean error on re-run rather than silent overwriting.
 */
$written = [];
foreach ($planned as $item) {
    if (file_put_contents($item['newPath'], $item['srt']) === false) {
        fwrite(STDERR, "ERROR: could not write {$item['newPath']}\n");
        exit(1);
    }
    $written[] = $item;
}

$removeFailures = [];
foreach ($written as $item) {
    if (!unlink($item['oldPath'])) {
        $removeFailures[] = $item['label'];
    }
}

echo "Done. " . count($written) . " draft(s) converted.\n";

if ($removeFailures !== []) {
    fwrite(STDERR, "\n" . count($removeFailures) . " old .vtt draft(s) could not be deleted:\n");
    foreach ($removeFailures as $label) {
        fwrite(STDERR, "  - $label\n");
    }
    exit(1);
}

exit(0);
