<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Develop</title>
    <style>
        html, body {
            margin: 0;
            min-height: 100%;
            background: #fff;
        }
        .video-shell {
            max-width: min(100vw, 1280px);
            margin: 0 auto;
            aspect-ratio: 16 / 9;
            background: #fff;
        }
        .video-shell iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            vertical-align: top;
        }
        .develop-block {
            margin-bottom: 2rem;
        }
        .caption-box {
            max-width: min(100vw, 1280px);
            margin: 0 auto;
            min-height: 4.25em;
            padding: 0.75em 1rem;
            box-sizing: border-box;
            background: #fff;
            color: #007800;
            font-family: sans-serif;
            font-size: clamp(1.5rem, 3.8vw, 2.35rem);
            line-height: 1.45;
            text-align: center;
        }
        .caption-box:empty::after {
            content: '\00a0';
        }
    </style>
</head>
<body>
<?php
$videoIdMinimal       = '38DSoHOO8u4';
$base           = [
    'rel'            => '0',
    'iv_load_policy' => '3',
    'playsinline'    => '1',
    'color'          => 'white',
    'enablejsapi'    => '1',
    'origin'         => 'https://deaf.city',
];
$paramsMinimal = http_build_query(array_merge($base, [
    'controls' => '0',
    'fs'       => '0',
]));
$embedMinimal  = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoIdMinimal) . '?' . $paramsMinimal;
?>
    <div class="develop-block">
        <div id="caption-box" class="caption-box"></div>
        <div class="video-shell">
            <iframe
                id="yt-player"
                src="<?php echo htmlspecialchars($embedMinimal, ENT_QUOTES, 'UTF-8'); ?>"
                title="Video (minimal chrome)"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen></iframe>
        </div>
    </div>

<script>
(function () {
    'use strict';

    var VIDEO_ID_MINIMAL = '<?php echo $videoIdMinimal; ?>';
    var captionEventsMinimal = [];
    var playerMinimal   = null;
    var syncInterval    = null;

    // ── Fetch captions ────────────────────────────────────────────────────────

    var lang = (navigator.language || 'en').split('-')[0];

    function loadCaptions(videoId, assign) {
        fetch('/develop/captions.php?v=' + videoId + '&lang=' + encodeURIComponent(lang))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (Array.isArray(data)) {
                    assign(data);
                }
            })
            .catch(function (e) { console.warn('Caption fetch failed for ' + videoId + ':', e); });
    }

    loadCaptions(VIDEO_ID_MINIMAL, function (data) { captionEventsMinimal = data; });

    // ── Caption sync ──────────────────────────────────────────────────────────

    function findCaption(events, timeMs) {
        var lo = 0, hi = events.length - 1, result = -1;
        while (lo <= hi) {
            var mid = (lo + hi) >> 1;
            if (events[mid].start <= timeMs) {
                result = mid;
                lo = mid + 1;
            } else {
                hi = mid - 1;
            }
        }
        if (result >= 0 && events[result].end >= timeMs) {
            return events[result];
        }
        return null;
    }

    function syncCaptionPair(player, boxId, events) {
        if (!player || typeof player.getCurrentTime !== 'function') return;
        var box = document.getElementById(boxId);
        if (!box) return;
        var timeMs  = player.getCurrentTime() * 1000;
        var caption = findCaption(events, timeMs);
        box.textContent = caption ? caption.text : '';
    }

    function syncAllCaptions() {
        syncCaptionPair(playerMinimal, 'caption-box', captionEventsMinimal);
    }

    function isActivePlayback(player) {
        if (!player || typeof player.getPlayerState !== 'function') return false;
        var s = player.getPlayerState();
        return s === YT.PlayerState.PLAYING || s === YT.PlayerState.BUFFERING;
    }

    function updateSyncRunning() {
        if (isActivePlayback(playerMinimal)) {
            startSync();
        } else {
            stopSync();
            syncAllCaptions();
        }
    }

    function startSync() {
        if (syncInterval) return;
        syncInterval = setInterval(syncAllCaptions, 150);
    }

    function stopSync() {
        clearInterval(syncInterval);
        syncInterval = null;
    }

    // ── IFrame Player API ─────────────────────────────────────────────────────

    window.onYouTubeIframeAPIReady = function () {
        playerMinimal = new YT.Player('yt-player', {
            events: {
                onStateChange: function () {
                    updateSyncRunning();
                }
            }
        });
    };

    var tag    = document.createElement('script');
    tag.src    = 'https://www.youtube.com/iframe_api';
    var first  = document.getElementsByTagName('script')[0];
    first.parentNode.insertBefore(tag, first);
}());
</script>
</body>
</html>
