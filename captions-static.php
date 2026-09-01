<?php
/**
 * Serves a caption file from data/captions/ as JSON cues for outer captions (no OAuth).
 * ?f=<basename> — only safe basenames ending in .srt or .vtt are allowed.
 *
 * Both extensions are accepted: SubRip is the primary stored format, WebVTT is
 * still served for any file not yet migrated. Parsing lives in
 * lib/caption_cues.php so it can be tested without going through HTTP.
 */
require __DIR__ . '/lib/caption_cues.php';

header('Content-Type: application/json; charset=utf-8');

$basename = isset($_GET['f']) ? basename((string) $_GET['f']) : '';
if ($basename === '' || !preg_match('/^[a-zA-Z0-9_\-\. ]+\.(srt|vtt)$/', $basename)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid or missing filename'));
    exit;
}

$captionsDir = __DIR__ . '/data/captions';
$path = $captionsDir . '/' . $basename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    echo json_encode(array('error' => 'Caption file not found'));
    exit;
}

$content = file_get_contents($path);
if ($content === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'Failed to read file'));
    exit;
}

echo json_encode(vpc_parse_caption_cues($content));
