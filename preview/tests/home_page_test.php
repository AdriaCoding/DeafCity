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
assert_contains('vpc-bottom-bar--player', $html, 'unified bottom bar on player page');
assert_contains('vpc-site-nav-wrap', $html, 'nav in control row');
assert_contains('data-picker="language"', $html, 'unified language picker on player page');
assert_not_contains('aria-current="page"', $html, 'home player page has no current nav route');
assert_not_contains('data-picker="spoken_language"', $html, 'no spoken language picker');
assert_not_contains('preview-site-nav--chrome', $html, 'no legacy chrome nav class');
assert_contains('vpc-control-row', $html, 'single control row wrapper');
assert_contains('vpc-control-transport-cluster', $html, 'transport cluster group');
assert_contains('vpc-control-center', $html, 'play in center cell');
assert_not_contains('preview-site-nav--overlay', $html, 'no top overlay nav');
assert_not_contains('proto-bar', $html, 'no prototype switcher');
assert_not_contains('variant=', $html, 'no variant query param logic');
assert_not_contains('vA-about', $html, 'no variant A about block');
assert_not_contains('vB-layout', $html, 'no variant B about block');
assert_not_contains('vC-about', $html, 'no variant C about block');
assert_not_contains('trio-wrap', $html, 'no inline trio video');
assert_contains('overflow: hidden', $html, 'non-scrollable body');

// Issue #17 (D24/D25): play in center cell inside transport cluster; groups ordered secondary-L → transport → secondary-R
$clusterPos = strpos($html, 'vpc-control-transport-cluster');
$centerPos = strpos($html, 'vpc-control-center');
$playPos = strpos($html, 'vpc-play-pause-btn');
if ($clusterPos === false || $centerPos === false || $playPos === false || $playPos < $centerPos) {
    fwrite(STDERR, "FAIL: play button should live inside vpc-control-center\n");
    exit(1);
}
if (!preg_match('~<div[^>]*vpc-control-transport-cluster[^>]*>.*?vpc-play-pause-btn~s', $html)) {
    fwrite(STDERR, "FAIL: vpc-play-pause-btn should be inside vpc-control-transport-cluster\n");
    exit(1);
}
echo "PASS: play button in transport cluster center cell\n";

assert_contains('/preview/about', $html, 'about nav link');

$aboutPos = strpos($html, '/preview/about');
$typPos = strpos($html, 'data-picker="typology"');
$langPos = strpos($html, 'data-picker="language"');
$signPos = strpos($html, 'data-picker="sign_language"');
$edPos = strpos($html, 'data-picker="edition"');
$partPos = strpos($html, '/preview/participants');
$prevPos = strpos($html, 'vpc-prev-btn');
$nextPos = strpos($html, 'vpc-next-btn');
$resetPos = strpos($html, 'vpc-reset-btn');
if ($aboutPos === false || $typPos === false || $langPos === false
    || $signPos === false || $edPos === false || $partPos === false
    || $prevPos === false || $nextPos === false || $resetPos === false) {
    fwrite(STDERR, "FAIL: missing control-row markers for player wing order check\n");
    exit(1);
}
if (!($aboutPos < $typPos && $typPos < $langPos
    && $langPos < $signPos && $signPos < $edPos && $edPos < $partPos
    && $partPos < $clusterPos && $clusterPos < $prevPos
    && $prevPos < $centerPos && $centerPos < $nextPos && $nextPos < $resetPos)) {
    fwrite(STDERR, "FAIL: player wings should be About → Typology → Language → Sign lang → Edition → Participants → transport (prev → play → next → reset)\n");
    exit(1);
}
echo "PASS: player wing order About → Typology → Language | Sign lang → Edition → Participants\n";

assert_not_contains('href="/preview/" class="preview-site-nav__btn"', $html, 'no Reproductor/home nav link');

assert_not_contains('data-picker="spoken_language"', $html, 'no spoken language picker in DOM');

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
assert_contains('preload=auto', $html, 'Vimeo preloads the current video before playback');

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

// Loading cover: the server-selected video is visible as a poster while Vimeo initializes.
$headThumbnailUrl = isset($playlistItems[0]['thumbnailUrl'])
    ? (string)$playlistItems[0]['thumbnailUrl']
    : '';
