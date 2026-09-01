<?php
// Run: php8.4 tests/asset_version_test.php
//
// Every page that loads a preview JS/CSS asset must derive its cache-busting
// version from the same shared helper (filemtime() of the actual file at render
// time), so a shared asset (e.g. vimeo_playlist_logic.js, vimeo_caption_player.css)
// never gets a different ?v= on one page than another — the drift that let a
// returning visitor pair a stale cached script with fresh markup.

require_once dirname(__DIR__) . '/lib/asset_version.php';

function av_assert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$previewDir = dirname(dirname(__FILE__));

// ── The helper itself: version tracks the real file's mtime ───────────────────
$url = preview_asset_url('js/vimeo_playlist_logic.js');
av_assert(strpos($url, '/js/vimeo_playlist_logic.js?v=') === 0, 'asset URL has the expected path + query shape');
preg_match('~\?v=(\d+)$~', $url, $m);
av_assert(isset($m[1]), 'version is numeric');
$expectedMtime = (string) filemtime($previewDir . '/js/vimeo_playlist_logic.js');
av_assert($m[1] === $expectedMtime, 'version equals the real file\'s filemtime()');

// A leading slash on the input is tolerated the same way.
av_assert(
    preview_asset_url('/js/vimeo_playlist_logic.js') === $url,
    'leading slash on the relative path does not change the result'
);

// ── Every page renders every shared asset through the same helper ─────────────
function av_extract_version($html, $file)
{
    if (!preg_match('~' . preg_quote($file, '~') . '\?v=(\d+)~', $html, $m)) {
        return null;
    }
    return $m[1];
}

$_GET = array();
ob_start();
include $previewDir . '/index.php';
$homeHtml = ob_get_clean();

ob_start();
include $previewDir . '/about/index.php';
$aboutHtml = ob_get_clean();

ob_start();
include $previewDir . '/participants/index.php';
$participantsHtml = ob_get_clean();

foreach (array('vimeo_playlist_logic.js', 'vimeo_caption_player.css', 'bottom-bar.css') as $sharedFile) {
    $vHome = av_extract_version($homeHtml, $sharedFile);
    $vAbout = av_extract_version($aboutHtml, $sharedFile);
    $vParticipants = av_extract_version($participantsHtml, $sharedFile);
    av_assert($vHome !== null, "home page loads {$sharedFile} with a version");
    av_assert($vHome === $vAbout, "{$sharedFile}: home and about agree on version ({$vHome} vs {$vAbout})");
    av_assert($vHome === $vParticipants, "{$sharedFile}: home and participants agree on version ({$vHome} vs {$vParticipants})");
}

// No page should still have a hand-rolled literal version number for a preview asset.
foreach (array('home' => $homeHtml, 'about' => $aboutHtml, 'participants' => $participantsHtml) as $pageName => $html) {
    if (preg_match('~/(?:js|css|components)/[a-zA-Z0-9_.\-]+\.(?:js|css)\?v=(?!\d)~', $html)) {
        fwrite(STDERR, "FAIL: {$pageName} page has a non-numeric preview asset version (not going through preview_asset_url())\n");
        exit(1);
    }
}
echo "PASS: no page hand-rolls a non-numeric preview asset version\n";

echo "\nAll asset_version tests passed.\n";
