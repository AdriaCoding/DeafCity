<?php

/**
 * Push catalog state to Vimeo: title, tags, and caption files for every video
 * in data/catalog.json. Pulls thumbnail_url from Vimeo only when missing.
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

function writeStatus(string $status, int $synced, int $total, ?string $statusFile): void
{
    if ($statusFile === null) {
        return;
    }
    file_put_contents($statusFile, json_encode([
        'status' => $status,
        'synced' => $synced,
        'total' => $total,
    ]));
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
    if (($result['skipped'] ?? false) === true) {
        echo "SKIP (not found on Vimeo)\n";
        $skipped++;
        continue;
    }

    $synced++;
    writeStatus('running', $synced, $total, $statusFile);

    $captionCount = count($entry['captions'] ?? []);
    $tagCount = count($entry['tags'] ?? []);
    $thumbNote = ($result['thumbnailBackfilled'] ?? false) ? ', thumbnail backfilled' : '';
    echo "title=\"" . ($entry['title'] ?? '') . "\", $tagCount tag(s), $captionCount caption(s)$thumbNote\n";
}

writeStatus('done', $synced, $total, $statusFile);
echo "\nDone. $synced/$total video(s) pushed, $skipped skipped.\n";
