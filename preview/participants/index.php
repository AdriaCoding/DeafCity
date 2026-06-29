<?php
$rootDir = dirname(dirname(dirname(__FILE__)));
require __DIR__ . '/../lib/videos_catalog.php';
require __DIR__ . '/../lib/preview_locale.php';

$locale = preview_bootstrap_locale();
$preview_i18n = $locale['i18n'];
$preview_lang = $locale['lang'];
$preview_dir = $locale['dir'];

$_dataDir = preview_resolve_data_dir();
$catalogJsonPath = $_dataDir . '/catalog.json';
$catalog = vpc_load_videos_catalog($catalogJsonPath);
$participants = $catalog ? vpc_participants_from_catalog($catalog) : array();

// Sort participants alphabetically by name
ksort($participants);

$currentRoute = 'participants';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($preview_lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($preview_dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(preview_t('participants.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <meta name="page-url" content="/preview/participants">
    <link rel="stylesheet" href="/preview/css/site-nav.css?v=6">
    <link rel="stylesheet" href="/preview/css/participants-page.css?v=10">
    <style>
        html[dir="rtl"] .preview-participants-page { direction: rtl; }
        html[dir="rtl"] .preview-site-nav { direction: rtl; }
    </style>
</head>
<body>
<div class="preview-participants-page">
    <h1 class="participants-title"><?= htmlspecialchars(preview_t('participants.heading'), ENT_QUOTES, 'UTF-8') ?></h1>

    <div class="participants-grid">
        <?php foreach ($participants as $name => $video): ?>
            <?php
            $thumbnailUrl = vpc_participant_thumbnail_display_url(
                isset($video['thumbnail_url']) ? (string) $video['thumbnail_url'] : ''
            );
            $encodedName = rawurlencode($name);
            $safeHref = '/preview/?participant=' . $encodedName;
            if ($preview_lang !== 'en') {
                $safeHref .= '&lang=' . rawurlencode($preview_lang);
            }
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
