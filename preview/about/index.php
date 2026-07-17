<?php
$rootDir = dirname(dirname(dirname(__FILE__)));
require __DIR__ . '/../lib/gallery_images.php';
require __DIR__ . '/../lib/preview_locale.php';
require __DIR__ . '/../lib/bottom_bar_player_config.php';

$locale = preview_bootstrap_locale();
$preview_i18n = $locale['i18n'];
$preview_lang = $locale['lang'];
$preview_dir = $locale['dir'];

$galleryJsonPath = $rootDir . '/data/gallery.json';
if (!is_readable($galleryJsonPath)) {
    $galleryJsonPath = preview_resolve_data_dir() . '/gallery.json';
}
$gallery_images = preview_load_gallery_images($galleryJsonPath);
$viewsDir = $rootDir . '/views';
$currentRoute = 'about';

$bottomBar = preview_build_bottom_bar_player_config('about', $preview_lang, 'about');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($preview_lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($preview_dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(preview_t('about.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="page-url" content="/preview/about">
    <link rel="stylesheet" href="/preview/components/vimeo_caption_player.css?v=58">
    <link rel="stylesheet" href="/preview/css/bottom-bar.css?v=6">
    <link rel="stylesheet" href="/preview/css/about-page.css?v=13">
    <style>
        html, body { height: 100%; margin: 0; overflow: hidden; }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            font-family: Roboto, Arial, sans-serif;
        }
        html[dir="rtl"] .preview-about-page { direction: rtl; text-align: right; }
    </style>
</head>
<body>
<div class="preview-about-page">
    <div id="clock">
        <div id="clock-proper" class="clock">
            <iframe src="/realtime/index.html" title="DEAF.city clock"></iframe>
        </div>
        <div id="gallery" class="gallery">
            <?php include $viewsDir . '/_gallery.php'; ?>
        </div>
    </div>

    <div id="about" class="about">
        <div id="about-todo">
            <?php include $viewsDir . '/about/todo.php'; ?>
        </div>
    </div>

    <div id="trio">
        <?php include $viewsDir . '/about/trio.php'; ?>
    </div>

    <div id="credits" class="about">
        <div id="credits-bottom">
            <?php include $viewsDir . '/about/credits_i18n.php'; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/bottom_bar.php'; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="/leaflet/js/gallery.js"></script>
<script src="/preview/js/vimeo_playlist_logic.js?v=12"></script>
<script src="/preview/js/secondary_player_chrome.js?v=3"></script>
</body>
</html>
