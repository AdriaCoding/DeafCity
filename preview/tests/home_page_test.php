<?php
// Run: php preview/tests/home_page_test.php

function assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function assert_not_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

/**
 * Extract the first Vimeo video ID found in the rendered HTML iframe src.
 * Returns '' if not found.
 */
function extract_first_video_id($html)
{
    if (preg_match('~player\.vimeo\.com/video/(\d+)~', $html, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Extract the playlist JSON from the vpc-config script block in rendered HTML.
 * Returns null on failure.
 */
function extract_playlist_from_html($html)
{
    if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $html, $m)) {
        return null;
    }
    $decoded = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // The JSON is encoded with JSON_HEX_* flags — decode it back
    $json = json_decode($decoded, true);
    return is_array($json) ? $json : null;
}

$homePage = dirname(dirname(__FILE__)) . '/index.php';
if (!is_file($homePage)) {
    fwrite(STDERR, "FAIL: home page missing at {$homePage}\n");
    exit(1);
}

ob_start();
include $homePage;
$html = ob_get_clean();

assert_contains('vimeo_caption_player', $html, 'player component');
assert_not_contains('>Player</span>', $html, 'no redundant player button on home');
assert_not_contains('is-active', $html, 'no active nav button on home');
assert_contains('href="/preview/about"', $html, 'about nav link');
assert_contains('preview-site-nav--chrome', $html, 'nav in player chrome');
assert_contains('vpc-site-nav-wrap', $html, 'nav below player controls');
assert_not_contains('preview-site-nav--overlay', $html, 'no top overlay nav');
assert_not_contains('proto-bar', $html, 'no prototype switcher');
assert_not_contains('variant=', $html, 'no variant query param logic');
assert_not_contains('vA-about', $html, 'no variant A about block');
assert_not_contains('vB-layout', $html, 'no variant B about block');
assert_not_contains('vC-about', $html, 'no variant C about block');
assert_not_contains('trio-wrap', $html, 'no inline trio video');
assert_contains('overflow: hidden', $html, 'non-scrollable body');

$transportPos = strpos($html, 'vpc-transport');
$navPos = strpos($html, 'vpc-site-nav-wrap');
if ($transportPos === false || $navPos === false || $navPos < $transportPos) {
    fwrite(STDERR, "FAIL: site nav should appear after transport controls\n");
    exit(1);
}
echo "PASS: nav below transport\n";

// ── Issue #1: paused random poster & no-autoplay ──────────────────────────────

// AC: No autoplay on load — iframe src must have autoplay=0 (or no autoplay=1).
if (preg_match('~player\.vimeo\.com/video/\d+[^"]*autoplay=([^&"]+)~', $html, $autoM)) {
    if ($autoM[1] !== '0') {
        fwrite(STDERR, "FAIL: no autoplay on load — autoplay param is '{$autoM[1]}', expected '0'\n");
        exit(1);
    }
}
assert_not_contains('autoplay=1', $html, 'no autoplay=1 in iframe src');
echo "PASS: no autoplay on load\n";

// AC: No mute/unmute control visible in HTML.
assert_not_contains('vpc-mute-btn', $html, 'no mute button element');
assert_not_contains('vpc-unmute-btn', $html, 'no unmute button element');
assert_not_contains('aria-label="Mute"', $html, 'no mute aria-label');
assert_not_contains('aria-label="Unmute"', $html, 'no unmute aria-label');
echo "PASS: no mute/unmute control\n";

// AC: Server-chosen video renders — player iframe src contains a Vimeo video id.
$firstVideoId = extract_first_video_id($html);
if ($firstVideoId === '') {
    fwrite(STDERR, "FAIL: no Vimeo video id found in iframe src\n");
    exit(1);
}
echo "PASS: player renders with a server-chosen video (id={$firstVideoId})\n";

// AC: serverShuffled flag is in the config JSON so JS trusts server order.
$cfg = extract_playlist_from_html($html);
if ($cfg === null) {
    fwrite(STDERR, "FAIL: could not parse vpc-config JSON from rendered HTML\n");
    exit(1);
}
if (empty($cfg['serverShuffled'])) {
    fwrite(STDERR, "FAIL: serverShuffled flag missing or false in vpc-config JSON\n");
    exit(1);
}
echo "PASS: serverShuffled=true in vpc-config JSON\n";

// AC: playlist index 0 in config (server placed poster at head).
if (!isset($cfg['playlistIndex']) || $cfg['playlistIndex'] !== 0) {
    fwrite(STDERR, "FAIL: playlistIndex in config should be 0, got: " . json_encode($cfg['playlistIndex'] ?? null) . "\n");
    exit(1);
}
echo "PASS: playlistIndex=0 in vpc-config (server poster is queue head)\n";

// AC: iframe src video id matches playlist[0] videoId in the config.
$playlistItems = isset($cfg['playlist']) && is_array($cfg['playlist']) ? $cfg['playlist'] : [];
if (count($playlistItems) === 0) {
    fwrite(STDERR, "FAIL: playlist in vpc-config is empty\n");
    exit(1);
}
$headVideoId = isset($playlistItems[0]['videoId']) ? (string)$playlistItems[0]['videoId'] : '';
if ($headVideoId === '') {
    fwrite(STDERR, "FAIL: playlist[0].videoId is empty in vpc-config\n");
    exit(1);
}
if ($firstVideoId !== $headVideoId) {
    fwrite(STDERR, "FAIL: iframe video id ({$firstVideoId}) does not match playlist[0].videoId ({$headVideoId}) — poster/head mismatch\n");
    exit(1);
}
echo "PASS: iframe poster matches playlist[0] (paused poster = queue head, no silent swap)\n";

// AC: Over many reloads every video appears first with roughly equal frequency.
// Verify randomness: shuffle the full catalog playlist 30 times in-process and
// check that at least 2 distinct video IDs appear at index 0.
// (catalog functions were already loaded by including index.php above)
$catalogJsonPathForTest = dirname(dirname(dirname(__FILE__))) . '/data/catalog.json';
if (!is_file($catalogJsonPathForTest)) {
    // Fallback: try main public_html data dir (when running from worktree)
    $catalogJsonPathForTest = '/srv/www/deaf.city/public_html/data/catalog.json';
}
if (is_file($catalogJsonPathForTest)) {
    $catalogForTest = vpc_load_videos_catalog($catalogJsonPathForTest);
    if ($catalogForTest) {
        $basePlaylist = vpc_vimeo_playlist_all_from_catalog($catalogForTest);
        $firstIds = [];
        for ($i = 0; $i < 30; $i++) {
            $shuffled = vpc_shuffle_playlist($basePlaylist);
            if (!empty($shuffled[0]['video_id'])) {
                $firstIds[$shuffled[0]['video_id']] = true;
            }
        }
        if (count($firstIds) < 2) {
            fwrite(STDERR, "FAIL: randomness check — only " . count($firstIds) . " distinct video(s) appeared as poster[0] over 30 shuffles (expected ≥2)\n");
            exit(1);
        }
        echo "PASS: randomness — " . count($firstIds) . " distinct first-video IDs seen over 30 shuffles\n";
    } else {
        echo "SKIP: randomness check skipped — catalog could not be loaded\n";
    }
} else {
    echo "SKIP: randomness check skipped — catalog.json not found at test runtime\n";
}

echo "All tests passed.\n";
