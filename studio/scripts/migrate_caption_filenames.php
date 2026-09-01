<?php

// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

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

/*
 * Lock a dedicated catalog.json.lock, never catalog.json itself: Studio's
 * CatalogEditor commits by rename(), so a lock held on the catalog inode is
 * released the moment that inode is replaced. Both writers must agree on the
 * same lock file or this script is no longer serialized against live Studio
 * writes. See CatalogEditor::withLockedCatalog().
 */
$fp = fopen($catalogPath . '.lock', 'c');
if ($fp === false) {
    fwrite(STDERR, "Could not open the catalog lock file.\n");
    exit(1);
}

flock($fp, LOCK_EX);

$raw = file_get_contents($catalogPath);
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

// Temp file + rename, so a reader never observes a half-written catalog.
$tmpPath = $catalogPath . '.tmp-' . bin2hex(random_bytes(6));
if (file_put_contents($tmpPath, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n") === false) {
    fwrite(STDERR, "Could not write the temporary catalog file.\n");
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}
@chmod($tmpPath, is_file($catalogPath) ? (fileperms($catalogPath) & 0777) : 0664);
if (!rename($tmpPath, $catalogPath)) {
    @unlink($tmpPath);
    fwrite(STDERR, "Could not commit catalog.json.\n");
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}
flock($fp, LOCK_UN);
fclose($fp);

echo "Done. catalog.json updated and " . count($renames) . " file(s) renamed on disk.\n";
