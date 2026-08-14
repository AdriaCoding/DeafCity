<?php
// Run: php8.4 preview/tests/home_page_test.php

// Failures from the assert_* helpers are collected rather than fatal, so one red
// assertion cannot hide every assertion after it. That masking is not hypothetical:
// stale copy expectations here concealed an unrelated icon mismatch for two weeks.
// The ~60 hand-written `exit(1)` blocks further down still stop the run; the shutdown
// summary below reports whatever was collected before they do.
$GLOBALS['home_page_test_failures'] = array();

function record_failure($message)
{
    $GLOBALS['home_page_test_failures'][] = $message;
    fwrite(STDERR, "FAIL: {$message}\n");
}

register_shutdown_function(function () {
    $failures = $GLOBALS['home_page_test_failures'];
    if (count($failures) === 0) {
        return;
    }
    fwrite(STDERR, "\n" . count($failures) . " failing assertion(s):\n");
    foreach ($failures as $i => $message) {
        fwrite(STDERR, '  ' . ($i + 1) . ". {$message}\n");
    }
    exit(1);
});

function assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        record_failure("{$label} — expected to contain: {$needle}");
        return;
    }
    echo "PASS: {$label}\n";
}

function assert_not_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) !== false) {
        record_failure("{$label} — should not contain: {$needle}");
        return;
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
assert_contains('vpc-deaf-hearing-btn', $html, 'DEAF+HEARING control in chrome');
if (preg_match('~class="[^"]*vpc-deaf-hearing-btn[^"]*is-active~', $html)) {
    fwrite(STDERR, "FAIL: DEAF+HEARING should be inactive by default (no is-active)\n");
    exit(1);
}
if (preg_match('~vpc-deaf-hearing-btn[^>]*aria-pressed="false"~', $html) !== 1
    && preg_match('~vpc-deaf-hearing-btn[^>]*aria-pressed=\'false\'~', $html) !== 1) {
    // Button attrs may span lines
    if (!preg_match('~vpc-deaf-hearing-btn[\s\S]{0,400}?aria-pressed="false"~', $html)) {
        fwrite(STDERR, "FAIL: DEAF+HEARING should expose aria-pressed=\"false\" by default\n");
        exit(1);
    }
}
// DH13: the control carries the localized accessible name. Resolved from the store
// rather than hard-coded — this copy is owned by Antoni via Studio and changes freely
// (it has already moved once from "Deaf & Hearing interactions"), so pinning the literal
// made this test fail on a copy edit rather than on a real regression.
require_once dirname(__DIR__) . '/lib/preview_locale.php';
$dhLocale = preview_bootstrap_locale();
$dhName = $dhLocale['i18n']->t('player.filter.deaf_hearing');
assert_contains(
    htmlspecialchars($dhName, ENT_QUOTES, 'UTF-8'),
    $html,
    'DEAF+HEARING accessible name (DH13)'
);
if (preg_match('~vpc-deaf-hearing-btn[\s\S]{0,400}?\bdisabled\b~', $html)) {
    fwrite(STDERR, "FAIL: DEAF+HEARING should be enabled when catalog has DEAF&HEARING tags (DH27)\n");
    exit(1);
}
echo "PASS: DEAF+HEARING enabled, inactive, aria-pressed=false by default\n";

// Maketa desktop DOM wings: ? · LANGUAGES · DEAF+HEARING · TYPOLOGIES | transport | SIGNS · CITIES · PARTICIPANTS · reset
$aboutPos = strpos($html, '/preview/about');
$deafPos = strpos($html, 'vpc-deaf-hearing-btn');
$typPos = strpos($html, 'data-picker="typology"');
$langPos = strpos($html, 'data-picker="language"');
$signPos = strpos($html, 'data-picker="sign_language"');
$edPos = strpos($html, 'data-picker="edition"');
$partPos = strpos($html, '/preview/participants');
$prevPos = strpos($html, 'vpc-prev-btn');
$nextPos = strpos($html, 'vpc-next-btn');
$resetPos = strpos($html, 'vpc-reset-btn');
if ($aboutPos === false || $deafPos === false || $typPos === false || $langPos === false
    || $signPos === false || $edPos === false || $partPos === false
    || $prevPos === false || $nextPos === false || $resetPos === false) {
    fwrite(STDERR, "FAIL: missing control-row markers for player wing order check\n");
    exit(1);
}
if (!($aboutPos < $langPos && $langPos < $deafPos && $deafPos < $typPos
    && $langPos < $signPos && $signPos < $edPos && $edPos < $partPos
    && $partPos < $resetPos
    && $clusterPos < $prevPos && $prevPos < $centerPos && $centerPos < $nextPos)) {
    fwrite(STDERR, "FAIL: desktop maketa order should be ? → Language → DEAF+HEARING → Typology → Sign → Edition → Participants → reset; transport prev → play → next\n");
    exit(1);
}
if (preg_match('~vpc-control-transport-cluster[^>]*>.*?vpc-reset-btn~s', $html)) {
    fwrite(STDERR, "FAIL: reset must not live inside transport cluster (maketa: right of Participants / mobile flank)\n");
    exit(1);
}
echo "PASS: desktop maketa wing order ? → Language → DEAF+HEARING → Typology | Sign → Edition → Participants → reset\n";

assert_not_contains('href="/preview/" class="preview-site-nav__btn"', $html, 'no Reproductor/home nav link');

assert_not_contains('data-picker="spoken_language"', $html, 'no spoken language picker in DOM');

// ── ADR-0009: intent-gated playback with sound and no mute control ───────────

// AC: Cold load stays paused and never requests muted playback.
if (preg_match('~player\.vimeo\.com/video/\d+[^"]*autoplay=([^&"]+)~', $html, $autoM)) {
    if ($autoM[1] !== '0') {
        fwrite(STDERR, "FAIL: cold load autoplay param is '{$autoM[1]}', expected '0'\n");
        exit(1);
    }
}
assert_contains('autoplay=0', $html, 'cold load remains paused');
assert_not_contains('muted=1', $html, 'cold load does not request muted playback');
echo "PASS: intent-gated playback on load\n";
assert_contains('preload=auto', $html, 'Vimeo preloads the current video before playback');

// AC: Videos cannot be muted through player chrome.
assert_not_contains('vpc-mute-btn', $html, 'no mute button element');
assert_not_contains('aria-label="Unmute video"', $html, 'no unmute control');
echo "PASS: no mute control\n";

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

// Solid white scrim for mid-playlist autoplay advances (no next-thumb flash).
if (strpos($html, 'class="vpc-load-scrim is-hidden"') === false
    && strpos($html, "class='vpc-load-scrim is-hidden'") === false
) {
    fwrite(STDERR, "FAIL: video shell should include a hidden solid white load scrim\n");
    exit(1);
}
echo "PASS: solid white load scrim present (hidden on cold load)\n";

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
assert_contains(
    'data-generic-label="' . htmlspecialchars($dhLocale['i18n']->t('player.filter.sign_language'), ENT_QUOTES, 'UTF-8') . '"',
    $html,
    'picker button has generic label attr'
);
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
assert_contains(htmlspecialchars($dhLocale['i18n']->t('player.filter.all_sign_languages'), ENT_QUOTES, 'UTF-8'), $html, 'sign language clear option carries the localized "all" label');

// AC: Dropdown lists sign languages present in catalog
assert_contains('LIBRAS Brazilian', $html, 'LIBRAS option in picker');
assert_contains('LSE Spanish', $html, 'LSE option in picker');
assert_contains('LIS Italian', $html, 'LIS option in picker');
assert_contains('LSF French', $html, 'LSF option in picker');
assert_contains('LSM Mexican', $html, 'LSM option in picker (tagged videos visible)');

// Empty facets absent — only sign languages present in catalog
assert_not_contains('GSS Greek', $html, 'empty facet (GSS) absent from dropdown');

// Issue #17: filters live in the single control row (right wing), not a separate stacked row
$controlRowPos = strpos($html, 'vpc-control-row');
$r2Pos = strpos($html, 'vpc-r2-filters');
if ($r2Pos === false || $controlRowPos === false || $r2Pos < $controlRowPos) {
    fwrite(STDERR, "FAIL: vpc-r2-filters should appear inside vpc-control-row\n");
    exit(1);
}
echo "PASS: filters inside single control row\n";

// AC: Each catalog item has catalog fields (sign_language, edition, typology, participant, tags).
// Issue #01: `playlist` is now the reduced cold-entry base playlist (one video per
// city) — too small to reliably contain a DEAF&HEARING-tagged item every render, so
// this field/tag-presence check reads `catalogPlaylist` (the full catalog) instead,
// which is what it always meant to validate.
$cfg2 = $cfg; // already parsed above
$playlistItems2 = isset($cfg2['catalogPlaylist']) && is_array($cfg2['catalogPlaylist']) ? $cfg2['catalogPlaylist'] : [];
if (count($playlistItems2) === 0) {
    fwrite(STDERR, "FAIL: catalogPlaylist is empty in vpc-config for field check\n");
    exit(1);
}
$missingFields = [];
$taggedCount = 0;
foreach ($playlistItems2 as $i => $item) {
    if (!array_key_exists('signLanguage', $item)) { $missingFields[] = "playlist[$i].signLanguage"; }
    if (!array_key_exists('edition', $item))      { $missingFields[] = "playlist[$i].edition"; }
    if (!array_key_exists('typology', $item))     { $missingFields[] = "playlist[$i].typology"; }
    if (!array_key_exists('participant', $item))  { $missingFields[] = "playlist[$i].participant"; }
    if (!array_key_exists('tags', $item) || !is_array($item['tags'])) {
        $missingFields[] = "playlist[$i].tags";
    } elseif (in_array('DEAF&HEARING', $item['tags'], true)) {
        $taggedCount++;
    }
}
if (count($missingFields) > 0) {
    fwrite(STDERR, "FAIL: Missing catalog fields in catalogPlaylist JSON: " . implode(', ', $missingFields) . "\n");
    exit(1);
}
if ($taggedCount < 1) {
    fwrite(STDERR, "FAIL: expected at least one catalogPlaylist item with DEAF&HEARING tag\n");
    exit(1);
}
echo "PASS: all catalogPlaylist items have signLanguage, edition, typology, participant, tags fields ($taggedCount tagged)\n";

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
assert_contains(htmlspecialchars($dhLocale['i18n']->t('player.filter.all_cities'), ENT_QUOTES, 'UTF-8'), $html, 'edition clear option carries the localized "all" label');
assert_contains(htmlspecialchars($dhLocale['i18n']->t('player.filter.all_typologies'), ENT_QUOTES, 'UTF-8'), $html, 'typology clear option carries the localized "all" label');

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
assert_contains('vpc-reset-btn__text', $html, 'reset keeps accessible text for screen readers');
assert_contains('aria-label="Reset filters and playlist (R)"', $html, 'reset retains aria-label with shortcut hint');
assert_contains('/preview/img/help_80dp_007800.svg', $html, 'help uses filled circle SVG');
assert_contains('/preview/img/skip_previous_80dp_007800.svg', $html, 'prev uses borderless skip SVG');
assert_contains('/preview/img/play_circle_80dp_007800.svg', $html, 'play uses filled circle SVG');
assert_contains('/preview/img/skip_next_80dp_007800.svg', $html, 'next uses borderless skip SVG');
assert_contains('/preview/img/replay_circle_filled_80dp_007800.svg', $html, 'reset uses filled replay SVG');
assert_contains('pause_circle_80dp_007800.svg', $html, 'pause circle SVG path available to player JS');
assert_contains('vpc-play-pause-btn__hourglass', $html, 'play button contains sandclock for loading state');
assert_contains('hourglass_empty', $html, 'loading sandclock uses Material hourglass icon');

$playerJsPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
if (is_file($playerJsPath)) {
    $playerJs = file_get_contents($playerJsPath);
    assert_not_contains('setShuffleToggleUi', $playerJs, 'no shuffle toggle UI dead code in player JS');
    assert_not_contains("querySelector('.vpc-shuffle-btn')", $playerJs, 'no shuffle button DOM queries in player JS');
    assert_contains('setTransportLoading', $playerJs, 'player shows transport spinner during loadVideo');
    assert_contains("querySelector('.vpc-poster-cover')", $playerJs, 'player controls the loading cover');
    assert_contains("querySelector('.vpc-load-scrim')", $playerJs, 'player controls the solid white load scrim');
    assert_contains('planLoadCover', $playerJs, 'player chooses thumb vs solid-white cover via playlist logic');
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
    assert_contains(
        "coverPlan.kind === 'solid-white'",
        $playerJs,
        'autoplay advances use solid white scrim instead of next thumb'
    );
    assert_contains('shouldRevealLoadCover', $playerJs, 'cover reveal waits for buffering to end');
    assert_contains("p.on('bufferstart'", $playerJs, 'player tracks Vimeo bufferstart for cover');
    assert_contains("p.on('bufferend'", $playerJs, 'player tracks Vimeo bufferend for cover');
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
    // Six square buttons share one fixed width (Toni: equal length, no stretch).
    assert_contains('--vpc-square-btn-w', $playerCss, 'square chrome buttons share one fixed-width var');
    assert_contains('width: 2.75rem', $playerCss, 'chrome icon boxes remain 44px');
    // Circular icons are cropped tight to their artwork so they read larger in the
    // 44px chrome box (see "Cropping round icons so that they appear larger").
    $circularIconPaths = array(
        dirname(dirname(__FILE__)) . '/img/help_80dp_007800.svg' => 'viewBox="2 2 20 20"',
        dirname(dirname(__FILE__)) . '/img/play_circle_80dp_007800.svg' => 'viewBox="2 2 16 16"',
        dirname(dirname(__FILE__)) . '/img/pause_circle_80dp_007800.svg' => 'viewBox="2 2 16 16"',
        dirname(dirname(__FILE__)) . '/img/replay_circle_filled_80dp_007800.svg' => 'viewBox="2 2 20 20"',
    );
    foreach ($circularIconPaths as $iconPath => $viewBox) {
        if (is_file($iconPath)) {
            $iconSvg = file_get_contents($iconPath);
            assert_contains($viewBox, $iconSvg, 'circular chrome SVG is cropped to its artwork');
        }
    }

    // Prev/next are NOT cropped to their artwork, deliberately. Their glyph (a bar plus
    // a triangle) occupies exactly 6..18 on both axes, so a tight "6 6 12 12" crop would
    // render it 1.5x larger than the circular icons beside it — and a triangle filling
    // its box reads optically heavier than a circle filling the same box. The 3-unit
    // padding is the optical correction that keeps the transport row visually even.
    // Confirmed intentional by the maintainer, Aug 2026.
    $paddedIconPaths = array(
        dirname(dirname(__FILE__)) . '/img/skip_previous_80dp_007800.svg' => 'viewBox="3 3 18 18"',
        dirname(dirname(__FILE__)) . '/img/skip_next_80dp_007800.svg' => 'viewBox="3 3 18 18"',
    );
    foreach ($paddedIconPaths as $iconPath => $viewBox) {
        if (is_file($iconPath)) {
            $iconSvg = file_get_contents($iconPath);
            assert_contains($viewBox, $iconSvg, 'non-circular chrome SVG keeps its optical padding');
        }
    }
    if (!preg_match(
        '~\.vpc-control-secondary-l > \.vpc-deaf-hearing-btn,\s*\.vpc-control-secondary-l > \.vpc-picker,\s*\.vpc-control-secondary-r > \.vpc-r2-filters > \.vpc-picker,\s*\.vpc-control-secondary-r > \.preview-site-nav\s*\{[^}]*flex:\s*0 1 var\(--vpc-square-btn-w\)~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: the six square chrome buttons should share --vpc-square-btn-w with flex-grow 0\n");
        exit(1);
    }
    echo "PASS: square chrome buttons share one fixed width (no stretch)\n";
    assert_contains('--vpc-top-crop', $playerCss, 'desktop video top crop uses one global custom property');
    if (!preg_match(
        '~@media screen and \(min-width: 651px\)\s*\{.*?--vpc-top-crop:\s*[^;]+;.*?\.video-shell iframe,\s*\.vpc-poster-cover\s*\{.*?height:\s*calc\(100%\s*\+\s*var\(--vpc-top-crop\)\).*?bottom:\s*0;.*?top:\s*auto;.*?transform:\s*translateX\(-50%\)~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: desktop video crop should grow and bottom-anchor both video layers\n");
        exit(1);
    }
    echo "PASS: desktop video crop grows and bottom-anchors both video layers\n";
    assert_contains('vpc-poster-cover', $playerCss, 'loading cover CSS');
    assert_contains('vpc-load-scrim', $playerCss, 'solid white load scrim CSS');
    assert_not_contains(
        'transition: opacity',
        $playerCss,
        'loading cover reveals without exposing Vimeo through a fade'
    );
    assert_contains('vpc-transport-spin', $playerCss, 'transport loading spinner animation');
    if (!preg_match(
        '~\[data-loading="true"\][^{]*\.vpc-play-pause-btn__hourglass[^}]*animation:\s*vpc-transport-spin~s',
        $playerCss
    ) && !preg_match(
        '~\.vpc-play-pause-btn__hourglass[^}]*vpc-transport-spin~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: sandclock should spin inside play button while loading\n");
        exit(1);
    }
    echo "PASS: sandclock spins inside play button while loading\n";
    if (!preg_match(
        '~\[data-loading="true"\][^{]*\.vpc-chrome-icon[^}]*display:\s*none~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: play/pause SVG should hide while sandclock loading state is active\n");
        exit(1);
    }
    echo "PASS: play SVG hidden during sandclock loading\n";
    if (!preg_match(
        '~\[data-loading="true"\]::before[^}]*width:\s*80%~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: loading circle should be 80% of play icon box to match SVG optical size\n");
        exit(1);
    }
    echo "PASS: loading circle matches play SVG optical size\n";
    assert_contains('vpc-chrome-btn__label', $html, 'chrome button labels wrapped for ellipsis');
    assert_contains('vpc-chrome-btn__label-full', $html, 'chrome buttons carry full face label');
    assert_contains('vpc-chrome-btn__label-short', $html, 'chrome buttons carry short face label');
    if (!preg_match('~\.vpc-chrome-btn__label-full[^}]*text-overflow:\s*ellipsis~s', $playerCss)) {
        fwrite(STDERR, "FAIL: chrome button full label span should ellipsis overflow\n");
        exit(1);
    }
    if (!preg_match(
        '~@media screen and \(max-width: 1024px\)\s*\{[^}]*\.vpc-chrome-btn__label-full\s*\{[^}]*display:\s*none~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: maketa should hide full face labels at ≤1024\n");
        exit(1);
    }
    echo "PASS: chrome button dual face labels swap at maketa breakpoint\n";
    // Maketa narrow (≤1024): shared flatten; phone ≤500 = 2×3; mid 501–1024 = 3×2
    if (!preg_match(
        '~@media screen and \(max-width: 1024px\)\s*\{.*?\.vpc-control-secondary-l,\s*\.vpc-control-secondary-r,.*?display:\s*contents~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mobile maketa should flatten wing wrappers with display:contents\n");
        exit(1);
    }
    echo "PASS: mobile maketa flattens wing wrappers\n";
    if (!preg_match(
        '~@media screen and \(max-width: 1024px\)\s*\{.*?\.vpc-control-secondary-r > \.preview-site-nav\s*\{[^}]*max-width:\s*var\(--vpc-square-btn-w\)~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mobile maketa Participants cell should share square chrome max-width\n");
        exit(1);
    }
    echo "PASS: mobile maketa Participants cell matches square chrome width\n";
    if (!preg_match(
        '~@media screen and \(max-width: 1024px\)\s*\{[^}]*--vpc-mobile-col-gap:\s*0\.5rem~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mobile maketa should define a fixed center gutter between columns\n");
        exit(1);
    }
    echo "PASS: mobile maketa center column gutter\n";
    // Phone ≤500: 2-col × 3-row
    if (!preg_match(
        '~@media screen and \(max-width: 500px\)\s*\{.*?grid-template-columns:\s*1fr var\(--vpc-square-btn-w\) var\(--vpc-mobile-col-gap\) var\(--vpc-square-btn-w\) 1fr~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: phone maketa should center a 2-col button pair\n");
        exit(1);
    }
    echo "PASS: phone maketa centered 2-col button pair\n";
    if (!preg_match(
        '~@media screen and \(max-width: 500px\)\s*\{.*?grid-template-areas:\s*["\']icons icons icons icons icons["\'].*?["\']\. lang \. signs \.["\'].*?["\']\. cities \. participants \.["\'].*?["\']\. deaf \. typology \.["\']~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: phone maketa grid-template-areas should match 2×3 L|R pairs\n");
        exit(1);
    }
    echo "PASS: phone maketa grid areas match 2×3 filter pairs\n";
    if (!preg_match(
        '~@media screen and \(max-width: 500px\)\s*\{.*?\.vpc-control-secondary-l > \.preview-site-nav\s*\{[^}]*grid-column:\s*2~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: phone maketa should align help with left filter column\n");
        exit(1);
    }
    echo "PASS: phone maketa help aligns with left filter column\n";
    if (!preg_match(
        '~@media screen and \(max-width: 500px\)\s*\{.*?\.vpc-reset-btn\s*\{[^}]*grid-column:\s*4~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: phone maketa should align reset with right filter column\n");
        exit(1);
    }
    echo "PASS: phone maketa reset aligns with right filter column\n";
    // Mid 501–1024: 3-col × 2-row
    if (!preg_match(
        '~@media screen and \(min-width: 501px\) and \(max-width: 1024px\)\s*\{.*?grid-template-columns:\s*1fr\s+var\(--vpc-square-btn-w\) var\(--vpc-mobile-col-gap\)\s+var\(--vpc-square-btn-w\) var\(--vpc-mobile-col-gap\)\s+var\(--vpc-square-btn-w\)\s+1fr~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mid maketa should center a 3-col button row\n");
        exit(1);
    }
    echo "PASS: mid maketa centered 3-col button row\n";
    if (!preg_match(
        '~@media screen and \(min-width: 501px\) and \(max-width: 1024px\)\s*\{.*?grid-template-areas:\s*["\']icons icons icons icons icons icons icons["\'].*?["\']\. lang \. signs \. cities \.["\'].*?["\']\. participants \. deaf \. typology \.["\']~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mid maketa grid-template-areas should match 3×2 layout\n");
        exit(1);
    }
    echo "PASS: mid maketa grid areas match 3×2 filter layout\n";
    if (!preg_match(
        '~@media screen and \(min-width: 501px\) and \(max-width: 1024px\)\s*\{.*?\.vpc-control-secondary-l > \.preview-site-nav\s*\{[^}]*grid-column:\s*2~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mid maketa should align help with left filter column\n");
        exit(1);
    }
    echo "PASS: mid maketa help aligns with left filter column\n";
    if (!preg_match(
        '~@media screen and \(min-width: 501px\) and \(max-width: 1024px\)\s*\{.*?\.vpc-reset-btn\s*\{[^}]*grid-column:\s*6~s',
        $playerCss
    )) {
        fwrite(STDERR, "FAIL: mid maketa should align reset with right filter column\n");
        exit(1);
    }
    echo "PASS: mid maketa reset aligns with right filter column\n";
}

// Reset is after Participants in the right wing (not inside transport)
if (!preg_match('~vpc-control-secondary-r[^>]*>.*?vpc-reset-btn~s', $html)) {
    fwrite(STDERR, "FAIL: reset button should appear in right secondary wing after Participants\n");
    exit(1);
}
echo "PASS: reset button in right wing after Participants\n";

// ── Issue #7: R3 Participants nav button ──────────────────────────────────────
assert_contains('/preview/participants', $html, 'Participants nav button in R3');
assert_contains('vpc-chrome-btn__label-full">Participants</span>', $html, 'Participants button label text');

echo "\nAll tests passed.\n";
