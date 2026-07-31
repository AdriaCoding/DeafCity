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

maa_assert_contains('autoplay=1', $html, 'cold load requests autoplay');
maa_assert_contains('muted=1', $html, 'cold load requests muted playback');
maa_assert_contains('vpc-mute-btn', $html, 'cold load exposes mute control');
maa_assert_contains('aria-label="Unmute video"', $html, 'mute control has initial accessible state');

$playerJs = file_get_contents(dirname(dirname(__FILE__)) . '/js/vimeo_caption_player.js');
maa_assert_contains('sessionSoundOn', $playerJs, 'player tracks session sound preference');
maa_assert_contains('setMuted', $playerJs, 'player controls Vimeo mute state');

echo "PASS: muted autoplay and unmute affordance\n";
