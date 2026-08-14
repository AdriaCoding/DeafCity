<?php
// Run: php8.4 preview/tests/locale_payload_test.php
//
// The locale payload feeds the no-reload Website language switch. Its contract is
// "identical to what the server would render at ?lang=<id>", so every assertion here
// compares the payload against the existing server-side resolution rather than against
// literal copy. Copy is owned by Antoni via Studio and must be free to change without
// breaking tests.

require_once dirname(__DIR__) . '/lib/preview_locale.php';
require_once dirname(__DIR__) . '/lib/videos_catalog.php';

function assert_eq($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n  expected: " . var_export($expected, true) . "\n  actual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$dataDir = preview_resolve_data_dir();
$entries = preview_i18n_load_store($dataDir . '/ui-localizations.json');

// ── Chrome strings resolve exactly as the server resolves them ────────────────
$payload = preview_build_locale_payload('es');

assert_eq('es', $payload['lang'], 'payload reports the resolved Website language');
assert_eq('ltr', $payload['dir'], 'Spanish is left-to-right');

$reference = new PreviewI18n($entries, 'es');
$chromeKeys = array_keys($reference->chromeMap());
assert_eq(true, count($chromeKeys) > 0, 'fixture data has chrome keys to compare');

$mismatched = array();
foreach ($chromeKeys as $key) {
    if (!isset($payload['strings'][$key]) || $payload['strings'][$key] !== $reference->t($key)) {
        $mismatched[] = $key;
    }
}
assert_eq(array(), $mismatched, 'every chrome string matches server-side resolution');

// ── Direction follows the language, so RTL can be applied without a reload ────
$arabic = preview_build_locale_payload('ar');
assert_eq('ar', $arabic['lang'], 'Arabic resolves to itself');
assert_eq('rtl', $arabic['dir'], 'Arabic is right-to-left');

// ── Unknown ids fall through the shared resolver rather than erroring ─────────
$unknown = preview_build_locale_payload('zz');
assert_eq('en', $unknown['lang'], 'unrecognized language id falls back to en');
assert_eq('ltr', $unknown['dir'], 'fallback carries a usable direction');

$empty = preview_build_locale_payload('');
assert_eq('en', $empty['lang'], 'empty language id falls back to en');

// ── Filter option labels are identical to the server-rendered ones ────────────
// This is the no-drift guarantee: a page whose language was switched in session must
// show exactly the labels a cold load at ?lang=<id> would have shown. The expectation
// is built through the real SSR path (index.php), not restated by hand.
$catalog = vpc_load_videos_catalog($dataDir . '/catalog.json');
$collection = vpc_catalog_collection($catalog, $dataDir . '/studio-config.json');
assert_eq(true, count($collection['edition_options']) > 0, 'real catalog exposes filter options to compare');

foreach (array('es', 'ca', 'ar', 'en') as $langId) {
    // preview_localize_filter_options() reads the active language from this global,
    // exactly as index.php leaves it after preview_bootstrap_locale().
    $preview_i18n = new PreviewI18n($entries, $langId);

    $expected = array(
        'sign_language' => preview_localize_filter_options($collection['sign_language_options'], 'sign_language'),
        'edition' => preview_localize_filter_options($collection['edition_options'], 'edition'),
        'typology' => preview_localize_filter_options($collection['typology_options'], 'typology'),
    );

    $actual = preview_build_locale_payload($langId);
    foreach ($expected as $facet => $options) {
        assert_eq(
            $options,
            isset($actual['filter_options'][$facet]) ? $actual['filter_options'][$facet] : null,
            "{$langId}: {$facet} option labels match server-side rendering"
        );
    }
}

// ── The endpoint serves that payload as JSON ─────────────────────────────────
$endpoint = dirname(__DIR__) . '/api/locale.php';
assert_eq(true, is_file($endpoint), 'locale endpoint exists');

$_GET['lang'] = 'ca';
ob_start();
include $endpoint;
$body = ob_get_clean();

$decoded = json_decode($body, true);
assert_eq(true, is_array($decoded), 'endpoint emits parseable JSON');
assert_eq('ca', $decoded['lang'], 'endpoint honours the requested Website language');
assert_eq('ltr', $decoded['dir'], 'endpoint reports direction');
assert_eq(
    preview_build_locale_payload('ca'),
    $decoded,
    'endpoint body is exactly the payload — no second source of truth'
);
