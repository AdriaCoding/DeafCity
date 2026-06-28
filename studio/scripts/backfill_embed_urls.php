<?php

/**
 * Backfill catalog embed_url from Vimeo player_embed_url (required for unlisted videos).
 *
 * Usage:
 *   php studio/scripts/backfill_embed_urls.php [--dry-run] [--visible-only]
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\VimeoClient;

$dryRun = in_array('--dry-run', $argv, true);
$visibleOnly = in_array('--visible-only', $argv, true);

$catalogPath = realpath(__DIR__ . '/../../data/catalog.json');
if ($catalogPath === false) {
    fwrite(STDERR, "catalog.json not found.\n");
    exit(1);
}

$catalog = json_decode(file_get_contents($catalogPath), true);
if (!is_array($catalog) || !isset($catalog['videos']) || !is_array($catalog['videos'])) {
    fwrite(STDERR, "Invalid catalog JSON.\n");
    exit(1);
}

$client = new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);
$updated = 0;
$skipped = 0;
$failed = 0;

foreach ($catalog['videos'] as &$video) {
    if (!is_array($video)) {
        continue;
    }

    if ($visibleOnly && (($video['invisible'] ?? false) === true)) {
        $skipped++;
        continue;
    }

    $vimeoId = trim((string) ($video['vimeo_id'] ?? ''));
    if ($vimeoId === '') {
        $skipped++;
        continue;
    }

    $existing = trim((string) ($video['embed_url'] ?? ''));
    if ($existing !== '') {
        $skipped++;
        continue;
    }

    $embedUrl = $client->getPlayerEmbedUrl($vimeoId);
    if ($embedUrl === null) {
        fwrite(STDERR, "FAILED $vimeoId: no player_embed_url\n");
        $failed++;
        continue;
    }

    echo ($dryRun ? 'DRY-RUN ' : '') . "$vimeoId -> $embedUrl\n";
    if (!$dryRun) {
        $video['embed_url'] = $embedUrl;
    }
    $updated++;
}
unset($video);

if (!$dryRun && $updated > 0) {
    file_put_contents(
        $catalogPath,
        json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
    );
}

echo "\nUpdated: $updated\nSkipped: $skipped\nFailed: $failed\n";
exit($failed > 0 ? 1 : 0);
