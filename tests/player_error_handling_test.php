<?php
// Run: php8.4 tests/player_error_handling_test.php
//
// Two failure paths must be visible, not silent: the Vimeo Player SDK failing to
// load, and a caption fetch failing after retries. New user-facing copy must go
// through the i18n mechanism (data/ui-localizations.json + preview_t()), never
// hand-written into a template.

function peh_assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$previewDir = dirname(dirname(__FILE__));

require_once $previewDir . '/lib/preview_locale.php';
$dataDir = preview_resolve_data_dir();
$entries = preview_i18n_load_store($dataDir . '/ui-localizations.json');

// ── New error strings go through the i18n store, not hand-written English ─────
foreach (array('player.error.sdk_load_failed', 'player.error.captions_failed') as $key) {
    if (!isset($entries[$key]) || !is_array($entries[$key])) {
        fwrite(STDERR, "FAIL: i18n store is missing key {$key}\n");
        exit(1);
    }
    $translations = isset($entries[$key]['translations']) ? $entries[$key]['translations'] : array();
    foreach (array('en', 'fr', 'ca', 'es', 'it', 'pt', 'ar', 'eu') as $langId) {
        if (empty($translations[$langId])) {
            fwrite(STDERR, "FAIL: {$key} is missing a '{$langId}' translation (would break language completeness)\n");
            exit(1);
        }
    }
    echo "PASS: {$key} has translations for every subtitle language (completeness preserved)\n";
}

// ── Config carries the resolved strings down to JS via preview_t()/chromeMap() ──
ob_start();
include $previewDir . '/index.php';
$html = ob_get_clean();
if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $html, $m)) {
    fwrite(STDERR, "FAIL: no vpc-config on home page\n");
    exit(1);
}
$cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
if (empty($cfg['strings']['player.error.sdk_load_failed']) || empty($cfg['strings']['player.error.captions_failed'])) {
    fwrite(STDERR, "FAIL: player.error.sdk_load_failed / captions_failed not present in cfg.strings\n");
    exit(1);
}
echo "PASS: both new error strings reach the JS config via the standard chrome strings map\n";

peh_assert_contains(
    'class="vpc-caption-error is-hidden"',
    $html,
    'caption-fetch-error element rendered, hidden by default, in the player area'
);

// ── JS: SDK load failure shows a visible, non-intrusive message; caption fetch
//    retries with exponential backoff (3 attempts) before showing its own message ──
$playerJs = file_get_contents($previewDir . '/js/vimeo_caption_player.js');

peh_assert_contains('function showVpcPlayerError', $playerJs, 'JS defines a visible player-area error helper');
peh_assert_contains("ensureVimeoSdk(attachPlayer, function ()", $playerJs, 'SDK load wires an onError callback (not silent)');
peh_assert_contains("vpcString('player.error.sdk_load_failed'", $playerJs, 'SDK load error message is looked up via i18n, not hand-written');

peh_assert_contains('CAPTION_FETCH_MAX_ATTEMPTS = 3', $playerJs, 'caption fetch retries exactly 3 attempts total');
peh_assert_contains('CAPTION_FETCH_RETRY_BASE_MS', $playerJs, 'caption fetch retry uses a base delay for backoff');
peh_assert_contains('Math.pow(2, attemptNumber - 1)', $playerJs, 'caption fetch retry delay grows exponentially');
peh_assert_contains('function showCaptionFetchError', $playerJs, 'JS defines a caption-fetch-failed notice helper');
peh_assert_contains("vpcString('player.error.captions_failed'", $playerJs, 'caption fetch error message is looked up via i18n, not hand-written');

echo "\nAll player_error_handling tests passed.\n";