if ($headThumbnailUrl === '') {
    fwrite(STDERR, "FAIL: playlist[0] needs a thumbnail for the loading cover\n");
    exit(1);
}
$escapedHeadThumbnailUrl = htmlspecialchars($headThumbnailUrl, ENT_QUOTES, 'UTF-8');
if (!preg_match(
    '~<img[^>]+class="vpc-poster-cover"[^>]+src="' . preg_quote($escapedHeadThumbnailUrl, '~') . '"[^>]*>~',
    $html
)) {
    fwrite(STDERR, "FAIL: initial loading cover should render playlist[0] thumbnail\n");
    exit(1);
}
echo "PASS: initial loading cover renders the selected video thumbnail\n";

// Issue #05 load polish: transport spinner during loadVideo.
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

// ── Issue #4: R2 filter row + Sign language custom picker ──────────────────────

// AC: R2 filter row renders in the player chrome (vpc-r2-filters)
assert_contains('vpc-r2-filters', $html, 'R2 filter row renders');

// AC: All pickers use custom dropdown — NOT a native <select>
assert_not_contains('<select', $html, 'no native <select> element (custom picker only)');
assert_contains('vpc-picker', $html, 'custom picker element present');
assert_contains('vpc-picker-btn', $html, 'custom picker button present');
assert_contains('vpc-picker-dropdown', $html, 'custom picker dropdown present');
assert_contains('data-picker="sign_language"', $html, 'sign_language picker attribute');

// AC: Picker button shows live readout from first video (D14′) — generic label in data-generic-label only
assert_contains('data-generic-label="Sign language"', $html, 'picker button has generic label attr');
assert_not_contains('data-picker="sign_language" data-active="true"', $html, 'sign language picker not green on load');
if (preg_match('~data-picker="sign_language"[^>]*data-active="false"~', $html) !== 1) {
    fwrite(STDERR, "FAIL: sign language picker should have data-active=\"false\" on cold load\n");
    exit(1);
}
echo "PASS: sign language picker shows passive readout on load (not green)\n";

// AC: Dropdown uses role=listbox (not native select)
assert_contains('role="listbox"', $html, 'dropdown has role=listbox');
assert_contains('role="option"', $html, 'dropdown options have role=option');

// AC: Clear/all option present
assert_contains('vpc-picker-clear', $html, 'clear/all option present in dropdown');
assert_contains('All sign languages', $html, 'clear option says "All sign languages"');

// AC: Dropdown lists sign languages present in catalog — all 4 known IDs must appear
// (libras, lse, lsm, gss) mapped to their config labels
assert_contains('LIBRAS Brazilian Sign Language', $html, 'LIBRAS option in picker');
assert_contains('LSE Spanish Sign Language', $html, 'LSE option in picker');
assert_contains('LIS Italian Sign Language', $html, 'LIS option in picker');
assert_contains('LSF French Sign Language', $html, 'LSF option in picker');

// Empty facets absent — only sign languages present in catalog
assert_not_contains('LSM Mexican Sign Language', $html, 'empty facet (LSM) absent from dropdown');
assert_not_contains('GSS Greek Sign Language', $html, 'empty facet (GSS) absent from dropdown');

// Issue #17: filters live in the single control row (right wing), not a separate stacked row
$controlRowPos = strpos($html, 'vpc-control-row');
$r2Pos = strpos($html, 'vpc-r2-filters');
if ($r2Pos === false || $controlRowPos === false || $r2Pos < $controlRowPos) {
    fwrite(STDERR, "FAIL: vpc-r2-filters should appear inside vpc-control-row\n");
    exit(1);
}
echo "PASS: filters inside single control row\n";

// AC: Each playlist item has catalog fields (sign_language, edition, typology, participant)
$cfg2 = $cfg; // already parsed above
$playlistItems2 = isset($cfg2['playlist']) && is_array($cfg2['playlist']) ? $cfg2['playlist'] : [];
if (count($playlistItems2) === 0) {
    fwrite(STDERR, "FAIL: playlist is empty in vpc-config for field check\n");
    exit(1);
}
$missingFields = [];
foreach ($playlistItems2 as $i => $item) {
    if (!array_key_exists('signLanguage', $item)) { $missingFields[] = "playlist[$i].signLanguage"; }
    if (!array_key_exists('edition', $item))      { $missingFields[] = "playlist[$i].edition"; }
    if (!array_key_exists('typology', $item))     { $missingFields[] = "playlist[$i].typology"; }
    if (!array_key_exists('participant', $item))  { $missingFields[] = "playlist[$i].participant"; }
}
if (count($missingFields) > 0) {
    fwrite(STDERR, "FAIL: Missing catalog fields in playlist JSON: " . implode(', ', $missingFields) . "\n");
    exit(1);
}
echo "PASS: all playlist items have signLanguage, edition, typology, participant fields\n";

