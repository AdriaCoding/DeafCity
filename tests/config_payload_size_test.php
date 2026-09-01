<?php
// Run: php8.4 tests/config_payload_size_test.php
//
// The home page renders the whole visible catalog inline in the
// <script type="application/json" class="vpc-config"> block (catalogPlaylist:
// every catalog Video, for client-side filtering — see index.php and
// components/vimeo_caption_player.php). There is no server-side paging or
// lazy fetch for it, so it grows with the catalog and nobody would notice a
// regression (e.g. a new field added to every playlist item) without a guard.
//
// Measured 2026-09-01 against the real data/catalog.json (243 Videos):
//   - inline vpc-config JSON:        ~285,237 bytes (~285 KB) of a ~317 KB page
//   - catalogPlaylist alone:         ~255,267 bytes (~255 KB), ~1,050 B/item avg
//   - gzipped on the wire: ~37 KB — NOT urgent today, but unguarded.
//
// This is not a hard cap on catalog growth — the catalog is expected to grow. The
// ceilings below are set at roughly 1.5x today's measured size, real headroom for
// ordinary catalog growth, so this only trips on a genuine step change (e.g. a new
// per-item field, or an accidental full re-embed of something already elsewhere).
//
// If this test trips: do NOT just raise the numbers. The fix is a lighter
// projection (drop fields the client doesn't need for filtering/cold-boot) or a
// lazy fetch endpoint for catalogPlaylist, not embedding more inline.

function cps_assert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$previewDir = dirname(dirname(__FILE__));

$_GET = array();
ob_start();
include $previewDir . '/index.php';
$html = ob_get_clean();

if (!preg_match('~<script[^>]+class="vpc-config"[^>]*>(.*?)</script>~s', $html, $m)) {
    fwrite(STDERR, "FAIL: no vpc-config script block found on the home page\n");
    exit(1);
}
$configJsonRaw = $m[1];
$configBytes = strlen($configJsonRaw);

// Ceiling: ~1.5x the ~285,237 bytes measured above.
$CONFIG_JSON_CEILING_BYTES = 430000;
cps_assert(
    $configBytes <= $CONFIG_JSON_CEILING_BYTES,
    "inline vpc-config JSON stays under {$CONFIG_JSON_CEILING_BYTES} bytes (measured: {$configBytes})"
);
echo "  (measured {$configBytes} bytes; ~1.5x headroom over the ~285,237 bytes measured 2026-09-01)\n";

$cfg = json_decode(html_entity_decode($configJsonRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
cps_assert(is_array($cfg), 'vpc-config JSON parses');
cps_assert(isset($cfg['catalogPlaylist']) && is_array($cfg['catalogPlaylist']), 'catalogPlaylist present');

$catalogPlaylist = $cfg['catalogPlaylist'];
$itemCount = count($catalogPlaylist);
cps_assert($itemCount > 0, 'catalogPlaylist is non-empty (test fixture sanity)');

$catalogPlaylistBytes = strlen(json_encode($catalogPlaylist));
// Ceiling: ~1.5x the ~255,267 bytes measured above (catalogPlaylist dominates the
// whole config payload — this isolates growth there from growth elsewhere in cfg).
$CATALOG_PLAYLIST_CEILING_BYTES = 385000;
cps_assert(
    $catalogPlaylistBytes <= $CATALOG_PLAYLIST_CEILING_BYTES,
    "catalogPlaylist JSON stays under {$CATALOG_PLAYLIST_CEILING_BYTES} bytes (measured: {$catalogPlaylistBytes} across {$itemCount} items)"
);

// Per-item shape guard: catch a silently-added large new field even if the catalog
// itself hasn't grown much in item count. This is an allow-list — a genuinely new
// field must be a deliberate, reviewed addition to this list, not a silent add.
$expectedKeys = array(
    'videoId', 'tracks', 'signLanguage', 'edition', 'typology', 'participant',
    'participant_sequence', 'tags', 'embedUrl', 'thumbnailUrl',
);
$seenKeys = array();
foreach ($catalogPlaylist as $item) {
    foreach (array_keys($item) as $k) {
        $seenKeys[$k] = true;
    }
}
$seenKeys = array_keys($seenKeys);
sort($seenKeys);
$expectedSorted = $expectedKeys;
sort($expectedSorted);
$unexpected = array_diff($seenKeys, $expectedKeys);
if (!empty($unexpected)) {
    fwrite(STDERR, "FAIL: catalogPlaylist items carry unexpected field(s) not in the allow-list: " . implode(', ', $unexpected) . "\n");
    fwrite(STDERR, "      If this is a deliberate addition, update \$expectedKeys in this test and re-check the size ceilings above.\n");
    exit(1);
}
echo "PASS: catalogPlaylist items carry only known fields (" . implode(', ', $seenKeys) . ")\n";

// Average per-item size, as a second, item-count-independent signal (guards against
// a per-item field ballooning even if the catalog's item count stays flat).
$avgItemBytes = $itemCount > 0 ? $catalogPlaylistBytes / $itemCount : 0;
$AVG_ITEM_CEILING_BYTES = 1600; // ~1.5x the ~1,050 B/item average measured 2026-09-01
cps_assert(
    $avgItemBytes <= $AVG_ITEM_CEILING_BYTES,
    "average catalogPlaylist item size stays under {$AVG_ITEM_CEILING_BYTES} bytes (measured: " . round($avgItemBytes, 1) . ")"
);

echo "\nAll config_payload_size tests passed.\n";
