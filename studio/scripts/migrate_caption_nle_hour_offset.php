<?php

/**
 * One-time migration: strip the DaVinci/Premiere 01:00:00 programme clock
 * from Caption files under data/ so cue times match video t=0.
 *
 * Only files whose first cue is at or after 01:00:00 *and* whose whole
 * content fits in less than one hour are rewritten. A Caption that genuinely
 * lasts an hour or more aborts the run — that case cannot be distinguished
 * from the NLE offset by a script.
 *
 * Idempotent: already-zero-based files are skipped.
 *
 * Usage:
 *   php studio/scripts/migrate_caption_nle_hour_offset.php                    # dry run
 *   php studio/scripts/migrate_caption_nle_hour_offset.php --apply            # rewrite
 *   php studio/scripts/migrate_caption_nle_hour_offset.php --data-dir=/path
 *   php studio/scripts/migrate_caption_nle_hour_offset.php --verbose
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CaptionTimecodeAligner;
use Studio\SrtParser;

$apply = in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true);

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

$parser = new SrtParser();
$aligner = new CaptionTimecodeAligner();

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dataDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (strtolower($fileInfo->getExtension()) === 'srt') {
        $files[] = $fileInfo->getPathname();
    }
}
sort($files);

if ($files === []) {
    echo "No .srt files under {$dataDir}.\n";
    exit(0);
}

$planned = [];
$skipped = 0;
$errors = [];
$longFiles = [];
$maxSpan = 0.0;
$maxSpanPath = '';

foreach ($files as $path) {
    try {
        $cues = $parser->parse($path)['cues'];
    } catch (\Throwable $e) {
        $errors[] = $path . ': ' . $e->getMessage();
        continue;
    }

    $minStart = min(array_column($cues, 'start'));
    $maxEnd = max(array_column($cues, 'end'));
    $span = $maxEnd - $minStart;
    if ($span > $maxSpan) {
        $maxSpan = $span;
        $maxSpanPath = $path;
    }
    if ($span >= 3600.0) {
        $longFiles[] = sprintf(
            '%s (span %.3fs, first cue %.3fs)',
            $path,
            $span,
            $minStart
        );
        continue;
    }

    $offset = $aligner->offsetSeconds($cues);
    if ($offset <= 0.0) {
        $skipped++;
        continue;
    }

    $aligned = $aligner->align($cues);
    $planned[] = [
        'path' => $path,
        'offset' => $offset,
        'cueCount' => count($cues),
        'srt' => $parser->write($aligned),
        'texts' => array_column($cues, 'text'),
    ];
}

echo sprintf(
    "Scanned %d .srt file(s). Longest content span: %.3fs (%s).\n",
    count($files),
    $maxSpan,
    $maxSpanPath !== '' ? str_replace($dataDir . '/', '', $maxSpanPath) : 'n/a'
);

if ($longFiles !== []) {
    fwrite(STDERR, "\n" . count($longFiles) . " file(s) last a full hour or more — aborting, nothing changed:\n");
    foreach ($longFiles as $line) {
        fwrite(STDERR, "  - $line\n");
    }
    exit(1);
}

if ($errors !== []) {
    fwrite(STDERR, "\n" . count($errors) . " file(s) failed to parse — aborting, nothing changed:\n");
    foreach ($errors as $e) {
        fwrite(STDERR, "  - $e\n");
    }
    exit(1);
}

echo ($apply ? 'Applying' : 'Planned (dry run — pass --apply to execute)')
    . ' ' . count($planned) . ' rewrite(s)'
    . ($skipped > 0 ? ", {$skipped} already starting at 00:00:00" : '')
    . ":\n";

$byDir = [];
foreach ($planned as $item) {
    $rel = str_replace($dataDir . '/', '', $item['path']);
    $top = explode('/', $rel)[0];
    $byDir[$top] = ($byDir[$top] ?? 0) + 1;
    if ($verbose) {
        echo sprintf("  -%gs  %s  (%d cues)\n", $item['offset'], $rel, $item['cueCount']);
    }
}

foreach ($byDir as $dir => $count) {
    echo "  {$dir}/: {$count}\n";
}
if (!$verbose && $planned !== []) {
    echo "  (pass --verbose to list every file)\n";
}

if ($planned === []) {
    echo "Nothing to migrate.\n";
    exit(0);
}

if (!$apply) {
    exit(0);
}

$written = 0;
foreach ($planned as $item) {
    $tmp = $item['path'] . '.tmp';
    if (file_put_contents($tmp, $item['srt']) === false) {
        fwrite(STDERR, "ERROR: could not write {$tmp}\n");
        exit(1);
    }
    if (!rename($tmp, $item['path'])) {
        @unlink($tmp);
        fwrite(STDERR, "ERROR: could not replace {$item['path']}\n");
        exit(1);
    }

    try {
        $check = $parser->parse($item['path'])['cues'];
    } catch (\Throwable $e) {
        fwrite(STDERR, "ERROR: rewritten file does not parse: {$item['path']}: {$e->getMessage()}\n");
        exit(1);
    }
    if (count($check) !== $item['cueCount'] || array_column($check, 'text') !== $item['texts']) {
        fwrite(STDERR, "ERROR: cue text/count changed after rewrite: {$item['path']}\n");
        exit(1);
    }
    if ($aligner->offsetSeconds($check) !== 0.0) {
        fwrite(STDERR, "ERROR: rewrite did not clear the hour offset: {$item['path']}\n");
        exit(1);
    }

    $written++;
}

echo "Done. {$written} caption file(s) rewritten.\n";
exit(0);
