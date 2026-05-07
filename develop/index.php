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
$vimeoVttBasename          = 'luis_02.es-MX.vtt';
$vimeoVttBasenameSecondary = 'luis_02.en.vtt';
// Vimeo outer captions from static WebVTT (captions-static.php).
$vimeoVideoId = '639494119';
$embedVimeo   = 'https://player.vimeo.com/video/' . rawurlencode($vimeoVideoId)
    . '?api=1&title=0&byline=0&portrait=0&dnt=1';

// YouTube (disabled). Change to `if (true):` and uncomment the JS block marked `YOUTUBE RESCUE` below.
if (false):
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
<?php endif; ?>

    <div class="develop-block">
        <div id="caption-box-vimeo" class="caption-box"></div>
        <div id="caption-box-vimeo-2" class="caption-box"></div>
        <div class="video-shell">
            <iframe
                id="vimeo-player"
                src="<?php echo htmlspecialchars($embedVimeo, ENT_QUOTES, 'UTF-8'); ?>"
                title="Vimeo"
                allow="autoplay; fullscreen; picture-in-picture"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen></iframe>
        </div>
    </div>

<script>
(function () {
    'use strict';

    var VIMEO_VTT_FILE = '<?php echo htmlspecialchars($vimeoVttBasename, ENT_QUOTES, 'UTF-8'); ?>';
    var VIMEO_VTT_FILE_2 = '<?php echo htmlspecialchars($vimeoVttBasenameSecondary, ENT_QUOTES, 'UTF-8'); ?>';
    var captionEventsVimeo = [];
    var captionEventsVimeo2 = [];
    var vimeoPlayer     = null;

    function loadStaticVtt(file, assign, label) {
        fetch('/develop/captions-static.php?f=' + encodeURIComponent(file))
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (res) {
                if (!res.ok || !Array.isArray(res.data)) {
                    console.warn('Static VTT failed (' + label + ')', res.data);
                    return;
                }
                assign(res.data);
                syncAllCaptions();
            })
            .catch(function (e) {
                console.warn('Static VTT fetch failed (' + label + '):', e);
            });
    }

    loadStaticVtt(VIMEO_VTT_FILE, function (data) { captionEventsVimeo = data; }, 'vimeo-primary');
    loadStaticVtt(VIMEO_VTT_FILE_2, function (data) { captionEventsVimeo2 = data; }, 'vimeo-secondary');

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

    function syncCaptionBox(boxId, events, timeMs) {
        var box = document.getElementById(boxId);
        if (!box) return;
        var caption = findCaption(events, timeMs);
        box.textContent = caption ? caption.text : '';
    }

    function syncVimeoCaptionBoxes(seconds) {
        var ms = seconds * 1000;
        syncCaptionBox('caption-box-vimeo', captionEventsVimeo, ms);
        syncCaptionBox('caption-box-vimeo-2', captionEventsVimeo2, ms);
    }

    function syncAllCaptions() {
        if (vimeoPlayer && typeof vimeoPlayer.getCurrentTime === 'function') {
            vimeoPlayer.getCurrentTime().then(syncVimeoCaptionBoxes).catch(function () { /* ignore */ });
        }
    }

    /*
     * ========== YOUTUBE RESCUE ================================================
     * Turn PHP `if (false):` → `if (true):` for the YouTube block, then remove this
     * comment wrapper so the following runs again (merge syncAllCaptions to drive both players).
     *
    var VIDEO_ID_MINIMAL = '38DSoHOO8u4';
    var captionEventsMinimal = [];
    var playerMinimal   = null;
    var syncInterval    = null;
    var lang = (navigator.language || 'en').split('-')[0];

    function loadCaptions(videoId, assign) {
        function tryLang(code, done) {
            fetch('/develop/captions.php?v=' + videoId + '&lang=' + encodeURIComponent(code))
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (res) {
                    if (res.ok && Array.isArray(res.data)) {
                        assign(res.data);
                        syncAllCaptions();
                    } else if (typeof done === 'function') {
                        done();
                    }
                })
                .catch(function (e) {
                    console.warn('Caption fetch failed for ' + videoId + ' (' + code + '):', e);
                    if (typeof done === 'function') {
                        done();
                    }
                });
        }
        tryLang(lang, function () {
            if (lang !== 'en') {
                tryLang('en', null);
            }
        });
    }
    loadCaptions(VIDEO_ID_MINIMAL, function (data) {
        captionEventsMinimal = data;
    });

    function syncCaptionPair(player, boxId, events) {
        if (!player || typeof player.getCurrentTime !== 'function') return;
        syncCaptionBox(boxId, events, player.getCurrentTime() * 1000);
    }

    function syncAllCaptions() {
        syncCaptionPair(playerMinimal, 'caption-box', captionEventsMinimal);
        if (vimeoPlayer && typeof vimeoPlayer.getCurrentTime === 'function') {
            vimeoPlayer.getCurrentTime().then(syncVimeoCaptionBoxes).catch(function () {});
        }
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

    function initYoutubePlayer() {
        playerMinimal = new YT.Player('yt-player', {
            events: {
                onReady: function () {
                    syncAllCaptions();
                    updateSyncRunning();
                },
                onStateChange: function () {
                    updateSyncRunning();
                }
            }
        });
    }

    if (window.YT && window.YT.Player) {
        initYoutubePlayer();
    } else {
        window.onYouTubeIframeAPIReady = function () {
            initYoutubePlayer();
        };
        var tag    = document.createElement('script');
        tag.src    = 'https://www.youtube.com/iframe_api';
        var first  = document.getElementsByTagName('script')[0];
        first.parentNode.insertBefore(tag, first);
    }
     * ===========================================================================
     */

    // ── Vimeo Player SDK + static WebVTT captions ──────────────────────────────

    function initVimeoPlayer() {
        var iframe = document.getElementById('vimeo-player');
        if (!iframe || !window.Vimeo || !window.Vimeo.Player) return;
        vimeoPlayer = new Vimeo.Player(iframe);
        vimeoPlayer.on('timeupdate', function (data) {
            syncVimeoCaptionBoxes(data.seconds);
        });
        vimeoPlayer.on('seeked', function () {
            vimeoPlayer.getCurrentTime().then(syncVimeoCaptionBoxes);
        });
        vimeoPlayer.on('pause', function () {
            vimeoPlayer.getCurrentTime().then(syncVimeoCaptionBoxes);
        });
        syncAllCaptions();
    }

    (function loadVimeoSdk() {
        var s = document.createElement('script');
        s.src = 'https://player.vimeo.com/api/player.js';
        s.onload = initVimeoPlayer;
        s.onerror = function () {
            console.warn('Vimeo Player SDK failed to load');
        };
        document.head.appendChild(s);
    }());
}());
</script>
</body>
</html>
