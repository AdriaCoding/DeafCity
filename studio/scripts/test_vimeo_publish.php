<?php

/**
 * Manual integration test: upload caption files to Vimeo text tracks.
 *
 * Uploads every .vtt file in data/captions/ to a sample video (delete-then-upload,
 * matching PublicationHandler). Does not print credentials or full API bodies.
 *
 * Usage: php studio/scripts/test_vimeo_publish.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\StudioConfig;
use Studio\VimeoClient;

$vimeoId = '639494119';
$captionsDir = realpath(__DIR__ . '/../../data/captions');
$studioConfigPath = __DIR__ . '/../../data/studio-config.json';

if ($captionsDir === false || !is_dir($captionsDir)) {
    fwrite(STDERR, "Captions directory not found.\n");
    exit(1);
}

$labelMap = [];
foreach ((new StudioConfig($studioConfigPath))->getSubtitleLanguages() as $lang) {
    $labelMap[$lang['id']] = $lang['label'];
}

/** @var list<array{path: string, file: string, vimeoLang: string, label: string}> */
$captions = [];
foreach (glob($captionsDir . '/*.vtt') ?: [] as $path) {
    $file = basename($path);
    if (!preg_match('/^[^.]+\.([^.]+)\.vtt$/', $file, $m)) {
        fwrite(STDERR, "Skipping unrecognised filename: $file\n");
        continue;
    }
    $langTag = $m[1];
    $vimeoLang = strtolower(explode('-', $langTag)[0]);
    $label = $langTag === 'es-MX'
        ? 'Español (México)'
        : ($labelMap[$vimeoLang] ?? $langTag);
    $captions[] = [
        'path' => $path,
        'file' => $file,
        'vimeoLang' => $vimeoLang,
        'label' => $label,
    ];
}

if ($captions === []) {
    fwrite(STDERR, "No .vtt files found in data/captions/.\n");
    exit(1);
}

usort($captions, fn(array $a, array $b) => strcmp($a['file'], $b['file']));

$client = new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);

echo "Sample video: $vimeoId\n";
echo 'Caption files: ' . count($captions) . "\n\n";

echo 'Deleting existing text tracks … ';
try {
    $tracks = $client->getTextTracks($vimeoId);
    foreach ($tracks as $track) {
        if ($track['uri'] !== '') {
            $client->deleteTextTrack($track['uri']);
        }
    }
    echo count($tracks) . " removed\n\n";
} catch (\Throwable $e) {
    echo "failed (" . $e->getMessage() . ")\n\n";
    exit(1);
}

$ok = 0;
$failed = [];

foreach ($captions as $caption) {
    $line = sprintf(
        'Uploading %s (%s / %s) … ',
        $caption['file'],
        $caption['label'],
        $caption['vimeoLang'],
    );
    echo $line;
    try {
        $client->uploadAndActivateTextTrack(
            $vimeoId,
            $caption['path'],
            $caption['vimeoLang'],
            $caption['label'],
        );
        echo "OK\n";
        $ok++;
    } catch (\Throwable $e) {
        echo "FAILED\n";
        echo '   ' . $e->getMessage() . "\n";
        $failed[] = $caption['file'];
    }
}

echo "\n";
$total = count($captions);
if ($failed === []) {
    echo "All $total caption track(s) uploaded.\n";
    exit(0);
}

echo "$ok/$total succeeded; failed: " . implode(', ', $failed) . "\n";
exit(1);
