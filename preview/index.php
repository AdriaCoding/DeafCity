<?php
require __DIR__ . '/lib/videos_catalog.php';
$catalogJsonPath  = dirname(__DIR__) . '/data/catalog.json';
$studioConfigPath = dirname(__DIR__) . '/data/studio-config.json';
$catalog  = vpc_load_videos_catalog($catalogJsonPath);
$playlist = $catalog ? vpc_vimeo_playlist_all_from_catalog($catalog) : [];
$signLanguageOptions = $catalog ? vpc_sign_language_options_from_catalog($catalog, $studioConfigPath) : [];
$vpc = null;
if (count($playlist) > 0) {
    $vpc = [
        'instance_id' => 'preview-playlist-demo',
        'playlist' => $playlist,
        'site_nav_route' => 'home',
    ];
    if (count($signLanguageOptions) > 0) {
        $vpc['sign_language_filter'] = ['options' => $signLanguageOptions, 'default' => ''];
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
    <link rel="stylesheet" href="/preview/components/vimeo_caption_player.css?v=24">
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
<script src="/preview/js/vimeo_caption_player.js?v=24" defer></script>
</body>
</html>
