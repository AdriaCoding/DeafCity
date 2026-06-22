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
assert_contains('ministerio.png', $html, 'sponsor logos');
assert_contains('Roboto', $html, 'Roboto font');
assert_not_contains('go back to player', $html, 'no back link on about page');
assert_contains('preview-site-nav--navbar', $html, 'sticky bottom navbar on about page');
assert_contains('preview-site-nav__btn', $html, 'navbar uses button style');
assert_contains('href="/preview/"', $html, 'navbar includes Player route');
assert_contains('href="/preview/about"', $html, 'navbar includes About route');
assert_contains('href="/preview/participants"', $html, 'navbar includes Participants route');
assert_contains('aria-current="page"', $html, 'navbar marks current page');
assert_contains('>About</a>', $html, 'About label in navbar');

echo "All tests passed.\n";
