<?php
// Run: php preview/tests/participants_page_test.php

function pp_assert_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label} — expected to contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
function pp_assert_not_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label} — should not contain: {$needle}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

// ── Participants page renders ─────────────────────────────────────────────────
$participantsPage = dirname(dirname(__FILE__)) . '/participants/index.php';
if (!is_file($participantsPage)) {
    fwrite(STDERR, "FAIL: participants page missing at {$participantsPage}\n");
    exit(1);
}
ob_start();
include $participantsPage;
$html = ob_get_clean();

pp_assert_contains('participants-grid', $html, 'participants grid present');
pp_assert_contains('go back to player', $html, 'back-to-player link present');
pp_assert_contains('/preview/participants', $html, 'self-referential URL not present... wait');
// The nav should NOT show Participants when on participants page
pp_assert_not_contains('href="/preview/participants"', $html, 'no Participants nav button on participants page');

// Count distinct participant cards
preg_match_all('~/preview/\?participant=~', $html, $cardMatches);
$cardCount = count($cardMatches[0]);
if ($cardCount !== 22) {
    fwrite(STDERR, "FAIL: expected 22 participant cards, got {$cardCount}\n");
    exit(1);
}
echo "PASS: 22 participant cards in grid\n";

// Check a known participant is present
pp_assert_contains('participant=Hamida', $html, 'Hamida card present');
pp_assert_contains('participant=Edinho', $html, 'Edinho card present');
pp_assert_contains('Hamida', $html, 'Hamida name label');

// ── Home page with ?participant=Hamida ────────────────────────────────────────
$_GET['participant'] = 'Hamida';
$homePage = dirname(dirname(__FILE__)) . '/index.php';
ob_start();
include $homePage;
$homeHtml = ob_get_clean();

// Extract vpc-config JSON
if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtml, $m)) {
    fwrite(STDERR, "FAIL: no vpc-config in home page with ?participant=Hamida\n");
    exit(1);
}
$cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
if (!is_array($cfg)) {
    fwrite(STDERR, "FAIL: vpc-config JSON invalid with ?participant=Hamida\n");
    exit(1);
}

// Hamida has exactly 1 video
$playlistCount = count($cfg['playlist'] ?? []);
if ($playlistCount !== 1) {
    fwrite(STDERR, "FAIL: Hamida playlist should have 1 video, got {$playlistCount}\n");
    exit(1);
}
echo "PASS: ?participant=Hamida gives 1-video playlist\n";

// participantName in config
if (($cfg['participantName'] ?? '') !== 'Hamida') {
    fwrite(STDERR, "FAIL: participantName in vpc-config should be 'Hamida', got: " . json_encode($cfg['participantName'] ?? null) . "\n");
    exit(1);
}
echo "PASS: participantName=Hamida in vpc-config\n";

// ── Home page without ?participant shows full catalog playlist ─────────────────
unset($_GET['participant']);
ob_start();
include $homePage;
$homeHtml2 = ob_get_clean();

if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtml2, $m2)) {
    fwrite(STDERR, "FAIL: no vpc-config in home page without participant\n");
    exit(1);
}
$cfg2 = json_decode(html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
$fullCount = count($cfg2['playlist'] ?? []);
if ($fullCount !== 24) {
    fwrite(STDERR, "FAIL: full catalog playlist should have 24 videos, got {$fullCount}\n");
    exit(1);
}
echo "PASS: full catalog playlist has 24 videos (no participant filter)\n";

// participantName empty when no ?participant
if (($cfg2['participantName'] ?? 'NOT_SET') !== '') {
    fwrite(STDERR, "FAIL: participantName should be '' when no ?participant, got: " . json_encode($cfg2['participantName'] ?? null) . "\n");
    exit(1);
}
echo "PASS: participantName empty when no ?participant param\n";

// ── Participants button in R3 nav on home page ─────────────────────────────────
pp_assert_contains('href="/preview/participants"', $homeHtml2, 'Participants nav button on home page');
pp_assert_contains('>Participants<', $homeHtml2, 'Participants button label text');

// ── Edinho has 2 videos (playlist should have 2) ─────────────────────────────
$_GET['participant'] = 'Edinho';
ob_start();
include $homePage;
$homeHtml3 = ob_get_clean();
if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtml3, $m3)) {
    fwrite(STDERR, "FAIL: no vpc-config with ?participant=Edinho\n");
    exit(1);
}
$cfg3 = json_decode(html_entity_decode($m3[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
$edinhoCount = count($cfg3['playlist'] ?? []);
if ($edinhoCount !== 2) {
    fwrite(STDERR, "FAIL: Edinho playlist should have 2 videos, got {$edinhoCount}\n");
    exit(1);
}
echo "PASS: ?participant=Edinho gives 2-video playlist\n";
unset($_GET['participant']);

echo "\nAll participants_page tests passed.\n";
