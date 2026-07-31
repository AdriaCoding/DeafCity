<?php

unset($_GET['participant']);
ob_start();
include dirname(dirname(__FILE__)) . '/index.php';
$html = ob_get_clean();

function maa_assert_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

function maa_assert_not_contains($needle, $haystack, $label) {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

maa_assert_contains('autoplay=0', $html, 'cold load remains paused');
if (strpos($html, 'muted=1') !== false) {
    fwrite(STDERR, "FAIL: cold load must not request muted playback\n");
    exit(1);
}
if (strpos($html, 'vpc-mute-btn') !== false) {
    fwrite(STDERR, "FAIL: cold load must not expose a mute control\n");
    exit(1);
}

$playerJs = file_get_contents(dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js');
maa_assert_not_contains('setMuted', $playerJs, 'player must not control a mute state');
maa_assert_not_contains('vpc-sound-enabled', $playerJs, 'player must not persist a sound preference');

echo "PASS: intent-gated playback has no muted autoplay or mute control\n";
