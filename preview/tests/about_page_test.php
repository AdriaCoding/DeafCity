<?php
// Run: php preview/tests/about_page_test.php

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
assert_contains('/preview/about', $html, 'navbar includes About route');
assert_contains('/preview/participants', $html, 'navbar includes Participants route');
assert_contains('aria-current="page"', $html, 'navbar marks current page');
assert_contains('vpc-chrome-btn__label">About</span>', $html, 'About label in navbar');
assert_not_contains('city-map-section', $html, 'map section removed');
assert_not_contains('about-map.js', $html, 'about map script removed');
assert_not_contains('d3.v7.min.js', $html, 'd3 script removed');
assert_contains('data-picker="language"', $html, 'language picker on about chrome');
assert_contains('data-picker="typology"', $html, 'typology picker on about chrome');
assert_contains('secondary_player_chrome.js', $html, 'secondary transport script');
assert_contains('English</li>', $html, 'English option in language picker');

$aboutCss = file_get_contents(dirname(dirname(__FILE__)) . '/css/about-page.css');
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
