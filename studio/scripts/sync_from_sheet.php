<?php

// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

/**
 * Sync Catalog from Toni’s Google Sheet.
 *
 * Usage:
 *   php studio/scripts/sync_from_sheet.php [--replace] [--dry-run]
 *
 * Default mode upserts only (same as Studio “Sincronitza des del full”).
 * --replace removes Catalog Videos whose vimeo_id is absent from the Sheet, then upserts.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

/*
 * One sheet sync run at a time, for the whole duration of the process. Two
 * concurrent runs would spend API budget twice and interleave their catalog
 * writes.
 */
$__runLock = \Studio\ProcessLock::acquire(dirname(__DIR__, 2) . '/data/sync_from_sheet.lock');
if ($__runLock === null) {
    fwrite(STDERR, "Another sheet sync run is already in progress. Aborting.\n");
    exit(1);
}

use Studio\CatalogEditor;
use Studio\CatalogSheetSync;
use Studio\GoogleSheetsClient;
use Studio\SheetCatalogParser;
use Studio\StudioConfig;
use Studio\VimeoClient;

$replace = false;
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--replace') {
        $replace = true;
        continue;
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    fwrite(STDERR, "Unknown argument: $arg\n");
    exit(1);
}

if (!defined('SPREADSHEET_ID') || SPREADSHEET_ID === '') {
    fwrite(STDERR, "SPREADSHEET_ID is not configured.\n");
    exit(1);
}

$serviceAccountPath = realpath(__DIR__ . '/../../config/google-sheets-service-account.json');
if ($serviceAccountPath === false || !is_readable($serviceAccountPath)) {
    fwrite(STDERR, "Google Sheets service-account JSON not readable.\n");
    exit(1);
}

$catalogPath = realpath(__DIR__ . '/../../data/catalog.json');
if ($catalogPath === false) {
    fwrite(STDERR, "catalog.json not found.\n");
    exit(1);
}

$studioConfigPath = realpath(__DIR__ . '/../../data/studio-config.json');
if ($studioConfigPath === false) {
    fwrite(STDERR, "studio-config.json not found.\n");
    exit(1);
}

$sync = new CatalogSheetSync(
    new GoogleSheetsClient($serviceAccountPath, SPREADSHEET_ID),
    new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN),
    new CatalogEditor($catalogPath),
    new SheetCatalogParser((new StudioConfig($studioConfigPath))->getTypologies()),
);

$result = $sync->run(['replace' => $replace, 'dryRun' => $dryRun]);

if ($result->error !== null) {
    fwrite(STDERR, 'Error: ' . $result->error . "\n");
    exit(1);
}

$mode = $replace ? 'replace' : 'upsert';
if ($dryRun) {
    $mode .= ', dry-run';
}

echo "Sheet sync ($mode)\n";
echo "  added:   {$result->added}\n";
echo "  updated: {$result->updated}\n";
echo "  removed: {$result->removed}\n";
echo "  skipped: {$result->skipped}\n";
echo "  warnings: " . count($result->warnings) . "\n";
foreach ($result->warnings as $warning) {
    echo "  - $warning\n";
}

if ($dryRun) {
    echo "(dry-run: catalog not written)\n";
}

exit(0);
