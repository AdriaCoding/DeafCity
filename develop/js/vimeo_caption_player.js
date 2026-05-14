(function () {
    'use strict';

    function ensureVimeoSdk(onReady) {
        if (window.Vimeo && window.Vimeo.Player) {
            onReady();
            return;
        }
        window.__vpcVimeoSdkCallbacks = window.__vpcVimeoSdkCallbacks || [];
        window.__vpcVimeoSdkCallbacks.push(onReady);

        if (window.__vpcVimeoSdkLoading) return;
        window.__vpcVimeoSdkLoading = true;

        var s = document.createElement('script');
        s.src = 'https://player.vimeo.com/api/player.js';
        s.onload = function () {
            window.__vpcVimeoSdkLoading = false;
            var pending = window.__vpcVimeoSdkCallbacks || [];
            window.__vpcVimeoSdkCallbacks = [];
            pending.forEach(function (cb) {
                try {
                    cb();
                } catch (e) {}
            });
        };
        s.onerror = function () {
            window.__vpcVimeoSdkLoading = false;
            window.__vpcVimeoSdkCallbacks = [];
            console.warn('Vimeo Player SDK failed to load');
        };
        document.head.appendChild(s);
    }

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

    /**
     * @param {HTMLElement} root
     */
    function initOne(root) {
        if (root.getAttribute('data-vpc-initialized') === '1') {
            return;
        }
        root.setAttribute('data-vpc-initialized', '1');

        var cfgEl = root.querySelector('script.vpc-config[type="application/json"]');
        if (!cfgEl || !cfgEl.textContent) return;

        var cfg;
        try {
            cfg = JSON.parse(cfgEl.textContent.trim());
        } catch (e) {
            console.warn('Vimeo caption player: invalid config JSON', e);
            return;
        }

        var iframeId = cfg.iframeId;
        var captionBoxId = cfg.captionBoxId;
        var captionsEndpoint =
            cfg.captionsEndpoint && typeof cfg.captionsEndpoint === 'string'
                ? cfg.captionsEndpoint
                : '/develop/captions-static.php';
        /** @type {Array<{file: string}>} */
        var tracks = Array.isArray(cfg.tracks) ? cfg.tracks : [];

        /** @type {Array<{ events: unknown[] }>} */
        var vimeoTracksState = tracks.map(function () {
            return { events: [] };
        });

        var activeCaptionTrackIndex = 0;

        /** @type {unknown} */
        var vimeoPlayer = null;

        function loadStaticVtt(file, trackIndex, label) {
            var url = captionsEndpoint +
                (captionsEndpoint.indexOf('?') >= 0 ? '&' : '?') +
                'f=' +
                encodeURIComponent(file);
            fetch(url)
                .then(function (r) {
                    return r.json().then(function (data) {
                        return { ok: r.ok, data: data };
                    });
                })
                .then(function (res) {
                    if (!res.ok || !Array.isArray(res.data)) {
                        console.warn('Static VTT failed (' + label + ')', res.data);
                        return;
                    }
                    if (vimeoTracksState[trackIndex]) {
                        vimeoTracksState[trackIndex].events = res.data;
                    }
                    syncAllCaptions();
                })
                .catch(function (e) {
                    console.warn('Static VTT fetch failed (' + label + '):', e);
                });
        }

        tracks.forEach(function (t, i) {
            if (t && t.file) {
                loadStaticVtt(t.file, i, 'vimeo-' + i);
            }
        });

        function setActiveCaptionTrack(index) {
            if (index < 0 || index >= tracks.length) return;
            activeCaptionTrackIndex = index;
            root.querySelectorAll('.caption-lang-btn').forEach(function (btn) {
                var idx = parseInt(btn.getAttribute('data-track-index'), 10);
                btn.setAttribute('aria-pressed', idx === index ? 'true' : 'false');
            });
            syncAllCaptions();
        }

        root.querySelectorAll('.caption-lang-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-track-index'), 10);
                setActiveCaptionTrack(idx);
            });
        });

        function syncVimeoCaptionBoxes(seconds) {
            var ms = seconds * 1000;
            var state = vimeoTracksState[activeCaptionTrackIndex];
            var events = state ? state.events : [];
            syncCaptionBox(captionBoxId, events, ms);
        }

        function syncAllCaptions() {
            if (
                vimeoPlayer &&
                typeof vimeoPlayer === 'object' &&
                typeof vimeoPlayer.getCurrentTime === 'function'
            ) {
                vimeoPlayer
                    .getCurrentTime()
                    .then(syncVimeoCaptionBoxes)
                    .catch(function () {});
            }
        }

        function attachPlayer() {
            var iframe = document.getElementById(iframeId);
            if (!iframe || !window.Vimeo || !window.Vimeo.Player) return;
            vimeoPlayer = new window.Vimeo.Player(iframe);

            /** @type {any} */
            var p = vimeoPlayer;
            p.on('timeupdate', function (data) {
                syncVimeoCaptionBoxes(data.seconds);
            });
            p.on('seeked', function () {
                p.getCurrentTime().then(syncVimeoCaptionBoxes);
            });
            p.on('pause', function () {
                p.getCurrentTime().then(syncVimeoCaptionBoxes);
            });
            syncAllCaptions();
        }

        ensureVimeoSdk(attachPlayer);
    }

    function boot() {
        document.querySelectorAll('.develop-vimeo-player-root').forEach(initOne);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

