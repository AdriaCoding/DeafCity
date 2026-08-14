<?php
/**
 * Preview captions — single line on wide viewports; two-line wrap on narrow screens.
 *
 * Run: php8.4 preview/tests/caption_single_line_test.php
 */

require dirname(dirname(__FILE__)) . '/lib/caption_cues.php';

function csl_assert_eq($expected, $actual, $label) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

csl_assert_eq(60, vpc_caption_target_max_chars(), 'target max chars');
csl_assert_eq(5, vpc_caption_max_chars_tolerance(), 'tolerance');
csl_assert_eq(65, vpc_caption_display_max_length(), 'display max length');

csl_assert_eq(
    'Hello world',
    vpc_normalize_caption_display_text("Hello\nworld"),
    'collapses internal newlines'
);

csl_assert_eq(
    'One two three',
    vpc_normalize_caption_display_text("  One   two   three  \n"),
    'collapses whitespace'
);

$sixtySix = str_repeat('c', 66);
csl_assert_eq($sixtySix, vpc_normalize_caption_display_text($sixtySix), 'long text not truncated');

$accented = 'café réponden con un aplauso muy largo que supera el límite permitido aquí';
csl_assert_eq($accented, vpc_normalize_caption_display_text($accented), 'long accented text preserved');

$cssPath = dirname(dirname(__FILE__)) . '/components/vimeo_caption_player.css';
$css = file_get_contents($cssPath);
if (strpos($css, 'white-space: nowrap') === false) {
    fwrite(STDERR, "FAIL: caption CSS missing white-space: nowrap\n");
    exit(1);
}
echo "PASS: caption CSS enforces single line on wide viewports\n";

if (strpos($css, 'caption-box--two-line') === false) {
    fwrite(STDERR, "FAIL: caption CSS missing narrow two-line modifier\n");
    exit(1);
}
echo "PASS: caption CSS defines narrow two-line wrap\n";

if (!preg_match('~\.caption-box\s*\{([^}]*)\}~s', $css, $captionBoxRule)) {
    fwrite(STDERR, "FAIL: caption CSS missing .caption-box rule\n");
    exit(1);
}
if (strpos($captionBoxRule[1], 'text-overflow: ellipsis') !== false) {
    fwrite(STDERR, "FAIL: caption CSS should not ellipsis long tracks\n");
    exit(1);
}
echo "PASS: caption CSS does not truncate with ellipsis\n";

if (strpos($css, '55px') === false) {
    fwrite(STDERR, "FAIL: caption CSS missing 55px single-line block height\n");
    exit(1);
}
echo "PASS: caption CSS uses 55px block height\n";

$jsPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
$js = file_get_contents($jsPath);
if (strpos($js, 'captionFitFontSizeForDisplay') === false) {
    fwrite(STDERR, "FAIL: player JS missing two-line shrink-to-fit helper\n");
    exit(1);
}
echo "PASS: player JS uses two-line shrink budget on narrow viewports\n";

if (strpos($js, 'syncCaptionBlockLayout') === false) {
    fwrite(STDERR, "FAIL: player JS missing dynamic caption block layout\n");
    exit(1);
}
echo "PASS: player JS adjusts caption block height for line mode\n";

if (strpos($js, 'refitCaptionBox') === false) {
    fwrite(STDERR, "FAIL: player JS missing refitCaptionBox shrink-to-fit\n");
    exit(1);
}
echo "PASS: player JS shrinks caption font to fit\n";

if (strpos($js, 'measureCaptionTextWidth') === false) {
    fwrite(STDERR, "FAIL: player JS missing caption width measurement\n");
    exit(1);
}
echo "PASS: player JS measures caption text width\n";

if (strpos($js, 'L.normalizeCaptionText') === false) {
    fwrite(STDERR, "FAIL: player JS does not normalize caption text\n");
    exit(1);
}
echo "PASS: player JS normalizes caption text on sync\n";

echo "\nAll caption_single_line tests passed.\n";
