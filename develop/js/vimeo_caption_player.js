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

        /** Master playlist (never reordered — sign-language filter chooses a subset). */
        /** @type {Array<{ videoId: string, tracks?: Array<{ file: string, label?: string }>, signLanguage?: string}>} */
        var fullPlaylistItems =
            Array.isArray(cfg.playlist) && cfg.playlist.length > 0 ? cfg.playlist : [];

        var signLangFilter = cfg.signLanguageFilter;
        /** @type {boolean} */
        var captionPickerDynamic = !!cfg.captionPickerDynamic && !!signLangFilter;

        var selectedSignLang = '';
        if (signLangFilter && signLangFilter.default) {
            selectedSignLang = String(signLangFilter.default);
        } else if (signLangFilter && Array.isArray(signLangFilter.options) && signLangFilter.options[0]) {
            selectedSignLang = String(signLangFilter.options[0].value || '');
        }

        /** @type {number[]} */
        var filteredMasterIndices = [];

        /** Index into filteredMasterIndices — which video inside the filtered list is playing. */
        var filteredCursor = 0;

        /** Absolute index into fullPlaylistItems (master). */
        var playlistIndex =
            typeof cfg.playlistIndex === 'number' ? cfg.playlistIndex : 0;

        /** @type {{ events: unknown[] }[][]} */
        var vimeoTracksState = fullPlaylistItems.map(function (item) {
            var tl = Array.isArray(item.tracks) ? item.tracks : [];
            return tl.map(function () {
                return { events: [] };
            });
        });

        /** @typedef {{ file: string, label?: string }} VpcTrackDecl */
        /** @type {VpcTrackDecl[]} */
        var uiTracks = Array.isArray(cfg.tracks) ? cfg.tracks : [];

        var activeCaptionTrackIndex = 0;

        /** @type {unknown} */
        var vimeoPlayer = null;

        /** @type {HTMLElement | null} */
        var captionDynamicHost = root.querySelector('.vpc-caption-dynamic-btns');

        function recomputeFilteredMasterIndices() {
            if (
                captionPickerDynamic &&
                selectedSignLang !== '' &&
                fullPlaylistItems.length > 0
            ) {
                filteredMasterIndices = fullPlaylistItems
                    .map(function (item, ix) {
                        return (item.signLanguage || '') === selectedSignLang ? ix : -1;
                    })
                    .filter(function (ix) {
                        return ix >= 0;
                    });
                return;
            }
            filteredMasterIndices = fullPlaylistItems.map(function (_, ix) {
                return ix;
            });
        }

        function filteredCount() {
            return filteredMasterIndices.length;
        }

        function refreshMasterFromFilteredCursor(autoplayPreferred) {
            if (filteredCount() === 0) return Promise.resolve();
            filteredCursor = Math.min(filteredCursor, filteredCount() - 1);
            var masterIx = filteredMasterIndices[filteredCursor];
            if (masterIx !== undefined && masterIx !== playlistIndex) {
                return loadVideoMaster(masterIx, autoplayPreferred);
            }
            return Promise.resolve();
        }

        function currentItemCueTracksRaw() {
            var item = fullPlaylistItems[playlistIndex];
            return Array.isArray(item.tracks) ? item.tracks : [];
        }

        /** @returns {VpcTrackDecl[]} */
        function currentItemUiTracksForPicker() {
            if (captionPickerDynamic) return currentItemCueTracksRaw();
            if (playlistIndex !== 0) return [];
            return uiTracks;
        }

        function loadStaticVtt(file, masterIndex, cueTrackIndex, label) {
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
                    var tier = vimeoTracksState[masterIndex];
                    if (tier && tier[cueTrackIndex]) {
                        tier[cueTrackIndex].events = res.data;
                    }
                    syncAllCaptions();
                })
                .catch(function (e) {
                    console.warn('Static VTT fetch failed (' + label + '):', e);
                });
        }

        fullPlaylistItems.forEach(function (item, pi) {
            var tr = Array.isArray(item.tracks) ? item.tracks : [];
            tr.forEach(function (t, ci) {
                if (t && t.file) {
                    loadStaticVtt(t.file, pi, ci, 'vpc-' + pi + '-' + ci);
                }
            });
        });

        /** @returns {HTMLElement | null} */
        function captionPickerOuter() {
            return root.querySelector('.vpc-caption-lang-dynamic') || root.querySelector('.caption-lang-picker');
        }

        function rebuildDynamicCaptionButtons() {
            if (!captionPickerDynamic || !captionDynHost) return;

            captionDynHost.innerHTML = '';

            var tracks = currentItemCueTracksRaw();

            tracks.forEach(function (t, i) {
                if (!t || typeof t.label === 'undefined') return;
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'caption-lang-btn';
                b.setAttribute('data-track-index', String(i));
                b.textContent = t.label || '';
                var headingEl = root.querySelector('.caption-lang-picker-label');
                if (headingEl && headingEl.id) {
                    b.setAttribute('aria-describedby', headingEl.id);
                }
                b.setAttribute('aria-controls', captionBoxId);
                captionDynHost.appendChild(b);
            });
        }

        function updateCaptionPickerVisibility() {
            var picker = captionPickerOuter();
            if (!picker) return;

            var hasTracks = currentItemCueTracksRaw().length > 0 || (!captionPickerDynamic && uiTracks.length > 0);
            if (!captionPickerDynamic && playlistIndex !== 0) hasTracks = false;

            picker.classList.toggle(
                'vpc-caption-picker-hidden',
                captionPickerDynamic ? currentItemCueTracksRaw().length === 0 : !hasTracks
            );
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

        root.addEventListener('click', function (ev) {
            var tgt = /** @type {HTMLElement} */ (ev.target);
            if (!tgt.closest) return;
            var btn = tgt.closest('.caption-lang-btn');
            if (!btn || !root.contains(btn)) return;
            var idx = parseInt(btn.getAttribute('data-track-index'), 10);
            if (!isNaN(idx)) setActiveCaptionTrack(idx);
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

        recomputeFilteredMasterIndices();
        filteredCursor = 0;
        if (filteredCount() > 0) playlistIndex = filteredMasterIndices[0];

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

            function loadVideoMaster(masterIx, autoPlayPreferred) {
                var target =
                    typeof masterIx === 'number' && masterIx >= 0 ? masterIx : playlistIndex;

                playlistIndex = target;

                var item = fullPlaylistItems[playlistIndex];
                var vidRaw = item && item.videoId ? String(item.videoId) : '';
                var vidNum = parseInt(vidRaw, 10);
                /** @type {Promise<void>} */
                var loadP =
                    !isNaN(vidNum) && typeof p.loadVideo === 'function'
                        ? p.loadVideo(vidNum).then(function () {})
                        : Promise.reject(new Error('Vimeo.Player.loadVideo unavailable'));

                return loadP
                    .then(function () {
                        if (captionPickerDynamic) rebuildDynamicCaptionButtons();
                        activeCaptionTrackIndex = 0;
                        setActiveCaptionTrack(0);
                        updateCaptionPickerVisibility();
                        /** @type {Promise<void>} */
                        var autoplayP =
                            autoPlayPreferred === false ? Promise.resolve() : tryAutoplayFallback();
                        return autoplayP;
                    })
                    .then(function () {
                        updatePlaylistNavButtons();
                        syncCaptionBox(captionBoxId, eventsForSync(), 0);
                        refreshTransport();
                    })
                    .catch(function (e) {
                        console.warn('Vimeo playlist: loadVideo failed', e);
                        refreshTransport();
                    });
            }

            function updatePlaylistNavButtons() {
                var prevBtn = root.querySelector('.vpc-prev-btn');
                var nextBtn = root.querySelector('.vpc-next-btn');
                if (!prevBtn || !nextBtn) return;
                var fc = filteredCount();
                prevBtn.disabled = fc <= 1 || filteredCursor <= 0;
                nextBtn.disabled = fc <= 1 || filteredCursor >= fc - 1;
            }

            /** @param {number} deltaFiltered */
            function seekFiltered(deltaFiltered, autoplayPreferred) {
                var ni = filteredCursor + deltaFiltered;
                if (filteredCount() <= 0 || ni < 0 || ni >= filteredMasterIndices.length) {
                    return Promise.resolve();
                }
                filteredCursor = ni;
                var masterIx = filteredMasterIndices[filteredCursor];
                return loadVideoMaster(masterIx, autoplayPreferred !== false).then(function () {
                    updatePlaylistNavButtons();
                });
            }

            var prevTransport = root.querySelector('.vpc-prev-btn');
            if (prevTransport) {
                prevTransport.addEventListener('click', function () {
                    seekFiltered(-1, true);
                });
            }
            var nextTransport = root.querySelector('.vpc-next-btn');
            if (nextTransport) {
                nextTransport.addEventListener('click', function () {
                    seekFiltered(1, true);
                });
            }

            var signSel = /** @type {HTMLSelectElement | null} */ (root.querySelector(
                '.vpc-sign-lang-select'
            ));
            if (signSel && captionPickerDynamic) {
                signSel.value = selectedSignLang;
                signSel.addEventListener('change', function () {
                    selectedSignLang = signSel.value;
                    recomputeFilteredMasterIndices();
                    filteredCursor = 0;

                    if (filteredCount() === 0) return;

                    playlistIndex = filteredMasterIndices[0];

                    loadVideoMaster(playlistIndex, true).then(function () {
                        updatePlaylistNavButtons();
                    });
                });
            }

            loadVideoMaster(playlistIndex, undefined)
                .then(function () {})
                .catch(function () {});

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
