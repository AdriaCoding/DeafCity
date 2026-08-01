<?php
// Run: php8.4 preview/tests/base_city_playlist_test.php
// Issue #01: base playlist — one random participant per city.

require_once dirname(__DIR__) . '/lib/videos_catalog.php';

function assert_true($cond, $label)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

// ── Pool construction on a synthetic fixture ─────────────────────────────────

$fixture = array(
    array('video_id' => '1', 'edition' => 'bcn', 'participant' => 'Amaia'),
    array('video_id' => '2', 'edition' => 'bcn', 'participant' => 'Amaia'),
    array('video_id' => '3', 'edition' => 'bcn', 'participant' => 'Toni'),
    array('video_id' => '4', 'edition' => 'alg', 'participant' => 'Hamida'),
    array('video_id' => '5', 'edition' => 'roma', 'participant' => 'Serena'),
    array('video_id' => '6', 'edition' => '', 'participant' => 'NoCity'), // no edition — excluded
);

$pool = vpc_base_city_playlist_pool($fixture);
assert_true(count($pool) === 3, 'one entry per distinct city (bcn, alg, roma)');

$editionsSeen = array();
foreach ($pool as $entry) {
    $editionsSeen[$entry['edition']] = true;
}
assert_true(count($editionsSeen) === 3, 'each pool entry is from a distinct city');
assert_true(!isset($editionsSeen['']), 'entries without an edition are excluded from any city bucket');

// ── Randomness: bcn has 2 participants with unequal video counts (Amaia x2, Toni x1) ──
// A fair per-participant pick should surface Toni too, not just proportionally-more-likely Amaia.

$seenParticipants = array();
for ($i = 0; $i < 60; $i++) {
    $p = vpc_base_city_playlist_pool($fixture);
    foreach ($p as $entry) {
        if ($entry['edition'] === 'bcn') {
            $seenParticipants[$entry['participant']] = true;
        }
    }
}
assert_true(count($seenParticipants) === 2, 'both bcn participants appear over repeated picks (per-participant fairness)');

// ── Integration: vpc_catalog_collection() builds the reduced pool for cold entry ────

$dataDir = dirname(dirname(dirname(__FILE__))) . '/data';
if (!is_file($dataDir . '/catalog.json')) {
    $dataDir = '/srv/www/deaf.city/public_html/data';
}
$catalogJsonPath = $dataDir . '/catalog.json';
$studioConfigPath = $dataDir . '/studio-config.json';

if (is_file($catalogJsonPath)) {
    $catalog = vpc_load_videos_catalog($catalogJsonPath);
    if ($catalog) {
        $collection = vpc_catalog_collection($catalog, $studioConfigPath);
        $fullCatalogCount = count($collection['catalog_playlist']);
        $basePlaylistCount = count($collection['playlist']);

        $distinctEditions = array();
        foreach ($collection['catalog_playlist'] as $entry) {
            if (!empty($entry['edition'])) {
                $distinctEditions[$entry['edition']] = true;
            }
        }

        assert_true($basePlaylistCount === count($distinctEditions), 'cold-entry playlist has exactly one video per city');
        assert_true($basePlaylistCount < $fullCatalogCount, 'cold-entry playlist is smaller than the full catalog');
        assert_true($fullCatalogCount > 0, 'catalog_playlist still carries the full catalog for client-side filtering');
    } else {
        echo "SKIP: integration check skipped — catalog could not be loaded\n";
    }
} else {
    echo "SKIP: integration check skipped — catalog.json not found at test runtime\n";
}

echo "base_city_playlist_test.php: all passed\n";
