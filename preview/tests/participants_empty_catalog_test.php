<?php
// Run: php8.4 preview/tests/participants_empty_catalog_test.php
//
// When data/catalog.json is missing or invalid, the home page already renders an
// explicit empty state (preview/index.php, player.error.no_playlist) instead of
// silently showing nothing. The Participants page must do the same instead of
// rendering a bare, empty grid.
//
// Trick: preview_resolve_data_dir() is defined behind an `if (!function_exists(...))`
// guard in preview/lib/preview_locale.php (the same pattern used throughout this
// codebase). Defining it here first — before anything requires that file — makes
// every guarded require downstream (in the Participants page and its libs) skip
// redefining it and use this override instead, so we can point at a data directory
// with everything except catalog.json without touching the real one.

function pec_assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
function pec_assert_not_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$previewDir = dirname(dirname(__FILE__));
$realDataDir = dirname($previewDir) . '/data';

$fakeDataDir = sys_get_temp_dir() . '/vpc-empty-catalog-test-' . getmypid();
if (!is_dir($fakeDataDir)) {
    mkdir($fakeDataDir, 0777, true);
}
// Copy everything the page chrome needs except catalog.json, so catalog loading
// fails exactly the way it would with a missing/corrupt catalog on the real site.
foreach (array('ui-localizations.json', 'studio-config.json') as $file) {
    $src = $realDataDir . '/' . $file;
    if (is_file($src)) {
        copy($src, $fakeDataDir . '/' . $file);
    }
}
if (file_exists($fakeDataDir . '/catalog.json')) {
    unlink($fakeDataDir . '/catalog.json');
}

function preview_resolve_data_dir()
{
    global $fakeDataDir;
    return $fakeDataDir;
}

ob_start();
include $previewDir . '/participants/index.php';
$html = ob_get_clean();

if (isset($catalog) && $catalog !== null) {
    fwrite(STDERR, "FAIL: test setup — catalog unexpectedly loaded from fake data dir\n");
    exit(1);
}
echo "PASS: fake data dir has no catalog.json (catalog resolves null)\n";

pec_assert_not_contains('class="participant-card"', $html, 'no participant cards rendered when catalog is missing');
pec_assert_not_contains('class="participants-grid"', $html, 'no empty grid silently rendered when catalog is missing');
pec_assert_contains(
    htmlspecialchars(preview_t('player.error.no_playlist'), ENT_QUOTES, 'UTF-8'),
    $html,
    'Participants page mirrors the home page empty-catalog message'
);

foreach (array('ui-localizations.json', 'studio-config.json') as $file) {
    @unlink($fakeDataDir . '/' . $file);
}
@rmdir($fakeDataDir);

echo "\nAll participants_empty_catalog tests passed.\n";
