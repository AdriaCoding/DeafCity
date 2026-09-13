<?php
// Run: php8.4 tests/about_page_test.php

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

$aboutPage = dirname(dirname(__FILE__)) . '/about/index.php';
if (!is_file($aboutPage)) {
    fwrite(STDERR, "FAIL: about page missing at {$aboutPage}\n");
    exit(1);
}

ob_start();
include $aboutPage;
$html = ob_get_clean();

assert_contains('realtime/index.html', $html, 'clock iframe');
assert_contains('id="gallery"', $html, 'gallery section');
assert_contains('gallery-image', $html, 'gallery images');
assert_contains('id="about-todo"', $html, 'about text');
assert_contains('id="trio"', $html, 'trio video');
assert_contains('id="sign-language-map"', $html, 'sign language map between video and credits');
$trioPos = strpos($html, 'id="trio"');
$mapPos = strpos($html, 'id="sign-language-map"');
$creditsPos = strpos($html, 'id="credits"');
if ($trioPos === false || $mapPos === false || $creditsPos === false || !($trioPos < $mapPos && $mapPos < $creditsPos)) {
    fwrite(STDERR, "FAIL: map must sit between trio video and credits\n");
    exit(1);
}
echo "PASS: map sits between trio video and credits\n";
assert_not_contains('sign-language-map-filter', $html, 'no sidebar category filter');
assert_not_contains('DEAF.city editions', $html, 'no filter legend labels');
assert_contains('/leaflet/leaflet.js', $html, 'leaflet script');
assert_contains('/js/sign_language_map.js', $html, 'map script');
assert_contains('/data/languages.json', $html, 'sign language geojson');
assert_contains('sign-language-map-attribution', $html, 'glottolog map attribution');
assert_contains('Glottolog 5.3', $html, 'glottolog version in attribution');
assert_contains('Classification adapted by DEAF.city', $html, 'deaf.city classification note');
assert_contains('/data/deafcity.json', $html, 'edition city markers');
assert_not_contains('proto-bar', $html, 'no prototype switcher');
assert_not_contains('prototype-map', $html, 'no prototype map files');
assert_not_contains('prototype-switcher', $html, 'no prototype switcher include');
assert_contains('id="credits"', $html, 'credits section');
assert_contains('credits-logos', $html, 'credits logos row');
assert_contains('ministerio.png', $html, 'sponsor logos');
assert_contains('Roboto', $html, 'Roboto font');
assert_not_contains('go back to player', $html, 'no back link on about page');
assert_contains('vpc-bottom-bar--player', $html, 'unified player chrome on about page');
assert_not_contains('vpc-bottom-bar--nav', $html, 'no legacy nav-mode bottom bar');
assert_contains('data-secondary-page="true"', $html, 'secondary page player chrome flag');
assert_contains('vpc-control-transport-cluster', $html, 'transport cluster on about page');
assert_contains('vpc-reset-btn__text', $html, 'reset shows visible text');
assert_contains('chrome_button_widths.js', $html, 'uniform chrome width sync script');
assert_not_contains('vpc-shuffle-btn', $html, 'no shuffle button on about page');
assert_not_contains('href="/preview/" class="preview-site-nav__btn"', $html, 'no Reproductor/home nav link');
assert_contains('preview-site-nav__btn', $html, 'navbar uses button style');
assert_contains('/about', $html, 'navbar includes About route');
assert_contains('/participants', $html, 'navbar includes Participants route');
assert_contains('aria-current="page"', $html, 'navbar marks current page');
assert_contains('vpc-chrome-btn__label">?</span>', $html, 'About nav button shows ? in all locales');
assert_not_contains('city-map-section', $html, 'map section removed');
assert_not_contains('about-map.js', $html, 'about map script removed');
assert_not_contains('d3.v7.min.js', $html, 'd3 script removed');
assert_contains('data-picker="language"', $html, 'language picker on about chrome');
assert_contains('data-picker="typology"', $html, 'typology picker on about chrome');
assert_contains('secondary_player_chrome.js', $html, 'secondary transport script');
assert_contains('English</li>', $html, 'English option in language picker');

