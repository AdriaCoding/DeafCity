<?php
/**
 * Issue #02 — playback sessionStorage + secondary transport handoff
 *
 * Run: php preview/tests/transport_session_test.php
 */

function ts_assert_contains($needle, $haystack, $label)
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$logicJs = file_get_contents(dirname(dirname(__FILE__)) . '/js/vimeo_playlist_logic.js');
$playerJs = file_get_contents(dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js');
$secondaryJs = file_get_contents(dirname(dirname(__FILE__)) . '/js/secondary_player_chrome.js');

ts_assert_contains("PLAYBACK_SESSION_KEY = 'vpc-playback-session'", $logicJs, 'playback session key exported');
ts_assert_contains("NAV_INTENT_KEY = 'vpc-nav-intent'", $logicJs, 'nav intent key exported');
ts_assert_contains('planSecondaryNavRestore', $logicJs, 'secondary nav restore planner');
ts_assert_contains('buildPlaybackSessionSnapshot', $logicJs, 'session snapshot builder');

ts_assert_contains('planSecondaryNavRestore', $playerJs, 'player reads nav restore plan');
ts_assert_contains('savePlaybackSession', $playerJs, 'player persists playback session');
ts_assert_contains('__vpcSavePlaybackSession', $playerJs, 'session save exposed for lang handoff');
ts_assert_contains('sessionStorage.removeItem(L.NAV_INTENT_KEY)', $playerJs, 'nav intent cleared after read');

ts_assert_contains("sessionStorage.setItem('vpc-nav-intent'", $secondaryJs, 'secondary chrome writes nav intent');
ts_assert_contains("sessionStorage.removeItem('vpc-playback-session')", $secondaryJs, 'reset clears playback session');
ts_assert_contains("params.push('lang='", $secondaryJs, 'lang query preserved on navigation');

if (strpos($secondaryJs, "match[1] !== 'en'") !== false) {
    fwrite(STDERR, "FAIL: secondary chrome still skips lang=en\n");
    exit(1);
}
echo "PASS: secondary chrome includes lang=en in navigation\n";

echo "\nAll transport_session tests passed.\n";
