<?php
$rootDir = dirname(dirname(dirname(__FILE__)));
require __DIR__ . '/../lib/videos_catalog.php';

// Resolve data paths
$_dataDir = $rootDir . '/data';
if (!is_readable($_dataDir . '/catalog.json')) {
    $_dataDir = '/srv/www/deaf.city/public_html/data';
}
$catalogJsonPath = $_dataDir . '/catalog.json';
$catalog = vpc_load_videos_catalog($catalogJsonPath);
$participants = $catalog ? vpc_participants_from_catalog($catalog) : array();

// Sort participants alphabetically by name
ksort($participants);

$currentRoute = 'participants';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Participants — DEAF.city</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <meta name="page-url" content="/preview/participants">
    <link rel="stylesheet" href="/preview/css/site-nav.css?v=3">
    <link rel="stylesheet" href="/preview/css/participants-page.css?v=9">
</head>
<body>
<div class="preview-participants-page">
    <h1 class="participants-title">Participants</h1>

    <div class="participants-grid">
        <?php foreach ($participants as $name => $video): ?>
            <?php
            $thumbnailUrl = vpc_participant_thumbnail_display_url(
                isset($video['thumbnail_url']) ? (string) $video['thumbnail_url'] : ''
            );
            $encodedName = rawurlencode($name);
            $safeHref = '/preview/?participant=' . $encodedName;
            ?>
            <a href="<?= htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') ?>" class="participant-card">
                <?php if ($thumbnailUrl !== ''): ?>
                    <span class="participant-thumb">
                        <img src="<?= htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                    </span>
                <?php endif; ?>
                <span class="participant-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
$currentRoute = 'participants';
$navPlacement = 'navbar';
include __DIR__ . '/../components/site_nav.php';
?>

<script>
(function () {
    'use strict';
    var KEY = 'vpc-gesture-activated';
    var cards = document.querySelectorAll('.participant-card');
    for (var i = 0; i < cards.length; i++) {
        cards[i].addEventListener('click', function () {
            try {
                sessionStorage.setItem(KEY, '1');
            } catch (e) {}
        });
    }
}());
</script>
</body>
</html>
