<?php

// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

/**
 * Push catalog state to Vimeo: title, tags, and caption files for every video
 * in data/catalog.json. Re-fetches thumbnail_url from Vimeo for every video,
 * so Vimeo-side thumbnail changes are picked up on each sync.
 *
 * Usage: php studio/scripts/sync_to_vimeo.php [--status-file /path/to/status.json]
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\VimeoClient;
use Studio\VimeoPushSync;

$statusFile = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--status-file' && isset($argv[$i + 1])) {
        $statusFile = $argv[$i + 1];
        break;
    }
}

function writeStatus(string $status, int $synced, int $total, ?string $statusFile, int $skipped = 0, ?string $error = null): void
{
    if ($statusFile === null) {
        return;
    }
    file_put_contents($statusFile, json_encode([
        'status' => $status,
        'synced' => $synced,
        'total' => $total,
        'skipped' => $skipped,
        'error' => $error,
    ]));
}

/*
 * One Vimeo push at a time. The lock is held for the whole run (released when
 * the process exits), not just around the launch, so a second sync started
 * from the Studio while this one is still working is refused instead of
 * racing it through CatalogEditor and the Vimeo API.
 */
$syncLock = \Studio\ProcessLock::acquire(dirname(__DIR__, 2) . '/data/sync_to_vimeo.lock');
if ($syncLock === null) {
    fwrite(STDERR, "Another Vimeo sync is already running. Aborting.\n");
    writeStatus('error', 0, 0, $statusFile, 0, 'Ja hi ha una sincronització en curs.');
    exit(1);
}

$catalogPath = realpath(__DIR__ . '/../../data/catalog.json');
$captionsDir = realpath(__DIR__ . '/../../data/captions');
$configPath = realpath(__DIR__ . '/../../data/studio-config.json');

if ($catalogPath === false || !is_file($catalogPath)) {
    fwrite(STDERR, "catalog.json not found.\n");
    exit(1);
}

if ($captionsDir === false || !is_dir($captionsDir)) {
    fwrite(STDERR, "Captions directory not found.\n");
    exit(1);
}

if ($configPath === false || !is_file($configPath)) {
    fwrite(STDERR, "studio-config.json not found.\n");
    exit(1);
}

$client = new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);
$catalogEditor = new CatalogEditor($catalogPath);
$studioConfig = new StudioConfig($configPath);
$sync = new VimeoPushSync($client, $studioConfig, $catalogEditor, $captionsDir);

$videos = $catalogEditor->getAllVideos();
$total = count($videos);
$synced = 0;
$skipped = 0;

writeStatus('running', 0, $total, $statusFile);

foreach ($videos as $idx => $entry) {
    $vimeoId = (string) ($entry['vimeo_id'] ?? '');
    echo '[' . ($idx + 1) . "/$total] $vimeoId … ";

    $result = $sync->syncVideo($entry);

    if (isset($result['abort'])) {
        echo "ABORT: {$result['abort']}\n";
        writeStatus('error', $synced, $total, $statusFile, $skipped, $result['abort']);
        exit(1);
    }

    if (($result['skipped'] ?? false) === true) {
        echo "SKIP (not found on Vimeo)\n";
        $skipped++;
        writeStatus('running', $synced, $total, $statusFile, $skipped);
        continue;
    }

    $synced++;
    writeStatus('running', $synced, $total, $statusFile, $skipped);

    $captionCount = count($entry['captions'] ?? []);
    $tagCount = count($entry['tags'] ?? []);
    $thumbNote = ($result['thumbnailUpdated'] ?? false) ? ', thumbnail updated' : '';
    echo "title=\"" . ($entry['title'] ?? '') . "\", $tagCount tag(s), $captionCount caption(s)$thumbNote\n";
}

writeStatus('done', $synced, $total, $statusFile, $skipped);
echo "\nDone. $synced/$total video(s) pushed, $skipped skipped.\n";
