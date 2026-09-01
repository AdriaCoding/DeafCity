<?php
// Run: php8.4 preview/tests/caption_accessibility_test.php
//
// The caption box must be announced to screen readers as it updates (aria-live),
// read as one unit rather than word-by-word diffs (aria-atomic), follow the script
// direction of whatever is actually on screen (dir="auto" — Arabic/Hebrew subtitles
// mixed with LTR chrome), and use the correct `lang` per active subtitle track so a
// screen reader picks the right voice.

function ca_assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$previewDir = dirname(dirname(__FILE__));

$homePage = $previewDir . '/index.php';
ob_start();
include $homePage;
$playerHtml = ob_get_clean();

if (!preg_match('~<div id="[^"]*__captions" class="caption-box"([^>]*)></div>~', $playerHtml, $m)) {
    fwrite(STDERR, "FAIL: caption box markup not found\n");
    exit(1);
}
$captionBoxAttrs = $m[1];

ca_assert_contains('aria-live="polite"', $captionBoxAttrs, 'caption box has aria-live="polite"');
ca_assert_contains('aria-atomic="true"', $captionBoxAttrs, 'caption box has aria-atomic="true"');
ca_assert_contains('dir="auto"', $captionBoxAttrs, 'caption box has dir="auto"');

$playerJs = file_get_contents($previewDir . '/js/vimeo_caption_player.js');
ca_assert_contains('function activeCaptionLangCode', $playerJs, 'JS resolves the active caption lang tag');
ca_assert_contains("box.setAttribute('lang', langCode)", $playerJs, 'JS sets lang on the caption box when swapping caption text');
ca_assert_contains('cueTracks[activeCaptionTrackIndex]', $playerJs, 'active caption lang is resolved from the active subtitle track, not a fixed default');

echo "\nAll caption_accessibility tests passed.\n";
