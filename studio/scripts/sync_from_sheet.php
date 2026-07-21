<?php

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

use Studio\CatalogEditor;
use Studio\CatalogSheetSync;
use Studio\GoogleSheetsClient;
use Studio\SheetCatalogParser;
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

$sync = new CatalogSheetSync(
    new GoogleSheetsClient($serviceAccountPath, SPREADSHEET_ID),
    new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN),
    new CatalogEditor($catalogPath),
    new SheetCatalogParser(),
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
