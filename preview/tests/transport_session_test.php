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

ts_assert_contains('explicitParticipantName', $logicJs, 'explicit participant bypasses session restore');
ts_assert_contains('explicitParticipantName: participantName', $playerJs, 'player passes URL participant to restore planner');
ts_assert_contains('savePlaybackSession', $playerJs, 'player persists playback session');
ts_assert_contains('__vpcSavePlaybackSession', $playerJs, 'session save exposed for lang handoff');
ts_assert_contains('sessionStorage.removeItem(L.NAV_INTENT_KEY)', $playerJs, 'nav intent cleared after read');

ts_assert_contains("sessionStorage.setItem('vpc-nav-intent'", $secondaryJs, 'secondary chrome writes nav intent');
ts_assert_contains("sessionStorage.removeItem('vpc-playback-session')", $secondaryJs, 'reset clears playback session');
ts_assert_contains('syncParticipantsNavFromSession', $secondaryJs, 'secondary chrome syncs participants nav from session');
ts_assert_contains('participantSequence', $secondaryJs, 'secondary chrome reads participant sequence from session');
ts_assert_contains('resolveParticipantsNavState', $secondaryJs, 'secondary chrome uses participants nav state');
ts_assert_contains("bind(deafBtn, 'deaf-hearing')", $secondaryJs, 'secondary DEAF+HEARING sets deaf-hearing intent');
ts_assert_contains('syncDeafHearingFromSession', $secondaryJs, 'secondary chrome mirrors DEAF+HEARING from session');
ts_assert_contains("navIntent === 'deaf-hearing'", $logicJs, 'planner handles deaf-hearing force-ON');
ts_assert_contains('kind === \'deaf-hearing\'', $playerJs, 'player consumes deaf-hearing restore kind');
ts_assert_contains('resolveTagToggleOnFilterState', $playerJs, 'player applies DH15b tag toggle resolve');
ts_assert_contains('toggleDeafHearingFilter', $playerJs, 'player wires DEAF+HEARING toggle');

if (strpos($secondaryJs, "match[1] !== 'en'") !== false) {
    fwrite(STDERR, "FAIL: secondary chrome still skips lang=en\n");
    exit(1);
}
echo "PASS: secondary chrome includes lang=en in navigation\n";

echo "\nAll transport_session tests passed.\n";
