<?php
require __DIR__ . '/lib/videos_catalog.php';
// Resolve data paths: use the worktree-local data dir when available,
// otherwise fall back to the canonical public_html data dir (for worktree environments).
$_dataDir = dirname(__DIR__) . '/data';
if (!is_readable($_dataDir . '/catalog.json')) {
    $_dataDir = '/srv/www/deaf.city/public_html/data';
}
$catalogJsonPath  = $_dataDir . '/catalog.json';
$studioConfigPath = $_dataDir . '/studio-config.json';
$catalog  = vpc_load_videos_catalog($catalogJsonPath);
// D2 + D12: shuffle server-side so item[0] is both the paused poster and the queue head.
// Every reload yields a fresh random order; no sign-language pre-filter (D7).
$playlist = $catalog ? vpc_shuffle_playlist(vpc_vimeo_playlist_all_from_catalog($catalog)) : [];
$signLanguageOptions = $catalog ? vpc_sign_language_options_from_catalog($catalog, $studioConfigPath) : [];
$editionOptions      = $catalog ? vpc_edition_options_from_catalog($catalog, $studioConfigPath) : [];
$typologyOptions     = $catalog ? vpc_typology_options_from_catalog($catalog, $studioConfigPath) : [];
$vpc = null;
if (count($playlist) > 0) {
    $vpc = [
        'instance_id'    => 'preview-playlist-demo',
        'playlist'       => $playlist,
        'site_nav_route' => 'home',
        // Tell JS the server already placed the chosen poster at index 0.
        'playlist_index' => 0,
    ];
    // R2 sign language picker: populated-from-present only (D17), no default (D7).
    if (count($signLanguageOptions) > 0) {
        $vpc['sign_language_filter'] = ['options' => $signLanguageOptions];
    }
    // R2 City/Edition picker: populated-from-present only (D17).
    if (count($editionOptions) > 0) {
        $vpc['edition_filter'] = ['options' => $editionOptions];
    }
    // R2 Typology picker: populated-from-present only (D17).
    if (count($typologyOptions) > 0) {
        $vpc['typology_filter'] = ['options' => $typologyOptions];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DEAF.city</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/preview/components/vimeo_caption_player.css?v=25">
    <link rel="stylesheet" href="/preview/css/site-nav.css?v=2">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
            background: #fff;
            font-family: Roboto, Arial, sans-serif;
        }
        .preview-block { height: 100%; }
    </style>
</head>
<body>

<div class="preview-block">
<?php if ($vpc !== null): ?>
    <?php require __DIR__ . '/components/vimeo_caption_player.php'; ?>
<?php else: ?>
    <p style="font-family:sans-serif;padding:1rem;color:#666;">
        No playlist loaded. Check that <code>data/catalog.json</code> exists.
    </p>
<?php endif; ?>
</div>

<script src="/preview/js/vimeo_playlist_logic.js?v=3"></script>
<script src="/preview/js/vimeo_caption_player.js?v=25" defer></script>
</body>
</html>
