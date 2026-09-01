<?php

// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

/**
 * Bulk-import Vimeo videos uploaded on a given date into catalog.json.
 *
 * Usage:
 *   php studio/scripts/bulk_import_vimeo.php [--date=2026-06-16] [--dry-run]
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CatalogEditor;
use Studio\VideoTitleMetadata;
use Studio\VimeoClient;
use Vimeo\Vimeo;

$date = '2026-06-16';
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (str_starts_with($arg, '--date=')) {
        $date = substr($arg, 7);
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Invalid --date value.\n");
    exit(1);
}

$catalogPath = realpath(__DIR__ . '/../../data/catalog.json');
if ($catalogPath === false) {
    fwrite(STDERR, "catalog.json not found.\n");
    exit(1);
}

$client = new Vimeo(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);
$vimeoClient = new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);
$catalogEditor = new CatalogEditor($catalogPath);

$fields = implode(',', [
    'uri', 'name', 'created_time', 'tags.name',
]);

/** @var list<array<string, mixed>> $candidates */
$candidates = [];
$page = 1;

while (true) {
    $response = $client->request('/me/videos', [
        'page' => $page,
        'per_page' => 50,
        'sort' => 'date',
        'direction' => 'desc',
        'fields' => $fields,
    ], 'GET');

    $status = $response['status'] ?? 0;
    if ($status < 200 || $status >= 300) {
        fwrite(STDERR, "Vimeo API error (status $status).\n");
        exit(1);
    }

    $data = $response['body']['data'] ?? [];
    if ($data === []) {
        break;
    }

    foreach ($data as $video) {
        $created = (string) ($video['created_time'] ?? '');
        if (!str_starts_with($created, $date)) {
            continue;
        }

        preg_match('#/videos/(\d+)#', (string) ($video['uri'] ?? ''), $m);
        $vimeoId = $m[1] ?? '';
        if ($vimeoId === '') {
            continue;
        }

        $tags = array_values(array_filter(array_map(
            static fn(array $tag): string => trim((string) ($tag['name'] ?? '')),
            $video['tags'] ?? [],
        )));

        $candidates[] = [
            'vimeo_id' => $vimeoId,
            'title' => (string) ($video['name'] ?? ''),
            'tags' => $tags,
            'created_time' => $created,
        ];
    }

    $total = (int) ($response['body']['total'] ?? 0);
    if ($page * 50 >= $total) {
        break;
    }
    $page++;
}

if ($candidates === []) {
    fwrite(STDERR, "No Vimeo videos found for date $date.\n");
    exit(1);
}

usort($candidates, static fn(array $a, array $b): int => strcmp($a['title'], $b['title']));

$added = 0;
$skipped = 0;
$errors = [];

foreach ($candidates as $candidate) {
    $vimeoId = $candidate['vimeo_id'];
    $title = $candidate['title'];

    if ($catalogEditor->findVideoByVimeoId($vimeoId) !== null) {
        echo "SKIP already in catalog: $vimeoId $title\n";
        $skipped++;
        continue;
    }

    try {
        $resolved = VideoTitleMetadata::resolveEditionAndSignLanguage($title);
        $participant = VideoTitleMetadata::extractParticipant($title);
    } catch (\InvalidArgumentException $e) {
        $errors[] = "$vimeoId $title: " . $e->getMessage();
        continue;
    }

    if ($participant === null || $participant === '') {
        $errors[] = "$vimeoId $title: could not extract participant";
        continue;
    }

    $thumbnailUrl = null;
    try {
        $thumbnailUrl = $vimeoClient->getThumbnailUrl($vimeoId);
    } catch (\Throwable) {
        // non-fatal
    }

    $embedUrl = null;
    try {
        $embedUrl = $vimeoClient->getPlayerEmbedUrl($vimeoId);
    } catch (\Throwable) {
        // non-fatal
    }

    echo sprintf(
        "%s %s | %s / %s | participant=%s | tags=%d\n",
        $dryRun ? 'DRY-RUN' : 'ADD',
        $vimeoId,
        $resolved['sign_language'],
        $resolved['edition'],
        $participant,
        count($candidate['tags']),
    );

    if (!$dryRun) {
        $catalogEditor->addVideo(
            $vimeoId,
            $title,
            $resolved['sign_language'],
            $resolved['edition'],
            $thumbnailUrl,
            $candidate['tags'],
            null,
            $participant,
            $embedUrl,
        );
    }

    $added++;
}

echo "\nDate: $date\n";
echo 'Candidates: ' . count($candidates) . "\n";
echo ($dryRun ? 'Would add: ' : 'Added: ') . $added . "\n";
echo "Skipped: $skipped\n";

if ($errors !== []) {
    fwrite(STDERR, "\nErrors:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  $error\n");
    }
    exit(1);
}

exit(0);
