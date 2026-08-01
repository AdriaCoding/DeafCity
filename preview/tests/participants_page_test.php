<?php
// Run: php8.4 preview/tests/participants_page_test.php

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

require dirname(dirname(__FILE__)) . '/lib/preview_locale.php';
require dirname(dirname(__FILE__)) . '/lib/videos_catalog.php';

$catalogJsonPath = preview_resolve_data_dir() . '/catalog.json';
$catalog = vpc_load_videos_catalog($catalogJsonPath);
$expectedCardCount = count(vpc_participants_from_catalog($catalog ?: array()));

// ── Participants page renders ─────────────────────────────────────────────────
$participantsPage = dirname(dirname(__FILE__)) . '/participants/index.php';
if (!is_file($participantsPage)) {
    fwrite(STDERR, "FAIL: participants page missing at {$participantsPage}\n");
    exit(1);
}

$htmlA = '';
$htmlB = '';
for ($i = 0; $i < 2; $i++) {
    ob_start();
    include $participantsPage;
    $html = ob_get_clean();
    if ($i === 0) {
        $htmlA = $html;
    } else {
        $htmlB = $html;
    }
}
$html = $htmlA;

pp_assert_contains('participants-grid', $html, 'participants grid present');
pp_assert_not_contains('<h1', $html, 'no h1 heading above grid');
pp_assert_not_contains('participants-title', $html, 'no participants title element');
pp_assert_not_contains('go back to player', $html, 'no back-to-player link on participants page');
pp_assert_contains('vpc-bottom-bar--player', $html, 'unified player chrome on participants page');
pp_assert_not_contains('vpc-bottom-bar--nav', $html, 'no legacy nav-mode bottom bar');
pp_assert_contains('data-secondary-page="true"', $html, 'secondary page player chrome flag');
pp_assert_contains('vpc-control-transport-cluster', $html, 'transport cluster on participants page');
pp_assert_not_contains('vpc-shuffle-btn', $html, 'no shuffle button');
pp_assert_not_contains('href="/preview/" class="preview-site-nav__btn"', $html, 'no Reproductor/home nav link');
pp_assert_contains('/preview/participants', $html, 'navbar includes Participants route');
pp_assert_contains('aria-current="page"', $html, 'navbar marks current page');
pp_assert_contains('vpc-chrome-btn__label-full">Participants</span>', $html, 'Participants label in navbar');
pp_assert_contains('/preview/about', $html, 'navbar includes About route');
pp_assert_contains('data-picker="language"', $html, 'language picker on participants chrome');
pp_assert_contains('vpc-picker-dropdown', $html, 'language dropup on participants chrome');
pp_assert_contains('English</li>', $html, 'English option in language picker');
pp_assert_contains('data-thumb-urls', $html, 'participant cards carry thumb rotation data');
$participantsCss = file_get_contents(dirname(dirname(__FILE__)) . '/css/participants-page.css');
pp_assert_contains('object-fit: contain', $participantsCss, 'thumbnails use object-fit contain');
pp_assert_contains('overflow: hidden', $html, 'non-scrollable body on participants page');
pp_assert_contains('min-height: 0', $participantsCss, 'participants scroll area shrinks inside flex column');

preg_match_all('~/preview/\?participant=~', $html, $cardMatches);
$cardCount = count($cardMatches[0]);
if ($cardCount !== $expectedCardCount) {
    fwrite(STDERR, "FAIL: expected {$expectedCardCount} participant cards (deduped), got {$cardCount}\n");
    exit(1);
}
echo "PASS: {$expectedCardCount} deduped participant cards in grid\n";

preg_match_all('~data-participant="([^"]+)"~', $htmlA, $orderA);
preg_match_all('~data-participant="([^"]+)"~', $htmlB, $orderB);
if ($orderA[1] === $orderB[1]) {
    fwrite(STDERR, "FAIL: participant grid order should differ between consecutive reloads\n");
    exit(1);
}
echo "PASS: grid order shuffles between reloads\n";

