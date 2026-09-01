<?php
// Run: php8.4 tests/site_base_test.php

$siteRoot = dirname(__DIR__);
require $siteRoot . '/lib/site_base.php';
require $siteRoot . '/lib/preview_locale.php';

function sb_assert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

sb_assert(preview_home_path() === '/', 'home path is /');
sb_assert(preview_route_path('about') === '/about', 'about path');
sb_assert(preview_route_path('participants') === '/participants', 'participants path');
sb_assert(preview_route_path('home') === '/', 'home route alias');
sb_assert(preview_captions_endpoint() === '/captions-static.php', 'captions endpoint at root');
sb_assert(preview_locale_api_path() === '/api/locale.php', 'locale API at root');
sb_assert(
    preview_participant_home_url('Hamida') === '/?participant=Hamida',
    'participant query on home'
);
sb_assert(preview_asset_base_path() === '', 'assets at document-root paths');
sb_assert(preview_page_url_meta('about') === '/about', 'page-url meta for about');
sb_assert(
    is_readable(preview_resolve_data_dir() . '/catalog.json'),
    'data dir resolves beside the Website root'
);

echo "\nAll site_base tests passed.\n";
