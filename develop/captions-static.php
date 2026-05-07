<?php
/**
 * Serves WebVTT from this directory as JSON cues for outer captions (no OAuth).
 * ?f=<basename> — only safe basenames ending in .vtt are allowed.
 */
header('Content-Type: application/json; charset=utf-8');

$basename = isset($_GET['f']) ? basename((string) $_GET['f']) : '';
if ($basename === '' || !preg_match('/^[a-zA-Z0-9_\-\.]+\.vtt$/', $basename)) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid or missing filename'));
    exit;
}

$path = __DIR__ . '/' . $basename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    echo json_encode(array('error' => 'VTT file not found'));
    exit;
}

$vtt = file_get_contents($path);
if ($vtt === false) {
    http_response_code(500);
    echo json_encode(array('error' => 'Failed to read file'));
    exit;
}

function timestampToMsStatic($ts) {
    $parts = explode(':', $ts);
    if (count($parts) === 3) {
        return (int)(((int) $parts[0] * 3600 + (int) $parts[1] * 60 + (float) $parts[2]) * 1000);
    }
    return (int)(((int) $parts[0] * 60 + (float) $parts[1]) * 1000);
}

function parseVttStatic($vtt) {
    $cues   = array();
    $blocks = preg_split('/\n[ \t]*\n/', trim($vtt));

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '' || substr($block, 0, 6) === 'WEBVTT') {
            continue;
        }

        $lines    = explode("\n", $block);
        $tsLine   = null;
        $txtLines = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($tsLine === null && strpos($line, ' --> ') !== false) {
                $tsLine = $line;
            } elseif ($tsLine !== null && $line !== '') {
                $txtLines[] = $line;
            }
        }

        if (!$tsLine || empty($txtLines)) {
            continue;
        }

        if (!preg_match('/^([\d:\.]+)\s+-->\s+([\d:\.]+)/', $tsLine, $m)) {
            continue;
        }

        $text = strip_tags(implode(' ', $txtLines));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            continue;
        }

        $cues[] = array(
            'start' => timestampToMsStatic($m[1]),
            'end'   => timestampToMsStatic($m[2]),
            'text'  => $text,
        );
    }

    return $cues;
}

$cues = parseVttStatic($vtt);
echo json_encode($cues);
