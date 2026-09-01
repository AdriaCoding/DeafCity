<?php
// Run: php8.4 preview/tests/arabic_typography_test.php
//
// Arabic glyphs are not in Roboto. Preview must load Noto Sans Arabic and
// stack it after Roboto so Latin stays Roboto while Arabic (UI or captions)
// falls through — including when the website language is not Arabic.

function ar_assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$previewDir = dirname(dirname(__FILE__));

$homePage = $previewDir . '/index.php';
if (!is_file($homePage)) {
    fwrite(STDERR, "FAIL: home page missing at {$homePage}\n");
    exit(1);
}
ob_start();
include $homePage;
$playerHtml = ob_get_clean();

$aboutPage = $previewDir . '/about/index.php';
ob_start();
include $aboutPage;
$aboutHtml = ob_get_clean();

$participantsPage = $previewDir . '/participants/index.php';
ob_start();
include $participantsPage;
$participantsHtml = ob_get_clean();

ar_assert_contains('family=Noto+Sans+Arabic', $playerHtml, 'player loads Noto Sans Arabic from Google Fonts');
ar_assert_contains('family=Noto+Sans+Arabic', $aboutHtml, 'about loads Noto Sans Arabic from Google Fonts');
ar_assert_contains('family=Noto+Sans+Arabic', $participantsHtml, 'participants loads Noto Sans Arabic from Google Fonts');

$captionCss = file_get_contents($previewDir . '/components/vimeo_caption_player.css');
ar_assert_contains("font-family: 'Roboto', 'Noto Sans Arabic', sans-serif;", $captionCss, 'captions fall through to Noto Sans Arabic after Roboto');

$playerJs = file_get_contents($previewDir . '/js/vimeo_caption_player.js');
ar_assert_contains('Noto Sans Arabic', $playerJs, 'caption width measurement uses Noto Sans Arabic');

$aboutCss = file_get_contents($previewDir . '/css/about-page.css');
$participantsCss = file_get_contents($previewDir . '/css/participants-page.css');
$bottomBarCss = file_get_contents($previewDir . '/css/bottom-bar.css');
ar_assert_contains('Noto Sans Arabic', $aboutCss, 'about page type stack includes Noto Sans Arabic');
ar_assert_contains('Noto Sans Arabic', $participantsCss, 'participants page type stack includes Noto Sans Arabic');
ar_assert_contains('Noto Sans Arabic', $bottomBarCss, 'bottom bar type stack includes Noto Sans Arabic');

echo "\nAll arabic_typography tests passed.\n";
