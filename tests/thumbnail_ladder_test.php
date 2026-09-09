<?php
// Run: php8.4 tests/thumbnail_ladder_test.php

require dirname(dirname(__FILE__)) . '/lib/videos_catalog.php';

function tl_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$legacy = 'https://i.vimeocdn.com/video/2181836247-51bcf06afc8ce81cb92ee7b15a062677f3aa6b149e3f0ab1101f4e8035857e67-d_960x540?&r=pad&region=us';

$attrs = vpc_thumbnail_attrs($legacy, 'grid');

tl_assert(is_array($attrs) && isset($attrs['src'], $attrs['srcset'], $attrs['sizes']), 'grid attrs include src, srcset, and sizes');
tl_assert(strpos($attrs['src'], '_640x360') !== false, 'grid src uses the 640 rung');
tl_assert(strpos($attrs['src'], 'r=pad') === false, 'grid src strips r=pad');
tl_assert(strpos($attrs['srcset'], '640w') !== false, 'srcset includes 640w');
tl_assert(strpos($attrs['srcset'], '1920w') !== false, 'srcset includes 1920w');
tl_assert(strpos($attrs['srcset'], '3840w') !== false, 'srcset includes 3840w');
tl_assert(strpos($attrs['srcset'], '_960x') === false, 'srcset does not keep the legacy 960 rung');

echo "PASS: legacy 960 URL yields a 640/1920/3840 grid ladder without r=pad\n";

$player = vpc_thumbnail_attrs($legacy, 'player');
tl_assert(isset($player['src']) && strpos($player['src'], '_1920x1080') !== false, 'player src uses the 1920 rung');
tl_assert(strpos($player['srcset'], '3840w') !== false, 'player srcset still includes 3840w');

echo "PASS: player attrs fall back to 1920 and keep the full ladder\n";

tl_assert(vpc_thumbnail_attrs('', 'grid') === array(), 'empty URL emits no attrs');
tl_assert(vpc_thumbnail_attrs('https://example.com/thumb.jpg', 'grid') === array(), 'non-Vimeo URL emits no attrs');

echo "PASS: missing or non-Vimeo thumbnails emit no ladder attrs\n";

$base = vpc_thumbnail_base($legacy);
tl_assert($base !== '', 'legacy URL yields a thumbnail base');
tl_assert(strpos($base, '_960x540') === false, 'base has no size suffix');
tl_assert(strpos($base, 'r=pad') === false, 'base has no r=pad');
$fromBase = vpc_thumbnail_attrs($base, 'grid');
tl_assert($fromBase['src'] === $attrs['src'], 'base and legacy URL produce the same grid src');

echo "PASS: thumbnail base derived from a legacy URL rebuilds the same ladder\n";
