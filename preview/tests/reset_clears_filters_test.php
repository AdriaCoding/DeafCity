<?php
/**
 * Issue #13 — Reset clears filters & collections (D1′)
 *
 * Run: php preview/tests/reset_clears_filters_test.php
 */

function rcf_assert_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function rcf_assert_not_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$homePage = dirname(dirname(__FILE__)) . '/index.php';
$jsPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
$logicPath = dirname(dirname(__FILE__)) . '/js/vimeo_playlist_logic.js';
$cssPath = dirname(dirname(__FILE__)) . '/css/site-nav.css';

unset($_GET['participant']);
ob_start();
include $homePage;
$html = ob_get_clean();

// Reset no longer advertises restart-from-beginning behaviour
rcf_assert_not_contains('Restart video from the beginning', $html, 'no restart aria-label on reset button');
rcf_assert_contains('aria-label="Reset filters and playlist"', $html, 'reset button has neutral-reset aria-label');
rcf_assert_contains('vpc-reset-btn', $html, 'reset button present in chrome');
if (!preg_match('~vpc-control-secondary-r[^>]*>.*?vpc-reset-btn~s', $html)) {
    fwrite(STDERR, "FAIL: reset button should be in right secondary wing\n");
    exit(1);
}
echo "PASS: reset button in right secondary wing\n";

$js = file_get_contents($jsPath);
rcf_assert_not_contains('resetFromBeginning', $js, 'old resetFromBeginning handler removed');
rcf_assert_contains('resetToNeutralAll', $js, 'resetToNeutralAll handler wired');
rcf_assert_not_contains('setCurrentTime(0)', $js, 'reset does not seek current video to t=0');
rcf_assert_contains('planResetToNeutralAll', $js, 'reset uses shared playlist logic plan');

$logic = file_get_contents($logicPath);
rcf_assert_contains('function planResetToNeutralAll', $logic, 'planResetToNeutralAll exported from logic module');
rcf_assert_contains('shouldAutoplay: false', $logic, 'reset plan forces pause');

$css = file_get_contents($cssPath);
rcf_assert_contains('.preview-site-nav__btn.is-active', $css, 'participant active (green) nav styling present');

echo "\nAll reset_clears_filters tests passed.\n";
