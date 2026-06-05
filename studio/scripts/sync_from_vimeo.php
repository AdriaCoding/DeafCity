<?php

/**
 * Sync catalog from Vimeo: fetches title, tags, and text tracks for every video
 * in data/catalog.json, downloads VTT files to data/captions/, and overwrites
 * the catalog entry with the current Vimeo state.
 *
 * Usage: php studio/scripts/sync_from_vimeo.php [--status-file /path/to/status.json]
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\VimeoClient;
use Studio\VimeoNotFoundException;

// Parse --status-file argument
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

if ($catalogPath === false || !is_file($catalogPath)) {
    fwrite(STDERR, "catalog.json not found.\n");
    exit(1);
}

if ($captionsDir === false || !is_dir($captionsDir)) {
    fwrite(STDERR, "Captions directory not found.\n");
    exit(1);
}

$raw = file_get_contents($catalogPath);
$catalog = json_decode($raw ?: '', true);

if (!is_array($catalog) || !isset($catalog['videos'])) {
    fwrite(STDERR, "Invalid catalog.json.\n");
    exit(1);
}

$client = new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);

$total = count($catalog['videos']);
$synced = 0;
$skipped = 0;

writeStatus('running', 0, $total, $statusFile);

foreach ($catalog['videos'] as $idx => &$entry) {
    $vimeoId = (string) ($entry['vimeo_id'] ?? '');
    echo '[' . ($idx + 1) . "/$total] $vimeoId … ";

    // Title
    try {
        $title = $client->getVideo($vimeoId);
    } catch (VimeoNotFoundException $e) {
        echo "SKIP (not found on Vimeo)\n";
        $skipped++;
        continue;
    } catch (\Throwable $e) {
        echo "SKIP (" . $e->getMessage() . ")\n";
        $skipped++;
        continue;
    }

    // Tags
    try {
        $tags = $client->getTagNames($vimeoId);
    } catch (\Throwable) {
        $tags = [];
    }

    // Thumbnail
    $thumbnailUrl = null;
    try {
        $thumbnailUrl = $client->getThumbnailUrl($vimeoId);
    } catch (\Throwable) {
        // non-fatal
    }

    // Text tracks
    $captions = [];
    try {
        $tracks = $client->getTextTracksDetailed($vimeoId);
        foreach ($tracks as $track) {
            $lang = $track['language'];
            $filename = "$vimeoId.$lang.vtt";
            $destPath = $captionsDir . '/' . $filename;

            $content = @file_get_contents($track['link'], false, stream_context_create([
                'http' => ['timeout' => 30, 'header' => "User-Agent: deaf.city-sync/1.0\r\n"],
                'https' => ['timeout' => 30, 'header' => "User-Agent: deaf.city-sync/1.0\r\n"],
            ]));

            if ($content === false) {
                echo "\n  WARNING: could not download VTT for lang $lang";
                continue;
            }

            file_put_contents($destPath, $content);
            $captions[] = [
                'lang' => $lang,
                'label' => $track['name'],
                'file' => $filename,
            ];
        }
    } catch (\Throwable $e) {
        echo "\n  WARNING: could not fetch text tracks (" . $e->getMessage() . ")";
    }

    $entry['title'] = $title;
    $entry['tags'] = $tags;
    $entry['captions'] = $captions;
    if ($thumbnailUrl !== null) {
        $entry['thumbnail_url'] = $thumbnailUrl;
    }

    $synced++;
    writeStatus('running', $synced, $total, $statusFile);

    $captionCount = count($captions);
    $tagCount = count($tags);
    echo "title=\"$title\", $tagCount tag(s), $captionCount caption(s)\n";
}
unset($entry);

// Write catalog atomically with exclusive lock
$fp = fopen($catalogPath, 'c+');
if ($fp === false) {
    fwrite(STDERR, "Cannot open catalog.json for writing.\n");
    writeStatus('error', $synced, $total, $statusFile);
    exit(1);
}

flock($fp, LOCK_EX);
ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
flock($fp, LOCK_UN);
fclose($fp);

writeStatus('done', $synced, $total, $statusFile);
echo "\nDone. $synced/$total video(s) synced, $skipped skipped.\n";
