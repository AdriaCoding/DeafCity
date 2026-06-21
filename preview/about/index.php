<?php
$rootDir = dirname(dirname(dirname(__FILE__)));
require __DIR__ . '/../lib/gallery_images.php';

$galleryJsonPath = $rootDir . '/data/gallery.json';
$gallery_images = preview_load_gallery_images($galleryJsonPath);
$viewsDir = $rootDir . '/views';
$currentRoute = 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About — DEAF.city</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/preview/css/site-nav.css?v=1">
    <link rel="stylesheet" href="/preview/css/about-page.css?v=1">
</head>
<body>
<div class="preview-about-page">
    <?php
    $currentRoute = 'about';
    include __DIR__ . '/../components/site_nav.php';
    ?>

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
            <?php include $viewsDir . '/about/credits.php'; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="/leaflet/js/gallery.js"></script>
</body>
</html>
