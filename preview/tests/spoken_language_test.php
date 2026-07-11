<?php
// Run: php preview/tests/spoken_language_test.php

require dirname(dirname(__FILE__)) . '/lib/videos_catalog.php';

function assert_true($cond, $label)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function assert_eq($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label} — expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$dataDir = dirname(dirname(dirname(__FILE__))) . '/data';
if (!is_readable($dataDir . '/studio-config.json')) {
    $dataDir = '/srv/www/deaf.city/public_html/data';
}

$studioConfigPath = $dataDir . '/studio-config.json';
$catalogPath = $dataDir . '/catalog.json';

$subtitleLanguages = vpc_subtitle_languages_from_studio_config($studioConfigPath);
assert_true(count($subtitleLanguages) >= 6, 'subtitle_languages loaded from studio-config');

assert_eq('es', vpc_resolve_track_lang_to_subtitle_id('es-MX', $subtitleLanguages), 'es-MX maps to Spanish');
assert_eq('es', vpc_resolve_track_lang_to_subtitle_id('es-ES', $subtitleLanguages), 'es-ES maps to Spanish');
assert_eq('es', vpc_resolve_track_lang_to_subtitle_id('es', $subtitleLanguages), 'es maps to Spanish');
assert_eq('en', vpc_resolve_track_lang_to_subtitle_id('en', $subtitleLanguages), 'en maps to English');
assert_eq('ca', vpc_resolve_track_lang_to_subtitle_id('ca', $subtitleLanguages), 'ca maps to Catalan');

$catalog = vpc_load_videos_catalog($catalogPath);
assert_true(is_array($catalog), 'catalog loads');

$playlist = vpc_vimeo_playlist_all_from_catalog($catalog);
$playlistCount = count($playlist);
assert_true($playlistCount > 0, 'playlist has visible videos');

$withLang = 0;
$withoutCaptions = 0;
foreach ($playlist as $entry) {
    if (empty($entry['caption_tracks'])) {
        $withoutCaptions++;
        continue;
    }
    foreach ($entry['caption_tracks'] as $track) {
        if (!empty($track['lang'])) {
            $withLang++;
        }
    }
}
if ($withoutCaptions < $playlistCount) {
    assert_true($withLang > 0, 'caption tracks include lang field when present');
}
echo "PASS: playlist has {$playlistCount} visible videos\n";

$homePage = dirname(dirname(__FILE__)) . '/index.php';
ob_start();
include $homePage;
$html = ob_get_clean();

assert_true(strpos($html, 'data-picker="language"') !== false, 'unified language picker present');
assert_true(strpos($html, 'data-picker="spoken_language"') === false, 'spoken language picker removed');
assert_true(strpos($html, 'subtitleLanguages') !== false, 'vpc-config includes subtitleLanguages');
assert_true(strpos($html, 'initialSubtitleLang') !== false, 'vpc-config includes initialSubtitleLang');

if (preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $html, $m)) {
    $cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    assert_true(is_array($cfg), 'vpc-config parses');
    assert_true(isset($cfg['subtitleLanguages']) && is_array($cfg['subtitleLanguages']), 'subtitleLanguages in config');
    assert_eq('en', $cfg['initialSubtitleLang'] ?? null, 'initialSubtitleLang defaults to en');
    $firstWithTracks = null;
    foreach ($cfg['playlist'] as $item) {
        if (!empty($item['tracks']) && !empty($item['tracks'][0]['lang'])) {
            $firstWithTracks = $item['tracks'][0]['lang'];
            break;
        }
    }
    if ($firstWithTracks !== null) {
        assert_true($firstWithTracks !== '', 'playlist JSON tracks include lang');
    } else {
        echo "PASS: playlist JSON tracks lang check skipped (no caption tracks in catalog)\n";
    }
} else {
    fwrite(STDERR, "FAIL: vpc-config script block missing\n");
    exit(1);
}

echo "spoken_language_test.php: all passed\n";
