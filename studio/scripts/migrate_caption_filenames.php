<?php

/**
 * One-time migration: rename every caption file referenced in catalog.json's
 * captions[].file from the old `{vimeo_id}.{lang}.vtt` convention to the new
 * `{title, resolution suffix stripped}_{LANGCODE}.vtt` convention (see
 * Studio\CaptionFilename), and update catalog.json to match.
 *
 * Files under data/captions/ that no catalog entry currently references are
 * left untouched — this migration is catalog-driven, not directory-driven.
 *
 * Usage:
 *   php studio/scripts/migrate_caption_filenames.php            # dry run
 *   php studio/scripts/migrate_caption_filenames.php --apply     # renames files + rewrites catalog.json
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CaptionMigrationPlanner;

$apply = in_array('--apply', $argv, true);

$dataDir     = realpath(__DIR__ . '/../../data');
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

$planner = new CaptionMigrationPlanner();
$renames = $planner->plan($catalog['videos']);

if ($renames === []) {
    echo "Nothing to migrate — every catalog caption file already uses the new convention.\n";
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}

echo ($apply ? 'Applying' : 'Planned (dry run — pass --apply to execute)') . " " . count($renames) . " rename(s):\n";

$errors = false;
foreach ($renames as $rename) {
    $oldPath = $captionsDir . '/' . $rename['oldFile'];
    $newPath = $captionsDir . '/' . $rename['newFile'];

    echo "  [{$rename['vimeoId']}/{$rename['lang']}] {$rename['oldFile']} -> {$rename['newFile']}\n";

    if (!$apply) {
        continue;
    }

    if (!is_file($oldPath)) {
        fwrite(STDERR, "    ERROR: source file missing on disk: $oldPath\n");
        $errors = true;
        continue;
    }
    if (is_file($newPath)) {
        fwrite(STDERR, "    ERROR: destination file already exists, refusing to overwrite: $newPath\n");
        $errors = true;
        continue;
    }
    if (!rename($oldPath, $newPath)) {
        fwrite(STDERR, "    ERROR: rename failed for $oldPath -> $newPath\n");
        $errors = true;
        continue;
    }
}

if (!$apply) {
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
}

if ($errors) {
    fwrite(STDERR, "Aborting catalog.json update because at least one file rename failed.\n");
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}

$catalog['videos'] = $planner->applyRenames($catalog['videos'], $renames);

ftruncate($fp, 0);
fseek($fp, 0);
fwrite($fp, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo "Done. catalog.json updated and " . count($renames) . " file(s) renamed on disk.\n";
