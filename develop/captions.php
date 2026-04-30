<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// ── Input ────────────────────────────────────────────────────────────────────

$videoId = preg_replace('/[^A-Za-z0-9_\-]/', '', isset($_GET['v'])    ? $_GET['v']    : '');
$lang    = preg_replace('/[^A-Za-z\-]/',     '', isset($_GET['lang']) ? $_GET['lang'] : 'en');

if (!$videoId) {
    http_response_code(400);
    echo json_encode(array('error' => 'Missing video ID'));
    exit;
}

// ── File cache ───────────────────────────────────────────────────────────────

$cacheDir      = __DIR__ . '/cache';
$cacheLifetime = 6 * 3600;
$cacheFile     = $cacheDir . '/captions_' . $videoId . '_' . $lang . '.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
    echo file_get_contents($cacheFile);
    exit;
}

// ── Guards ───────────────────────────────────────────────────────────────────

if (!defined('YT_OAUTH_REFRESH_TOKEN') || YT_OAUTH_REFRESH_TOKEN === '') {
    http_response_code(503);
    echo json_encode(array('error' => 'OAuth refresh token not configured'));
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function ytGet($url, $accessToken) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $accessToken),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('code' => $code, 'body' => $body);
}

function timestampToMs($ts) {
    $parts = explode(':', $ts);
    if (count($parts) === 3) {
        return (int)(((int)$parts[0] * 3600 + (int)$parts[1] * 60 + (float)$parts[2]) * 1000);
    }
    return (int)(((int)$parts[0] * 60 + (float)$parts[1]) * 1000);
}

function parseVtt($vtt) {
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
            'start' => timestampToMs($m[1]),
            'end'   => timestampToMs($m[2]),
            'text'  => $text,
        );
    }

    return $cues;
}

// ── Access token ─────────────────────────────────────────────────────────────

$tokenCacheFile = $cacheDir . '/access_token.json';
$accessToken    = null;

if (file_exists($tokenCacheFile)) {
    $cached = json_decode(file_get_contents($tokenCacheFile), true);
    if ($cached && isset($cached['expires_at']) && $cached['expires_at'] > time() + 60) {
        $accessToken = $cached['token'];
    }
}

if (!$accessToken) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array(
            'client_id'     => YT_OAUTH_CLIENT_ID,
            'client_secret' => YT_OAUTH_CLIENT_SECRET,
            'refresh_token' => YT_OAUTH_REFRESH_TOKEN,
            'grant_type'    => 'refresh_token',
        )),
        CURLOPT_RETURNTRANSFER => true,
    ));
    $body = curl_exec($ch);
    curl_close($ch);
    $tokenData = json_decode($body, true);

    if (empty($tokenData['access_token'])) {
        http_response_code(502);
        echo json_encode(array('error' => 'Failed to obtain access token', 'detail' => $tokenData));
        exit;
    }

    $accessToken = $tokenData['access_token'];
    file_put_contents($tokenCacheFile, json_encode(array(
        'token'      => $accessToken,
        'expires_at' => time() + (int)(isset($tokenData['expires_in']) ? $tokenData['expires_in'] : 3600),
    )));
}

// ── captions.list ─────────────────────────────────────────────────────────────

$listUrl = 'https://www.googleapis.com/youtube/v3/captions?part=snippet&videoId=' . rawurlencode($videoId);
$listRes = ytGet($listUrl, $accessToken);

if ($listRes['code'] !== 200) {
    http_response_code(502);
    echo json_encode(array('error' => 'captions.list failed', 'http' => $listRes['code'], 'detail' => json_decode($listRes['body'])));
    exit;
}

$tracks = json_decode($listRes['body'], true);
$tracks = isset($tracks['items']) ? $tracks['items'] : array();

if (empty($tracks)) {
    $result = json_encode(array());
    file_put_contents($cacheFile, $result);
    echo $result;
    exit;
}

// Find the best track: exact language match → asr fallback → first available
$trackId    = null;
$fallbackId = null;

foreach ($tracks as $track) {
    $tLang = isset($track['snippet']['language'])  ? $track['snippet']['language']  : '';
    $tKind = isset($track['snippet']['trackKind']) ? $track['snippet']['trackKind'] : '';

    if ($tLang === $lang) {
        $trackId = $track['id'];
        break;
    }
    if ($fallbackId === null || $tKind === 'asr') {
        $fallbackId = $track['id'];
    }
}

$trackId = $trackId ? $trackId : $fallbackId;

// ── captions.download ─────────────────────────────────────────────────────────

$dlUrl = 'https://www.googleapis.com/youtube/v3/captions/' . rawurlencode($trackId) . '?tfmt=vtt';
$dlRes = ytGet($dlUrl, $accessToken);

if ($dlRes['code'] !== 200) {
    http_response_code(502);
    echo json_encode(array('error' => 'captions.download failed', 'http' => $dlRes['code']));
    exit;
}

// ── Parse and cache ───────────────────────────────────────────────────────────

$cues   = parseVtt($dlRes['body']);
$result = json_encode($cues);

file_put_contents($cacheFile, $result);
echo $result;
