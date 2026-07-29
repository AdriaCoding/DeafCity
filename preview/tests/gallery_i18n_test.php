<?php
// Run: php8.4 preview/tests/gallery_i18n_test.php

require dirname(dirname(__FILE__)) . '/lib/gallery_images.php';
require dirname(dirname(__FILE__)) . '/lib/preview_locale.php';

function gi_assert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ca';
$_GET['lang'] = 'en';
$locale = preview_bootstrap_locale();
$preview_i18n = $locale['i18n'];
$preview_lang = $locale['lang'];

gi_assert($preview_lang === 'en', 'explicit ?lang=en wins over Accept-Language');

$galleryJsonPath = dirname(dirname(dirname(__FILE__))) . '/data/gallery.json';
$gallery_images = preview_load_gallery_images($galleryJsonPath);

ob_start();
include dirname(dirname(dirname(__FILE__))) . '/views/_gallery.php';
$html = ob_get_clean();

gi_assert(strpos($html, 'Double-sided display of 10 Sign Languages') !== false, 'gallery caption renders via preview_t');
gi_assert(strpos($html, 'gallery.caption') === false, 'gallery markup does not expose raw i18n keys');

echo "\nAll gallery_i18n tests passed.\n";