// AC: signLanguageFilter config in vpc-config carries options array (D17)
if (!isset($cfg2['signLanguageFilter']) || !is_array($cfg2['signLanguageFilter'])) {
    fwrite(STDERR, "FAIL: signLanguageFilter missing from vpc-config JSON\n");
    exit(1);
}
$slOpts = isset($cfg2['signLanguageFilter']['options']) ? $cfg2['signLanguageFilter']['options'] : null;
if (!is_array($slOpts) || count($slOpts) === 0) {
    fwrite(STDERR, "FAIL: signLanguageFilter.options empty or missing in vpc-config JSON\n");
    exit(1);
}
echo "PASS: signLanguageFilter.options present in vpc-config (" . count($slOpts) . " options)\n";

// AC: editionFilter + typologyFilter in vpc-config for JS cascading (D17′, issue #12)
if (!isset($cfg2['editionFilter']) || !is_array($cfg2['editionFilter']['options'] ?? null)) {
    fwrite(STDERR, "FAIL: editionFilter.options missing from vpc-config JSON\n");
    exit(1);
}
if (!isset($cfg2['typologyFilter']) || !is_array($cfg2['typologyFilter']['options'] ?? null)) {
    fwrite(STDERR, "FAIL: typologyFilter.options missing from vpc-config JSON\n");
    exit(1);
}
echo "PASS: editionFilter and typologyFilter present in vpc-config\n";

// AC: Non-scrollable body preserved (D9)
assert_contains('overflow: hidden', $html, 'non-scrollable body still present');

// ── Issue #5: City/Edition + Typology pickers ─────────────────────────────────

// AC: Edition picker renders with data-picker="edition"
assert_contains('data-picker="edition"', $html, 'edition picker renders (data-picker=edition)');
assert_contains('data-generic-label="City / Edition"', $html, 'edition picker generic label');
if (preg_match('~data-picker="edition"[^>]*data-active="false"~', $html) !== 1) {
    fwrite(STDERR, "FAIL: edition picker should have data-active=\"false\" on cold load\n");
    exit(1);
}
echo "PASS: edition picker passive readout on load\n";

// AC: Typology picker renders with data-picker="typology"
assert_contains('data-picker="typology"', $html, 'typology picker renders (data-picker=typology)');
assert_contains('data-generic-label="Typology"', $html, 'typology picker generic label');
if (preg_match('~data-picker="typology"[^>]*data-active="false"~', $html) !== 1) {
    fwrite(STDERR, "FAIL: typology picker should have data-active=\"false\" on cold load\n");
    exit(1);
}
echo "PASS: typology picker passive readout on load\n";

// AC: Clear options for edition and typology pickers
assert_contains('All cities', $html, 'edition clear option says "All cities"');
assert_contains('All typologies', $html, 'typology clear option says "All typologies"');

// AC: Edition picker lists editions present in catalog
$catalogJsonPathForEditions = dirname(dirname(dirname(__FILE__))) . '/data/catalog.json';
if (!is_file($catalogJsonPathForEditions)) {
    $catalogJsonPathForEditions = '/srv/www/deaf.city/public_html/data/catalog.json';
}
$catalogForEditions = vpc_load_videos_catalog($catalogJsonPathForEditions);
$editionOpts = $catalogForEditions
    ? vpc_edition_options_from_catalog($catalogForEditions, $catalogJsonPathForEditions)
    : array();
$studioConfigForEditions = dirname($catalogJsonPathForEditions) . '/studio-config.json';
if ($catalogForEditions && is_file($studioConfigForEditions)) {
    $editionOpts = vpc_edition_options_from_catalog($catalogForEditions, $studioConfigForEditions);
}
foreach ($editionOpts as $edOpt) {
    if (!empty($edOpt['label'])) {
        assert_contains((string) $edOpt['label'], $html, 'edition option in picker: ' . $edOpt['label']);
    }
}

// AC: Empty editions absent when not in catalog
assert_not_contains('2021 Mexico City', $html, 'empty edition (Mexico City) absent from dropdown');
assert_not_contains('Salamanca 2028', $html, 'empty edition (Salamanca) absent from dropdown');

// AC: Typology picker lists typologies present in catalog (localized labels on render)
require_once dirname(dirname(__FILE__)) . '/lib/preview_locale.php';
$preview_i18n = preview_bootstrap_locale()['i18n'];
$typologyOpts = $catalogForEditions && is_file($studioConfigForEditions)
    ? preview_localize_filter_options(
        vpc_typology_options_from_catalog($catalogForEditions, $studioConfigForEditions),
        'typology'
    )
    : array();