pp_assert_contains('participant=Hamida', $html, 'Hamida card present');
pp_assert_contains('participant=Edinho', $html, 'Edinho card present');
pp_assert_contains('Hamida', $html, 'Hamida name label');

pp_assert_not_contains('r=pad', $html, 'no r=pad in participant thumbnail URLs');
pp_assert_contains('region=us', $html, 'other Vimeo query params preserved');

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

$multiVideoParticipant = 'Frank';
$multiVideos = vpc_participant_videos_from_catalog($catalog, $multiVideoParticipant);
if (count($multiVideos) < 2) {
    fwrite(STDERR, "FAIL: {$multiVideoParticipant} should have multiple videos for thumb alternation test\n");
    exit(1);
}
if (strpos($html, 'data-participant="' . $multiVideoParticipant . '"') === false) {
    fwrite(STDERR, "FAIL: {$multiVideoParticipant} card missing data-participant hook\n");
    exit(1);
}
echo "PASS: multi-video participant has thumb alternation hooks\n";

// ── Home page with ?participant=Hamida ────────────────────────────────────────
$_GET['participant'] = 'Hamida';
$homePage = dirname(dirname(__FILE__)) . '/index.php';
ob_start();
include $homePage;
$homeHtml = ob_get_clean();

if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtml, $m)) {
    fwrite(STDERR, "FAIL: no vpc-config in home page with ?participant=Hamida\n");
    exit(1);
}
$cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
if (!is_array($cfg)) {
    fwrite(STDERR, "FAIL: vpc-config JSON invalid with ?participant=Hamida\n");
    exit(1);
}

$playlistCount = count($cfg['playlist'] ?? []);
if ($playlistCount < 2) {
    fwrite(STDERR, "FAIL: Hamida playlist should have multiple videos, got {$playlistCount}\n");
    exit(1);
}
echo "PASS: ?participant=Hamida gives {$playlistCount}-video playlist\n";

if (($cfg['participantName'] ?? '') !== 'Hamida') {
    fwrite(STDERR, "FAIL: participantName in vpc-config should be 'Hamida', got: " . json_encode($cfg['participantName'] ?? null) . "\n");
    exit(1);
}
echo "PASS: participantName=Hamida in vpc-config\n";

$firstSeq = isset($cfg['playlist'][0]['participant_sequence'])
    ? (string) $cfg['playlist'][0]['participant_sequence']
    : '';
if ($firstSeq === '') {
    fwrite(STDERR, "FAIL: first Hamida playlist item should expose participant_sequence\n");
    exit(1);
}
echo "PASS: playlist items include participant_sequence ({$firstSeq})\n";

if (preg_match('~vpc-prev-btn[^>]*vpc-nav-hidden~', $homeHtml)) {
    fwrite(STDERR, "FAIL: prev button must not be hidden on multi-video participant playlist\n");
    exit(1);
}
if (preg_match('~vpc-next-btn[^>]*vpc-nav-hidden~', $homeHtml)) {
    fwrite(STDERR, "FAIL: next button must not be hidden on multi-video participant playlist\n");
    exit(1);
}
echo "PASS: prev/next visible on multi-video participant playlist\n";

if (preg_match('~class="vpc-prev-btn"[^>]*disabled~', $homeHtml)
    || preg_match('~disabled[^>]*class="vpc-prev-btn"~', $homeHtml)) {
    fwrite(STDERR, "FAIL: prev button should not be disabled on multi-video participant playlist\n");
    exit(1);
}
if (preg_match('~class="vpc-next-btn"[^>]*disabled~', $homeHtml)
    || preg_match('~disabled[^>]*class="vpc-next-btn"~', $homeHtml)) {
    fwrite(STDERR, "FAIL: next button should not be disabled on multi-video participant playlist\n");
    exit(1);
}
echo "PASS: prev/next enabled on multi-video participant playlist\n";

