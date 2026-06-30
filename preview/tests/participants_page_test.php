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
pp_assert_not_contains('go back to player', $html, 'no back-to-player link on participants page');
pp_assert_contains('vpc-bottom-bar--nav', $html, 'sticky bottom bar on participants page');
pp_assert_contains('href="/preview/participants"', $html, 'navbar includes Participants route');
pp_assert_contains('aria-current="page"', $html, 'navbar marks current page');
pp_assert_contains('>Participants</a>', $html, 'Participants label in navbar');
pp_assert_contains('href="/preview/"', $html, 'navbar includes Player route');
pp_assert_contains('href="/preview/about"', $html, 'navbar includes About route');

pp_assert_contains('data-picker="language"', $html, 'language picker on participants navbar');
pp_assert_contains('vpc-picker-dropdown', $html, 'language dropup on participants navbar');
pp_assert_contains('English</li>', $html, 'English option in language picker');

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

pp_assert_not_contains('r=pad', $html, 'no r=pad in participant thumbnail URLs');
pp_assert_contains('region=us', $html, 'other Vimeo query params preserved');

require dirname(dirname(__FILE__)) . '/lib/videos_catalog.php';

$padUrl = 'https://i.vimeocdn.com/video/1285032917-abc_200x150?&r=pad&region=us';
$displayUrl = vpc_participant_thumbnail_display_url($padUrl);
if ($displayUrl !== 'https://i.vimeocdn.com/video/1285032917-abc_200x150?region=us') {
    fwrite(STDERR, "FAIL: unexpected display URL: {$displayUrl}\n");
    exit(1);
}
if (vpc_participant_thumbnail_display_url('https://example.com/thumb.jpg?r=pad') !== 'https://example.com/thumb.jpg?r=pad') {
    fwrite(STDERR, "FAIL: non-Vimeo URL should pass through unchanged\n");
    exit(1);
}
if (vpc_participant_thumbnail_display_url('https://i.vimeocdn.com/video/x_640x360?&r=crop&region=us')
    !== 'https://i.vimeocdn.com/video/x_640x360?&r=crop&region=us') {
    fwrite(STDERR, "FAIL: r=crop URL should pass through unchanged\n");
    exit(1);
}
echo "PASS: vpc_participant_thumbnail_display_url strips r=pad only\n";

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
