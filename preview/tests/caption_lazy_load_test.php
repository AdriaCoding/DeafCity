<?php
/**
 * Preview player loads caption files for the current Video only.
 *
 * Run: php8.4 preview/tests/caption_lazy_load_test.php
 */

function cll_assert($cond, $label)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$jsPath = dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js';
$js = file_get_contents($jsPath);
cll_assert($js !== false && $js !== '', 'player JS readable');

$bulkPrefetch =
    preg_match(
        '/fullPlaylistItems\.forEach\(function\s*\(\s*item\s*,\s*pi\s*\)\s*\{[\s\S]*?loadStaticVtt/',
        $js
    ) === 1;
cll_assert(
    !$bulkPrefetch,
    'player does not prefetch every catalog caption file on init'
);

cll_assert(
    strpos($js, 'planCaptionFetchesForMasterIndex') !== false,
    'player asks playlist logic which caption files the current Video needs'
);

if (!preg_match('/function loadVideoMaster\([\s\S]*?\n            \}/', $js, $loadVideoMaster)) {
    fwrite(STDERR, "FAIL: could not locate loadVideoMaster\n");
    exit(1);
}
cll_assert(
    strpos($loadVideoMaster[0], 'ensureCaptionsForMasterIndex(playlistIndex)') !== false,
    'switching Video loads that Video caption files'
);
cll_assert(
    strpos($loadVideoMaster[0], 'syncCaptionBox(eventsForSync(), 0)') !== false,
    'video switch still paints title cue at t=0 (no stale overlay time)'
);

echo "\nAll caption_lazy_load tests passed.\n";
