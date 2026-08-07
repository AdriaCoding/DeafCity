<?php

/**
 * Read-only pre-flight for the VTT→SRT caption migration.
 *
 * Converts every data/captions/*.vtt in memory and asserts the round trip is
 * lossless at the cue level: same cue count, same timings (to the millisecond),
 * same text. Writes nothing, touches nothing — run this before the migration
 * script to prove the corpus survives conversion.
 *
 * Also reports what conversion would *drop*: WebVTT cue settings (positioning,
 * alignment) have no SubRip equivalent, and non-sequential cue ids get
 * renumbered. Neither is an error, but both are worth seeing before committing.
 *
 * Usage:
 *   php studio/scripts/verify_caption_conversion.php
 *   php studio/scripts/verify_caption_conversion.php --verbose
 *
 * Exit code 0 when every file round-trips cleanly, 1 otherwise.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Studio\SrtParser;
use Studio\VttParser;
use Studio\VttToSrtConverter;

const TIMING_TOLERANCE_SECONDS = 0.0005;

$verbose = in_array('--verbose', $argv, true);

$dataDir = realpath(__DIR__ . '/../../data');
if ($dataDir === false || !is_dir($dataDir . '/captions')) {
    fwrite(STDERR, "data/captions not found.\n");
    exit(1);
}

$captionsDir = $dataDir . '/captions';
$files = glob($captionsDir . '/*.vtt') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDERR, "No .vtt files found in $captionsDir — nothing to verify.\n");
    exit(1);
}

/** Caption filenames the catalog actually references, to separate live files from orphans. */
$referenced = [];
$catalogPath = $dataDir . '/catalog.json';
if (is_file($catalogPath)) {
    $catalog = json_decode((string) file_get_contents($catalogPath), true);
    foreach ($catalog['videos'] ?? [] as $video) {
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['file'] ?? '') !== '') {
                $referenced[$caption['file']] = true;
            }
        }
    }
}

$vttParser = new VttParser();
$srtParser = new SrtParser();
$converter = new VttToSrtConverter();

$failures = [];
$emptyFiles = [];
$withCueSettings = [];
$withNonSequentialIds = [];
$orphans = [];
$totalCues = 0;

foreach ($files as $path) {
    $name = basename($path);

    if (!isset($referenced[$name])) {
        $orphans[] = $name;
    }

    try {
        $source = $vttParser->parse($path)['cues'];
        $converted = $srtParser->parseString($converter->convert($path))['cues'];
    } catch (\Throwable $e) {
        $failures[] = "$name: conversion threw — " . $e->getMessage();
        continue;
    }

    if ($source === []) {
        $emptyFiles[] = $name;
        continue;
    }

    $totalCues += count($source);

    foreach ($source as $i => $cue) {
        if (($cue['opaque'] ?? '') !== '') {
            $withCueSettings[$name] = ($withCueSettings[$name] ?? 0) + 1;
        }
        if (($cue['id'] ?? '') !== '' && $cue['id'] !== (string) ($i + 1)) {
            $withNonSequentialIds[$name] = true;
        }
    }

    if (count($converted) !== count($source)) {
        $failures[] = sprintf(
            '%s: cue count %d → %d',
            $name,
            count($source),
            count($converted)
        );
        continue;
    }

    foreach ($source as $i => $cue) {
        $actual = $converted[$i];

        if (abs($cue['start'] - $actual['start']) > TIMING_TOLERANCE_SECONDS) {
            $failures[] = sprintf('%s cue %d: start %.6f → %.6f', $name, $i + 1, $cue['start'], $actual['start']);
        }
        if (abs($cue['end'] - $actual['end']) > TIMING_TOLERANCE_SECONDS) {
            $failures[] = sprintf('%s cue %d: end %.6f → %.6f', $name, $i + 1, $cue['end'], $actual['end']);
        }
        if ($cue['text'] !== $actual['text']) {
            $failures[] = sprintf(
                "%s cue %d: text differs\n    before: %s\n    after:  %s",
                $name,
                $i + 1,
                json_encode($cue['text'], JSON_UNESCAPED_UNICODE),
                json_encode($actual['text'], JSON_UNESCAPED_UNICODE)
            );
        }
    }

    if ($verbose) {
        printf("  ok  %-60s %d cues\n", $name, count($source));
    }
}

echo "\nVTT→SRT conversion pre-flight\n";
echo str_repeat('-', 60) . "\n";
printf("Files scanned:            %d\n", count($files));
printf("Cues verified:            %d\n", $totalCues);
printf("Catalog-referenced:       %d\n", count($files) - count($orphans));
printf("Not in catalog (orphans): %d\n", count($orphans));

if ($emptyFiles !== []) {
    printf("\nFiles parsing to ZERO cues (%d) — inspect before migrating:\n", count($emptyFiles));
    foreach ($emptyFiles as $name) {
        echo "  - $name\n";
    }
}

if ($withCueSettings !== []) {
    printf(
        "\nFiles with WebVTT cue settings that SubRip cannot carry (%d):\n",
        count($withCueSettings)
    );
    foreach ($withCueSettings as $name => $count) {
        echo "  - $name ($count cues)\n";
    }
}

if ($withNonSequentialIds !== []) {
    printf(
        "\nFiles whose cue ids are not already 1..N and will be renumbered (%d):\n",
        count($withNonSequentialIds)
    );
    foreach (array_keys($withNonSequentialIds) as $name) {
        echo "  - $name\n";
    }
}

if ($orphans !== [] && $verbose) {
    printf("\nOrphaned caption files (%d):\n", count($orphans));
    foreach ($orphans as $name) {
        echo "  - $name\n";
    }
}

if ($failures !== []) {
    printf("\nFAILED — %d problem(s):\n", count($failures));
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "\nOK — every file round-trips losslessly.\n";
exit(0);
