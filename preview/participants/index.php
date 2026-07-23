<?php
$rootDir = dirname(dirname(dirname(__FILE__)));
require __DIR__ . '/../lib/videos_catalog.php';
require __DIR__ . '/../lib/preview_locale.php';
require __DIR__ . '/../lib/bottom_bar_player_config.php';

$locale = preview_bootstrap_locale();
$preview_i18n = $locale['i18n'];
$preview_lang = $locale['lang'];
$preview_dir = $locale['dir'];

$_dataDir = preview_resolve_data_dir();
$catalogJsonPath = $_dataDir . '/catalog.json';
$catalog = vpc_load_videos_catalog($catalogJsonPath);
$participants = $catalog ? vpc_participants_from_catalog($catalog) : array();

$participantNames = array_keys($participants);
shuffle($participantNames);

$currentRoute = 'participants';
$bottomBar = preview_build_bottom_bar_player_config('participants', $preview_lang, 'participants');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($preview_lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= htmlspecialchars($preview_dir, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(preview_t('participants.page_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <meta name="page-url" content="/preview/participants">
    <link rel="stylesheet" href="/preview/components/vimeo_caption_player.css?v=65">
    <link rel="stylesheet" href="/preview/css/bottom-bar.css?v=7">
    <link rel="stylesheet" href="/preview/css/participants-page.css?v=12">
    <style>
        html, body { height: 100%; margin: 0; overflow: hidden; }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100%;
            font-family: Roboto, Arial, sans-serif;
        }
        html[dir="rtl"] .preview-participants-page { direction: rtl; }
    </style>
</head>
<body>
<div class="preview-participants-page">
    <div class="participants-grid">
        <?php foreach ($participantNames as $name): ?>
            <?php
            $videos = vpc_participant_videos_from_catalog($catalog, $name);
            if (count($videos) === 0) {
                continue;
            }
            $defaultVideo = $videos[0];
            $thumbnailUrl = vpc_participant_thumbnail_display_url(
                isset($defaultVideo['thumbnail_url']) ? (string) $defaultVideo['thumbnail_url'] : ''
            );
            $thumbUrls = array();
            foreach ($videos as $video) {
                $url = vpc_participant_thumbnail_display_url(
                    isset($video['thumbnail_url']) ? (string) $video['thumbnail_url'] : ''
                );
                if ($url !== '') {
                    $thumbUrls[] = $url;
                }
            }
            $encodedName = rawurlencode($name);
            $safeHref = '/preview/?participant=' . $encodedName;
            if ($preview_lang !== 'en') {
                $safeHref .= '&lang=' . rawurlencode($preview_lang);
            }
            ?>
            <a
                href="<?= htmlspecialchars($safeHref, ENT_QUOTES, 'UTF-8') ?>"
                class="participant-card"
                data-participant="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                data-thumb-urls="<?= htmlspecialchars(json_encode($thumbUrls), ENT_QUOTES, 'UTF-8') ?>"
            >
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

<?php include __DIR__ . '/../components/bottom_bar.php'; ?>

<script src="/preview/js/vimeo_playlist_logic.js?v=17"></script>
<script src="/preview/js/secondary_player_chrome.js?v=4"></script>
<script>
(function () {
    'use strict';
    var GESTURE_KEY = 'vpc-gesture-activated';
    var THUMB_KEY_PREFIX = 'vpc-participant-thumb-';

    function parseThumbUrls(card) {
        var raw = card.getAttribute('data-thumb-urls') || '[]';
        try {
            var urls = JSON.parse(raw);
            return Array.isArray(urls) ? urls : [];
        } catch (e) {
            return [];
        }
    }

    function pickThumbIndex(participant, count) {
        if (count <= 1) {
            return 0;
        }
        var storageKey = THUMB_KEY_PREFIX + participant;
        var visit = 0;
        try {
            visit = parseInt(sessionStorage.getItem(storageKey) || '0', 10);
            if (isNaN(visit) || visit < 0) {
                visit = 0;
            }
            sessionStorage.setItem(storageKey, String(visit + 1));
        } catch (e) {}
        return visit % count;
    }

    var cards = document.querySelectorAll('.participant-card');
    for (var i = 0; i < cards.length; i++) {
        (function (card) {
            var participant = card.getAttribute('data-participant') || '';
            var urls = parseThumbUrls(card);
            if (urls.length > 1) {
                var idx = pickThumbIndex(participant, urls.length);
                var img = card.querySelector('.participant-thumb img');
                if (img && urls[idx]) {
                    img.src = urls[idx];
                }
            }
            card.addEventListener('click', function () {
                try {
                    sessionStorage.setItem(GESTURE_KEY, '1');
                } catch (e) {}
            });
        })(cards[i]);
    }
}());
</script>
</body>
</html>