$aboutCss = file_get_contents(dirname(dirname(__FILE__)) . '/css/about-page.css');
assert_contains('#sign-language-map', $aboutCss, 'full-width map layout in about css');
assert_not_contains('#sign-language-map-filter', $aboutCss, 'no filter column styles');
assert_not_contains('15.5rem', $aboutCss, 'no reserved sidebar column on the map');
assert_contains('#FFCC00', $aboutCss, 'merged sign-language markers are yellow');
assert_contains('rgb(0, 120, 0)', $aboutCss, 'DEAF.city markers stay green');

$mapJs = file_get_contents(dirname(dirname(__FILE__)) . '/js/sign_language_map.js');
assert_contains('PIDGIN_BRANCH = 979', $mapJs, 'pidgin branch is excluded');
assert_not_contains('setOpacity', $mapJs, 'no invisible-but-hoverable markers');
assert_contains('fitBounds', $mapJs, 'geography crops to visible points');
assert_not_contains('sign-language-map-filter', $mapJs, 'map script does not build a filter');

$cities = json_decode(file_get_contents(dirname(dirname(__FILE__)) . '/data/deafcity.json'), true);
if (!is_array($cities) || $cities === []) {
    fwrite(STDERR, "FAIL: deafcity.json must list DEAF.city map locations\n");
    exit(1);
}
foreach ($cities as $city) {
    $label = (string) ($city['label'] ?? '');
    if (!preg_match('/^DEAF\\.city .+ [A-Z0-9]{2,}$/', $label)) {
        fwrite(STDERR, "FAIL: map location label must be DEAF.city CITY CODE — got: {$label}\n");
        exit(1);
    }
}
echo "PASS: DEAF.city map labels use DEAF.city CITY CODE\n";
$barcelona = null;
foreach ($cities as $city) {
    if (($city['id'] ?? '') === '2026-barcelona') {
        $barcelona = $city;
        break;
    }
}
if ($barcelona === null || ($barcelona['label'] ?? '') !== 'DEAF.city BARCELONA LSC') {
    fwrite(STDERR, "FAIL: Barcelona map label must be DEAF.city BARCELONA LSC\n");
    exit(1);
}
echo "PASS: Barcelona map label\n";
assert_not_contains('proto-bar', $aboutCss, 'no prototype bar css');
assert_not_contains('#city-map-section', $aboutCss, 'map css removed');
assert_not_contains('max-width: 1200px', $aboutCss, 'narrow max-width removed');
assert_contains('align-items: stretch', $aboutCss, 'clock row stretches children');
assert_contains('flex: 1 1 calc(50% - 8px)', $aboutCss, 'clock children share row width');
assert_contains('.credits-logos', $aboutCss, 'credits logos flex layout');
assert_contains('overflow: hidden', $html, 'non-scrollable body on about page');
assert_contains('min-height: 0', $aboutCss, 'about scroll area shrinks inside flex column');

$bottomBarCss = file_get_contents(dirname(dirname(__FILE__)) . '/css/bottom-bar.css');
if (preg_match('~\[data-secondary-page="true"\]\s*\{[^}]*box-shadow~s', $bottomBarCss)) {
    fwrite(STDERR, "FAIL: secondary page bottom bar must not use box-shadow (horizontal rule)\n");
    exit(1);
}
echo "PASS: no box-shadow on secondary page bottom bar\n";
if (preg_match('~\[data-secondary-page="true"\]\s*\{[^}]+\}~s', $bottomBarCss)) {
    fwrite(STDERR, "FAIL: secondary page bottom bar must not add extra wrapper padding (match player control-row)\n");
    exit(1);
}
echo "PASS: secondary page chrome spacing matches player page\n";

echo "All tests passed.\n";
