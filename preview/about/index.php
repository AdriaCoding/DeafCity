<?php
$rootDir = dirname(dirname(dirname(__FILE__)));
require __DIR__ . '/../lib/gallery_images.php';
require __DIR__ . '/../lib/preview_locale.php';

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
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($preview_lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($preview_dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(preview_t('about.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/preview/css/site-nav.css?v=6">
    <link rel="stylesheet" href="/preview/css/about-page.css?v=8">
    <style>
        html[dir="rtl"] .preview-about-page { direction: rtl; text-align: right; }
        html[dir="rtl"] .preview-site-nav { direction: rtl; }
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

    <div id="city-map-section">
        <?php include $viewsDir . '/about/map.php'; ?>
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

<?php
$currentRoute = 'about';
$navPlacement = 'navbar';
include __DIR__ . '/../components/site_nav.php';
?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="/leaflet/js/gallery.js"></script>
<script src="/js/d3.v7.min.js"></script>
<script src="/js/topojson-client.min.js"></script>
<script src="/leaflet/js/about-map.js?v=13"></script>
</body>
</html>