unset($_GET['participant']);
ob_start();
include $homePage;
$homeHtml2 = ob_get_clean();

if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtml2, $m2)) {
    fwrite(STDERR, "FAIL: no vpc-config in home page without participant\n");
    exit(1);
}
$cfg2 = json_decode(html_entity_decode($m2[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
$fullCatalog = vpc_vimeo_playlist_all_from_catalog($catalog);
$fullCatalogCount = count($fullCatalog);
$catalogPlaylistCount = count($cfg2['catalogPlaylist'] ?? []);
if ($catalogPlaylistCount !== $fullCatalogCount) {
    fwrite(STDERR, "FAIL: catalogPlaylist should have {$fullCatalogCount} videos, got {$catalogPlaylistCount}\n");
    exit(1);
}
echo "PASS: catalogPlaylist has {$fullCatalogCount} videos (no participant filter)\n";

// Issue #01: without a participant filter, `playlist` is the cold-entry base
// playlist — one video per city — not the full catalog.
$distinctEditions = [];
foreach ($fullCatalog as $entry) {
    if (!empty($entry['edition'])) {
        $distinctEditions[$entry['edition']] = true;
    }
}
$basePlaylistCount = count($cfg2['playlist'] ?? []);
if ($basePlaylistCount !== count($distinctEditions)) {
    fwrite(STDERR, "FAIL: base playlist should have " . count($distinctEditions) . " videos (one per city), got {$basePlaylistCount}\n");
    exit(1);
}
echo "PASS: base playlist has {$basePlaylistCount} videos, one per city (no participant filter)\n";

if (($cfg2['participantName'] ?? 'NOT_SET') !== '') {
    fwrite(STDERR, "FAIL: participantName should be '' when no ?participant, got: " . json_encode($cfg2['participantName'] ?? null) . "\n");
    exit(1);
}
echo "PASS: participantName empty when no ?participant param\n";

pp_assert_contains('/preview/participants', $homeHtml2, 'Participants nav button on home page');
pp_assert_contains('vpc-chrome-btn__label-full">Participants</span>', $homeHtml2, 'Participants button label text');

$_GET['participant'] = 'Frank';
ob_start();
include $homePage;
$homeHtml3 = ob_get_clean();
if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtml3, $m3)) {
    fwrite(STDERR, "FAIL: no vpc-config with ?participant=Frank\n");
    exit(1);
}
$cfg3 = json_decode(html_entity_decode($m3[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
$expectedMultiCount = count(vpc_participant_videos_from_catalog($catalog, 'Frank'));
$multiCount = count($cfg3['playlist'] ?? []);
if ($multiCount !== $expectedMultiCount) {
    fwrite(STDERR, "FAIL: Frank playlist should have {$expectedMultiCount} videos, got {$multiCount}\n");
    exit(1);
}
echo "PASS: ?participant=Frank gives {$expectedMultiCount}-video playlist\n";
unset($_GET['participant']);

$_GET['participant'] = 'DefinitelyNotARealParticipant_XYZ';
ob_start();
include $homePage;
$homeHtmlUnknown = ob_get_clean();
unset($_GET['participant']);
if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $homeHtmlUnknown, $mUnk)) {
    fwrite(STDERR, "FAIL: no vpc-config with unknown ?participant\n");
    exit(1);
}
$cfgUnk = json_decode(html_entity_decode($mUnk[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
if (($cfgUnk['participantName'] ?? '') !== 'DefinitelyNotARealParticipant_XYZ') {
    fwrite(STDERR, "FAIL: unknown participant must still set participantName, got: " . json_encode($cfgUnk['participantName'] ?? null) . "\n");
    exit(1);
}
echo "PASS: unknown ?participant still enters Participant mode (participantName set)\n";

echo "\nAll participants_page tests passed.\n";
