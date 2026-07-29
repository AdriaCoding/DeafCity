<?php
// Run: php8.4 preview/tests/rtl_chrome_test.php

function rtl_assert_not_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function rtl_assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$aboutPhp = file_get_contents(dirname(dirname(__FILE__)) . '/about/index.php');
$participantsPhp = file_get_contents(dirname(dirname(__FILE__)) . '/participants/index.php');
$indexPhp = file_get_contents(dirname(dirname(__FILE__)) . '/index.php');
$bottomBarCss = file_get_contents(dirname(dirname(__FILE__)) . '/css/bottom-bar.css');

rtl_assert_not_contains('html[dir="rtl"] .vpc-bottom-bar { direction: rtl; }', $aboutPhp, 'about page omits rtl bottom bar');
rtl_assert_not_contains('html[dir="rtl"] .vpc-control-row { direction: rtl; }', $aboutPhp, 'about page omits rtl control row');
rtl_assert_not_contains('html[dir="rtl"] .vpc-bottom-bar { direction: rtl; }', $participantsPhp, 'participants page omits rtl bottom bar');
rtl_assert_not_contains('html[dir="rtl"] .vpc-control-row { direction: rtl; }', $participantsPhp, 'participants page omits rtl control row');
rtl_assert_not_contains('html[dir="rtl"] .vpc-bottom-bar { direction: rtl; }', $indexPhp, 'player page omits rtl bottom bar');
rtl_assert_contains('direction: ltr', $bottomBarCss, 'bottom bar css forces ltr chrome in rtl pages');
rtl_assert_contains('html[dir="rtl"] .preview-about-page', $aboutPhp, 'about body text still rtl');

echo "\nAll rtl_chrome tests passed.\n";
