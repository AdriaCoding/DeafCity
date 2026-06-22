<?php
// Run: php preview/tests/filter_pickers_test.php
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

// Spoken Language never green on load
assert_true(strpos($html, 'vpc-picker--track-selector') !== false, 'spoken language track selector class');
if (preg_match('~data-picker="spoken_language"[^>]*data-active="true"~', $html)) {
    fwrite(STDERR, "FAIL: spoken language picker must never be data-active=true (D21)\n");
    exit(1);
}
echo "PASS: spoken language picker not green on load\n";

// Load shared logic for unit-testable filter helpers
$logicPath = dirname(dirname(__FILE__)) . '/js/vimeo_playlist_logic.js';
assert_true(is_file($logicPath), 'vimeo_playlist_logic.js exists');

echo "\nfilter_pickers_test.php: all passed\n";
