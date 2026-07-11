<?php
// Run: php preview/tests/gallery_images_test.php

require dirname(dirname(__FILE__)) . '/lib/gallery_images.php';

function assert_true($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$missing = preview_load_gallery_images('/tmp/preview-gallery-missing-' . getmypid() . '.json');
assert_true($missing === array(), 'missing file returns empty array');

$fixture = sys_get_temp_dir() . '/preview-gallery-fixture-' . getmypid() . '.json';
file_put_contents($fixture, json_encode(array(
    array('image' => '01.avif'),
)));

$images = preview_load_gallery_images($fixture);
assert_true(count($images) === 1, 'loads one image');
assert_true($images[0]['image'] === '/gallery/01.avif', 'prefixes gallery path');
assert_true(!isset($images[0]['caption']), 'gallery.json no longer carries captions');

unlink($fixture);

echo "All tests passed.\n";
