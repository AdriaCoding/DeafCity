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

        /** @type {Array<{ videoId: string, tracks?: Array<{ file: string, label?: string }>}>} */
        var playlistItems = Array.isArray(cfg.playlist) && cfg.playlist.length > 0
            ? cfg.playlist
            : [];

        /** @type {{ events: unknown[] }[][]} */
        var vimeoTracksState = playlistItems.map(function (item) {
            var tl = Array.isArray(item.tracks) ? item.tracks : [];
            return tl.map(function () {
                return { events: [] };
            });
        });

        var playlistIndex = typeof cfg.playlistIndex === 'number' ? cfg.playlistIndex : 0;

        /** @typedef {{ file: string, label?: string }} VpcTrackDecl */
        /** @type {VpcTrackDecl[]} UI track layout from server (typically first playlist clip) */
        var uiTracks = Array.isArray(cfg.tracks) ? cfg.tracks : [];

        var activeCaptionTrackIndex = 0;

        /** @type {unknown} */
        var vimeoPlayer = null;

        /** @returns {VpcTrackDecl[]} */
        function currentItemUiTracksForPicker() {
            if (playlistIndex !== 0) return [];
            return uiTracks;
        }

        function currentItemCueTracksRaw() {
            var item = playlistItems[playlistIndex];
            return Array.isArray(item.tracks) ? item.tracks : [];
        }

        function loadStaticVtt(file, plIndex, cueTrackIndex, label) {
            var url =
                captionsEndpoint +
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
                    var tier = vimeoTracksState[plIndex];
                    if (tier && tier[cueTrackIndex]) {
                        tier[cueTrackIndex].events = res.data;
                    }
                    syncAllCaptions();
                })
                .catch(function (e) {
                    console.warn('Static VTT fetch failed (' + label + '):', e);
                });
        }

        playlistItems.forEach(function (item, pi) {
            var tr = Array.isArray(item.tracks) ? item.tracks : [];
            tr.forEach(function (t, ci) {
                if (t && t.file) {
                    loadStaticVtt(t.file, pi, ci, 'pl-' + pi + '-' + ci);
                }
            });
        });

        function updateCaptionPickerVisibility() {
            var picker = root.querySelector('.caption-lang-picker');
            if (!picker) return;
            picker.classList.toggle('vpc-caption-picker-hidden', currentItemUiTracksForPicker().length === 0);
        }

        /** @returns {unknown[]} */
        function eventsForSync() {
            var tier = vimeoTracksState[playlistIndex];
            var raw = currentItemCueTracksRaw();
            var ix = raw.length === 0 ? 0 : Math.min(activeCaptionTrackIndex, raw.length - 1);
            if (!tier || !tier[ix]) return [];
            return tier[ix].events || [];
        }

        function setActiveCaptionTrack(index) {
            var cueTracks = currentItemCueTracksRaw();
            if (cueTracks.length === 0) {
                activeCaptionTrackIndex = 0;
                root.querySelectorAll('.caption-lang-btn').forEach(function (btn) {
                    btn.setAttribute('aria-pressed', 'false');
                });
                syncCaptionBox(captionBoxId, [], 0);
                return;
            }
            if (index < 0 || index >= cueTracks.length) return;
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
            var events = /** @type {unknown[]} */ (eventsForSync());
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

            var playBtn = root.querySelector('.vpc-play-pause-btn');
            function setTransportPlaying(isPlaying) {
                if (!playBtn) return;
                if (isPlaying) {
                    playBtn.textContent = 'Pause';
                    playBtn.setAttribute('aria-label', 'Pause video');
                } else {
                    playBtn.textContent = 'Play';
                    playBtn.setAttribute('aria-label', 'Play video');
                }
            }

            function refreshTransport() {
                p.getPaused()
                    .then(function (paused) {
                        setTransportPlaying(!paused);
                    })
                    .catch(function () {});
            }

            function togglePlayPause() {
                p.getPaused()
                    .then(function (paused) {
                        if (paused) {
                            return p.play();
                        }
                        return p.pause();
                    })
                    .then(function () {
                        refreshTransport();
                    })
                    .catch(function () {
                        refreshTransport();
                    });
            }

            if (playBtn) {
                playBtn.addEventListener('click', togglePlayPause);
            }

            var hitArea = root.querySelector('.vpc-video-hitarea');
            if (hitArea) {
                hitArea.addEventListener('click', togglePlayPause);
            }

            function resetFromBeginning() {
                p.setCurrentTime(0)
                    .then(function () {
                        return p.play();
                    })
                    .then(function () {
                        syncVimeoCaptionBoxes(0);
                        refreshTransport();
                    })
                    .catch(function () {
                        refreshTransport();
                    });
            }

            var resetBtn = root.querySelector('.vpc-reset-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', resetFromBeginning);
            }

            function updatePlaylistNavButtons() {
                var prevBtn = root.querySelector('.vpc-prev-btn');
                var nextBtn = root.querySelector('.vpc-next-btn');
                if (!prevBtn || !nextBtn) return;
                prevBtn.disabled = playlistIndex <= 0;
                nextBtn.disabled = playlistIndex >= playlistItems.length - 1;
            }

            function goPlaylistIndex(nextIndex, autoPlayPreferred) {
                if (playlistItems.length <= 1) return Promise.resolve();
                if (nextIndex < 0 || nextIndex >= playlistItems.length) {
                    return Promise.resolve();
                }
                playlistIndex = nextIndex;

                /** @type {number} */
                var vidNum = parseInt(playlistItems[playlistIndex].videoId, 10);
                /** @type {Promise<void>} */
                var loadP =
                    typeof p.loadVideo === 'function'
                        ? p.loadVideo(vidNum).then(function () {})
                        : Promise.reject(new Error('Vimeo.Player.loadVideo unavailable'));

                return loadP
                    .then(function () {
                        updateCaptionPickerVisibility();
                        activeCaptionTrackIndex = 0;
                        setActiveCaptionTrack(0);
                        syncCaptionBox(captionBoxId, eventsForSync(), 0);
                        updatePlaylistNavButtons();
                        /** @type {Promise<void>} */
                        var autoplayP =
                            autoPlayPreferred === false ? Promise.resolve() : tryAutoplayFallback();
                        return autoplayP;
                    })
                    .catch(function (e) {
                        console.warn('Vimeo playlist: loadVideo failed', e);
                        refreshTransport();
                    });
            }

            var prevTransport = root.querySelector('.vpc-prev-btn');
            if (prevTransport) {
                prevTransport.addEventListener('click', function () {
                    goPlaylistIndex(playlistIndex - 1, true);
                });
            }
            var nextTransport = root.querySelector('.vpc-next-btn');
            if (nextTransport) {
                nextTransport.addEventListener('click', function () {
                    goPlaylistIndex(playlistIndex + 1, true);
                });
            }

            updateCaptionPickerVisibility();
            updatePlaylistNavButtons();

            p.on('play', function () {
                setTransportPlaying(true);
            });
            p.on('pause', function () {
                setTransportPlaying(false);
                p.getCurrentTime().then(syncVimeoCaptionBoxes);
            });
            p.on('ended', function () {
                setTransportPlaying(false);
            });

            p.on('timeupdate', function (data) {
                syncVimeoCaptionBoxes(data.seconds);
            });
            p.on('seeked', function () {
                p.getCurrentTime().then(syncVimeoCaptionBoxes);
            });

            function tryAutoplayFallback() {
                var readyPromise =
                    typeof p.ready === 'function' ? p.ready() : Promise.resolve();
                return readyPromise
                    .then(function () {
                        return p.getPaused().then(function (paused) {
                            if (paused) return p.play();
                        });
                    })
                    .catch(function () {})
                    .then(function () {
                        refreshTransport();
                        syncAllCaptions();
                    });
            }

            tryAutoplayFallback();
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
