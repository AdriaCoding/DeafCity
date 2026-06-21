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
assert_contains('go back to player', $html, 'back link label');
assert_contains('preview-site-nav__link', $html, 'text link style on about page');
assert_not_contains('preview-site-nav__btn', $html, 'no button style on about page');
assert_not_contains('>About</a>', $html, 'no about link on about page');

echo "All tests passed.\n";
