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

$homePage = dirname(dirname(__FILE__)) . '/index.php';
if (!is_file($homePage)) {
    fwrite(STDERR, "FAIL: home page missing at {$homePage}\n");
    exit(1);
}

ob_start();
include $homePage;
$html = ob_get_clean();

assert_contains('vimeo_caption_player', $html, 'player component');
assert_not_contains('>Player</span>', $html, 'no redundant player button on home');
assert_not_contains('is-active', $html, 'no active nav button on home');
assert_contains('href="/preview/about"', $html, 'about nav link');
assert_contains('preview-site-nav--chrome', $html, 'nav in player chrome');
assert_contains('vpc-site-nav-wrap', $html, 'nav below player controls');
assert_not_contains('preview-site-nav--overlay', $html, 'no top overlay nav');
assert_not_contains('proto-bar', $html, 'no prototype switcher');
assert_not_contains('variant=', $html, 'no variant query param logic');
assert_not_contains('vA-about', $html, 'no variant A about block');
assert_not_contains('vB-layout', $html, 'no variant B about block');
assert_not_contains('vC-about', $html, 'no variant C about block');
assert_not_contains('trio-wrap', $html, 'no inline trio video');
assert_contains('overflow: hidden', $html, 'non-scrollable body');

$transportPos = strpos($html, 'vpc-transport');
$navPos = strpos($html, 'vpc-site-nav-wrap');
if ($transportPos === false || $navPos === false || $navPos < $transportPos) {
    fwrite(STDERR, "FAIL: site nav should appear after transport controls\n");
    exit(1);
}
echo "PASS: nav below transport\n";

echo "All tests passed.\n";