foreach ($typologyOpts as $tyOpt) {
    if (!empty($tyOpt['label'])) {
        assert_contains((string) $tyOpt['label'], $html, 'typology option in picker: ' . $tyOpt['label']);
    }
    if (!empty($tyOpt['value'])) {
        assert_contains('data-value="' . $tyOpt['value'] . '"', $html, 'typology value present: ' . $tyOpt['value']);
    }
}

// AC: Option count sanity — 5 editions and 5 typologies present in catalog
$editionPickerSection = '';
if (preg_match('~data-picker="edition"[^>]*>.*?</div>~s', $html, $epMatch)) {
    $editionPickerSection = $epMatch[0];
}
$editionOptionCount = substr_count($editionPickerSection, 'role="option"');
$expectedEditionOptions = count($editionOpts) + 1;
if ($editionOptionCount !== $expectedEditionOptions) {
    fwrite(STDERR, "FAIL: edition picker should have {$expectedEditionOptions} options (" . count($editionOpts) . " editions + 1 clear), got {$editionOptionCount}\n");
    exit(1);
}
echo "PASS: edition picker has {$expectedEditionOptions} options (" . count($editionOpts) . " editions + 1 clear)\n";

$typologyPickerSection = '';
if (preg_match('~data-picker="typology"[^>]*>.*?</div>~s', $html, $tpMatch)) {
    $typologyPickerSection = $tpMatch[0];
}
$typologyOptionCount = substr_count($typologyPickerSection, 'role="option"');
$expectedTypologyOptions = count($typologyOpts) + 1;
if ($typologyOptionCount !== $expectedTypologyOptions) {
    fwrite(STDERR, "FAIL: typology picker should have {$expectedTypologyOptions} options (" . count($typologyOpts) . " typologies + 1 clear), got {$typologyOptionCount}\n");
    exit(1);
}
echo "PASS: typology picker has {$expectedTypologyOptions} options (" . count($typologyOpts) . " typologies + 1 clear)\n";

// Player wing filter order: sign_language → edition (typology is on the left wing)
$slPos  = strpos($html, 'data-picker="sign_language"');
$edPos  = strpos($html, 'data-picker="edition"');
$tyPos  = strpos($html, 'data-picker="typology"');
if ($slPos === false || $edPos === false || $tyPos === false) {
    fwrite(STDERR, "FAIL: one or more filter pickers missing from HTML\n");
    exit(1);
}
if (!($tyPos < $slPos && $slPos < $edPos)) {
    fwrite(STDERR, "FAIL: typology on left wing; right wing filters should be sign_language → edition\n");
    exit(1);
}
echo "PASS: typology on left; sign_language → edition on right wing\n";

// Issue #19: unified language picker + initialSubtitleLang in config
if (!isset($cfg['initialSubtitleLang']) || $cfg['initialSubtitleLang'] !== 'en') {
    fwrite(STDERR, "FAIL: initialSubtitleLang should be 'en' on default load, got: " . json_encode($cfg['initialSubtitleLang'] ?? null) . "\n");
    exit(1);
}
echo "PASS: initialSubtitleLang=en in vpc-config on default load\n";

$subtitleLangCfg = isset($cfg['subtitleLanguages']) ? $cfg['subtitleLanguages'] : null;
if (!is_array($subtitleLangCfg) || count($subtitleLangCfg) === 0) {
    fwrite(STDERR, "FAIL: subtitleLanguages in vpc-config is empty or missing\n");
    exit(1);
}
echo "PASS: subtitleLanguages present in vpc-config (" . count($subtitleLangCfg) . " entries)\n";

if (preg_match('~data-picker="language"[^>]*data-active="false"~', $html) !== 1) {
    fwrite(STDERR, "FAIL: language picker should have data-active=\"false\" when lang is en\n");
    exit(1);
}
echo "PASS: language picker neutral when English is active\n";

// ── Issue #01: Reset visible text; no shuffle button ─────────────────────────

assert_not_contains('vpc-shuffle-btn', $html, 'shuffle button removed from transport');
assert_contains('chrome_button_widths.js', $html, 'uniform chrome width sync script');
assert_contains('vpc-reset-btn__text', $html, 'reset button shows visible text');
assert_contains('aria-label="Reset filters and playlist"', $html, 'reset retains aria-label');

$playerJsPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
if (is_file($playerJsPath)) {
    $playerJs = file_get_contents($playerJsPath);
    assert_not_contains('setShuffleToggleUi', $playerJs, 'no shuffle toggle UI dead code in player JS');
    assert_not_contains("querySelector('.vpc-shuffle-btn')", $playerJs, 'no shuffle button DOM queries in player JS');
    assert_contains('setTransportLoading', $playerJs, 'player shows transport spinner during loadVideo');
    assert_contains("querySelector('.vpc-poster-cover')", $playerJs, 'player controls the loading cover');
    assert_contains("preload: 'auto'", $playerJs, 'playlist transitions preload initial video segments');
    assert_contains(
        'revealPosterAfterPlaybackProgress(data.seconds)',
        $playerJs,
        'loading cover waits for real playback progress'
    );
    assert_contains(
        'if (!paused) markPosterPlaybackStarted()',
        $playerJs,
        'already-running autoplay still arms progress-based reveal'
    );
}

$chromeWidthsPath = dirname(dirname(__FILE__)) . '/js/chrome_button_widths.js';
if (is_file($chromeWidthsPath)) {
    $chromeWidthsJs = file_get_contents($chromeWidthsPath);
    assert_contains('vpcSyncChromeButtonWidths', $chromeWidthsJs, 'chrome width sync hook present');
}

$playerCssPath = dirname(dirname(__FILE__)) . '/components/vimeo_caption_player.css';
if (is_file($playerCssPath)) {
    $playerCss = file_get_contents($playerCssPath);
    assert_contains('text-overflow: ellipsis', $playerCss, 'chrome buttons crop overflowing labels');
    assert_contains('--vpc-chrome-filter-flex', $playerCss, 'filter chrome flex weight vars');
    assert_contains('--vpc-chrome-participants-max', $playerCss, 'participants chrome max width var');
    assert_contains('--vpc-chrome-reset-max', $playerCss, 'reset chrome max width var');
    assert_contains('vpc-poster-cover', $playerCss, 'loading cover CSS');
    assert_not_contains(
        'transition: opacity',
        $playerCss,
        'loading cover reveals without exposing Vimeo through a fade'
    );
    assert_contains('vpc-transport-spin', $playerCss, 'transport loading spinner animation');
    assert_contains('vpc-chrome-btn__label', $html, 'chrome button labels wrapped for ellipsis');
    if (!preg_match('~\.vpc-chrome-btn__label[^}]*text-overflow:\s*ellipsis~s', $playerCss)) {
        fwrite(STDERR, "FAIL: chrome button label span should ellipsis overflow\n");
        exit(1);
    }
    echo "PASS: chrome button label span ellipsis overflow\n";
    if (!preg_match(
        '~@media screen and \(max-width: 1024px\)\s*\{.*?\.vpc-control-secondary\s*\{[^}]*width:\s*100%[^}]*\}.*?\.vpc-control-secondary-l,\s*\.vpc-control-secondary-r\s*\{[^}]*display:\s*contents~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: stacked control row should stretch secondary chrome across full width\n");
        exit(1);
    }
    echo "PASS: stacked secondary chrome stretches full width\n";
    if (!preg_match(
        '~@media screen and \(max-width: 1024px\)\s*\{.*?\.vpc-control-row \.vpc-control-secondary-l > \.preview-site-nav\s*\{[^}]*flex:\s*0\.65 1 0[^}]*\}.*?\.vpc-control-row \.vpc-control-secondary-l > \.vpc-picker\s*\{[^}]*flex:\s*1 1 0[^}]*\}.*?\.vpc-control-row \.vpc-control-secondary-r > \.vpc-r2-filters\s*\{[^}]*flex:\s*1\.7 1 0[^}]*\}.*?\.vpc-control-row \.vpc-control-secondary-r > \.preview-site-nav\s*\{[^}]*flex:\s*1\.3 1 0[^}]*max-width:\s*none~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: stacked chrome should reserve enough width for Participants\n");
        exit(1);
    }
    echo "PASS: stacked chrome reserves enough width for Participants\n";
}

// Reset lives in the transport cluster (after next)
if (!preg_match('~vpc-control-transport-cluster[^>]*>.*?vpc-next-btn.*?vpc-reset-btn~s', $html)) {
    fwrite(STDERR, "FAIL: reset button should appear in transport cluster after next\n");
    exit(1);
}
echo "PASS: reset button in transport cluster after next\n";

// ── Issue #7: R3 Participants nav button ──────────────────────────────────────
assert_contains('/preview/participants', $html, 'Participants nav button in R3');
assert_contains('vpc-chrome-btn__label">Participants</span>', $html, 'Participants button label text');

echo "\nAll tests passed.\n";
