<?php
// Run: php8.4 preview/tests/gesture_gated_audio_test.php

function gga_assert_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function gga_assert_not_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$homePage = dirname(dirname(__FILE__)) . '/index.php';
$participantsPage = dirname(dirname(__FILE__)) . '/participants/index.php';

// ── Cold load: muted autoplay, with an explicit unmute gesture ───────────────
unset($_GET['participant']);
ob_start();
include $homePage;
$coldHtml = ob_get_clean();

gga_assert_contains('autoplay=1', $coldHtml, 'cold load iframe requests autoplay');
gga_assert_contains('muted=1', $coldHtml, 'cold load iframe requests muted playback');
gga_assert_contains('vpc-mute-btn', $coldHtml, 'cold load exposes mute control');

if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $coldHtml, $m)) {
    fwrite(STDERR, "FAIL: no vpc-config on cold load\n");
    exit(1);
}
$coldCfg = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
if (!is_array($coldCfg)) {
    fwrite(STDERR, "FAIL: vpc-config JSON invalid on cold load\n");
    exit(1);
}
if (!empty($coldCfg['gesturePreActivated'])) {
    fwrite(STDERR, "FAIL: gesturePreActivated should be absent/false on cold load\n");
    exit(1);
}
echo "PASS: gesturePreActivated not set on cold load\n";

// ── Participant page sets gesture carry flag on card click (D23) ─────────────
ob_start();
include $participantsPage;
$participantsHtml = ob_get_clean();

gga_assert_contains('vpc-gesture-activated', $participantsHtml, 'participants page references gesture storage key');
gga_assert_contains('participant-card', $participantsHtml, 'participant cards present for gesture wiring');
gga_assert_contains('sessionStorage.setItem', $participantsHtml, 'participants page sets sessionStorage on click');

// ── Home with ?participant= but no prior gesture: not pre-activated ──────────
$_GET['participant'] = 'Hamida';
ob_start();
include $homePage;
$participantColdHtml = ob_get_clean();

if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $participantColdHtml, $m2)) {
    fwrite(STDERR, "FAIL: no vpc-config with ?participant=Hamida\n");
    exit(1);
}
$participantCfg = json_decode(html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
if (($participantCfg['participantName'] ?? '') !== 'Hamida') {
    fwrite(STDERR, "FAIL: participantName should be Hamida\n");
    exit(1);
}
if (!empty($participantCfg['gesturePreActivated'])) {
    fwrite(STDERR, "FAIL: gesturePreActivated must not be set server-side (JS reads sessionStorage)\n");
    exit(1);
}
echo "PASS: participant home load does not server-pre-activate gesture\n";

unset($_GET['participant']);

// ── Playlist logic module exports gesture helpers ───────────────────────────
$logicPath = dirname(dirname(__FILE__)) . '/js/vimeo_playlist_logic.js';
if (!is_file($logicPath)) {
    fwrite(STDERR, "FAIL: vimeo_playlist_logic.js missing\n");
    exit(1);
}
$logicSrc = file_get_contents($logicPath);
gga_assert_contains('GESTURE_STORAGE_KEY', $logicSrc, 'logic exports gesture storage key');
gga_assert_contains('shouldAutoplayWithSound', $logicSrc, 'logic exports shouldAutoplayWithSound');
gga_assert_contains('resolveParticipantGestureCarry', $logicSrc, 'logic exports resolveParticipantGestureCarry');

// ── Player JS uses gesture helpers (no autoplay before activation) ───────────
$playerPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
$playerSrc = file_get_contents($playerPath);
gga_assert_contains('markGestureActivation', $playerSrc, 'player defines markGestureActivation');
gga_assert_contains('shouldAutoplayWithSound', $playerSrc, 'player uses shouldAutoplayWithSound');
gga_assert_contains('resolveParticipantGestureCarry', $playerSrc, 'player uses participant gesture carry');
gga_assert_contains('GESTURE_STORAGE_KEY', $playerSrc, 'player references gesture storage key');

echo "\nAll gesture_gated_audio tests passed.\n";
