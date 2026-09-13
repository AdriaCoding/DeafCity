<?php
// Run: php8.4 tests/studio_catalog_thumbs_test.php

function sct_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$src = file_get_contents(dirname(dirname(__FILE__)) . '/studio/views/continguts.php');
sct_assert(is_string($src) && $src !== '', 'continguts.php is readable');

sct_assert(
    strpos($src, 'vimeo_playlist_logic.js') !== false,
    'Studio catalog loads the shared thumbnail ladder helper'
);

if (!preg_match('/function injectVideoCard[\s\S]*?function setupModalConfigAdd/s', $src, $m)) {
    fwrite(STDERR, "FAIL: could not locate injectVideoCard in continguts.php\n");
    exit(1);
}
$inject = $m[0];

sct_assert(
    strpos($inject, 'ladderPosterAttrs') !== false,
    'injectVideoCard builds srcset from the thumbnail ladder'
);
sct_assert(
    strpos($inject, "'grid'") !== false || strpos($inject, '"grid"') !== false,
    'injectVideoCard uses the grid ladder (640 src)'
);
sct_assert(
    strpos($inject, 'escHtml(video.thumbnail_url)') === false,
    'injectVideoCard does not write a naked thumbnail_url as src'
);

echo "PASS: Studio catalog injects cards with the same thumbnail ladder as PHP cards\n";

if (!preg_match('/function showVimeoPreview[\s\S]*?function scheduleResolve/s', $src, $previewMatch)) {
    fwrite(STDERR, "FAIL: could not locate add-video preview helpers in continguts.php\n");
    exit(1);
}
$preview = $previewMatch[0];

sct_assert(
    strpos($preview, 'ladderPosterAttrs') !== false,
    'add-video preview builds the thumbnail from the shared ladder'
);
sct_assert(
    strpos($preview, "'grid'") !== false || strpos($preview, '"grid"') !== false,
    'add-video preview uses the grid ladder (640 src)'
);
sct_assert(
    strpos($preview, 'thumbnail_base') !== false,
    'add-video preview prefers thumbnail_base over a stored size URL'
);

echo "PASS: Studio add-video preview uses the 640 ladder rung\n";
