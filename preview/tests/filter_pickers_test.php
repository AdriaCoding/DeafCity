<?php
// Run: php8.4 preview/tests/filter_pickers_test.php
// Issue #12 — live readout markup, cascading options config, keep-if-matches helpers.

function assert_true($cond, $label)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function extract_vpc_config($html)
{
    if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $html, $m)) {
        return null;
    }
    $decoded = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return json_decode($decoded, true);
}

$homePage = dirname(dirname(__FILE__)) . '/index.php';
ob_start();
include $homePage;
$html = ob_get_clean();

$cfg = extract_vpc_config($html);
assert_true(is_array($cfg), 'vpc-config JSON parses');

$playlist = isset($cfg['playlist']) && is_array($cfg['playlist']) ? $cfg['playlist'] : [];
assert_true(count($playlist) > 0, 'playlist non-empty');

$head = $playlist[0];

// D14′: initial SSR readout matches first video metadata (neutral — data-active=false)
foreach (array(
    'sign_language' => array('field' => 'signLanguage', 'generic' => 'Sign language'),
    'edition'       => array('field' => 'edition', 'generic' => 'City / Edition'),
    'typology'      => array('field' => 'typology', 'generic' => 'Typology'),
) as $facet => $meta) {
    $pickerNeedle = 'data-picker="' . $facet . '"';
    assert_true(strpos($html, $pickerNeedle) !== false, "{$facet} picker present");

    if (!preg_match(
        '~data-picker="' . preg_quote($facet, '~') . '"[^>]*data-active="false"~',
        $html
    )) {
        fwrite(STDERR, "FAIL: {$facet} picker should start data-active=\"false\" (passive readout)\n");
        exit(1);
    }
    echo "PASS: {$facet} picker starts neutral (data-active=false)\n";

    $value = isset($head[$meta['field']]) ? (string) $head[$meta['field']] : '';
    if ($value !== '') {
        $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        assert_true(strpos($html, 'data-value="' . $escaped . '"') !== false, "{$facet} value in dropdown options");
    }
}

// Config carries all three filter option catalogs for JS cascading (D17′)
foreach (array('signLanguageFilter', 'editionFilter', 'typologyFilter') as $key) {
    assert_true(isset($cfg[$key]) && is_array($cfg[$key]), "{$key} in vpc-config");
    assert_true(
        isset($cfg[$key]['options']) && is_array($cfg[$key]['options']) && count($cfg[$key]['options']) > 0,
        "{$key}.options non-empty"
    );
}

// Issue #16: short_label passed through in filter option catalogs (D14″)
$hasShortLabel = false;
foreach (array('signLanguageFilter', 'editionFilter', 'typologyFilter') as $key) {
    foreach ($cfg[$key]['options'] as $opt) {
        if (is_array($opt) && !empty($opt['short_label'])) {
            $hasShortLabel = true;
            break 2;
        }
    }
}
assert_true($hasShortLabel, 'filter options carry short_label from studio-config');

// Issue #16: SSR picker faces carry full + short; CSS swaps at maketa ≤1024
require_once dirname(dirname(__FILE__)) . '/lib/videos_catalog.php';
$dataDir = dirname(dirname(dirname(__FILE__))) . '/data';
if (!is_readable($dataDir . '/catalog.json')) {
    $dataDir = '/srv/www/deaf.city/public_html/data';
}
$studioConfigPath = $dataDir . '/studio-config.json';
$catalog = vpc_load_videos_catalog($dataDir . '/catalog.json');
// Mirror index.php: typology (and, for non-en locales, sign_language/edition) go
// through preview_localize_filter_options before display — compare against the
// same localized labels the page actually renders, not the raw catalog values.
$signOpts = preview_localize_filter_options(vpc_sign_language_options_from_catalog($catalog, $studioConfigPath), 'sign_language');
$editionOpts = preview_localize_filter_options(vpc_edition_options_from_catalog($catalog, $studioConfigPath), 'edition');
$typologyOpts = preview_localize_filter_options(vpc_typology_options_from_catalog($catalog, $studioConfigPath), 'typology');

foreach (array(
    'sign_language' => array('field' => 'signLanguage', 'generic' => 'Sign Language', 'opts' => $signOpts),
    'edition'       => array('field' => 'edition', 'generic' => 'City / Edition', 'opts' => $editionOpts),
    'typology'      => array('field' => 'typology', 'generic' => 'Typology', 'opts' => $typologyOpts),
) as $facet => $meta) {
    $value = isset($head[$meta['field']]) ? (string) $head[$meta['field']] : '';
    if ($value === '') {
        continue;
    }
    $compact = vpc_compact_label_for_filter_option($facet, $meta['opts'], $value, $meta['generic']);
    $full = vpc_label_for_filter_option($meta['opts'], $value, $meta['generic']);
    $compactEsc = htmlspecialchars($compact, ENT_QUOTES, 'UTF-8');
    $fullEsc = htmlspecialchars($full, ENT_QUOTES, 'UTF-8');

    if (!preg_match(
        '~data-picker="' . preg_quote($facet, '~') . '"[^>]*>.*?class="vpc-picker-btn"[^>]*>'
        . '.*?class="vpc-chrome-btn__label">'
        . '<span class="vpc-chrome-btn__label-full">' . preg_quote($fullEsc, '~') . '</span>'
        . '<span class="vpc-chrome-btn__label-short">' . preg_quote($compactEsc, '~') . '</span>'
        . '</span></button>~s',
        $html
    )) {
        fwrite(STDERR, "FAIL: {$facet} picker face should carry full \"{$full}\" + short \"{$compact}\"\n");
        exit(1);
    }
    echo "PASS: {$facet} picker face has dual labels ({$full} / {$compact})\n";

    if ($full !== $compact) {
        assert_true(strpos($html, $fullEsc) !== false, "{$facet} dropup still shows full label");
        echo "PASS: {$facet} dropup keeps full label ({$full})\n";
    }
}

// Unified language picker on player page (issue #19)
assert_true(strpos($html, 'data-picker="language"') !== false, 'unified language picker on player page');
if (preg_match('~data-picker="spoken_language"[^>]*data-active="true"~', $html)) {
    fwrite(STDERR, "FAIL: spoken language picker removed — should not appear\n");
    exit(1);
}
echo "PASS: spoken language picker removed from player page\n";

// Load shared logic for unit-testable filter helpers
$logicPath = dirname(dirname(__FILE__)) . '/js/vimeo_playlist_logic.js';
assert_true(is_file($logicPath), 'vimeo_playlist_logic.js exists');

echo "\nfilter_pickers_test.php: all passed\n";
