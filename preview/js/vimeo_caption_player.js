(function () {
    'use strict';

    /** @type {typeof import('./vimeo_playlist_logic')} */
    var L = typeof VpcPlaylistLogic !== 'undefined' ? VpcPlaylistLogic : null;
    if (!L) {
        console.warn('Vimeo caption player: VpcPlaylistLogic missing');
        return;
    }

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

    /**
     * CSS aspect-ratio value from Vimeo dimensions; 16/9 when unavailable.
     * @param {number} width
     * @param {number} height
     * @returns {string}
     */
    function aspectRatioFrom(width, height) {
        var w = typeof width === 'number' ? width : parseInt(width, 10);
        var h = typeof height === 'number' ? height : parseInt(height, 10);
        if (!w || !h || w <= 0 || h <= 0) return '16 / 9';
        return w + ' / ' + h;
    }

    /**
     * Caption font size (px) from rendered video height — iframe is fit-to-height, so
     * height is the stable scale axis (width swings with aspect ratio).
     * @param {number} videoHeightPx
     * @returns {number}
     */
    function captionFontSizePxFromVideoHeight(videoHeightPx) {
        var h = typeof videoHeightPx === 'number' ? videoHeightPx : parseFloat(videoHeightPx);
        if (!h || h <= 0 || !isFinite(h)) return 18;
        var size = h * 0.048;
        if (size < 14) return 14;
        if (size > 38) return 38;
        return Math.round(size * 10) / 10;
    }

    /**
     * Pick caption track index matching previousLabel, or 0 when no match.
     * @param {Array<{ label?: string }>} tracks
     * @param {string} previousLabel
     * @returns {number}
     */
    function pickStickyTrackIndex(tracks, previousLabel) {
        if (!Array.isArray(tracks) || tracks.length === 0) return 0;
        if (previousLabel) {
            for (var i = 0; i < tracks.length; i++) {
                if (tracks[i] && tracks[i].label === previousLabel) return i;
            }
        }
        return 0;
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
                : '/preview/captions-static.php';

        /**
         * Master playlist (full catalog, never mutated).
         * Each item: { videoId, tracks, signLanguage, edition, typology, participant }
         * @type {Array<{ videoId: string, tracks: Array<{file:string,label?:string}>, signLanguage: string, edition: string, typology: string, participant: string }>}
         */
        var fullPlaylistItems =
            Array.isArray(cfg.playlist) && cfg.playlist.length > 0 ? cfg.playlist : [];

        /**
         * D18 — Composable filter state. null = not active for that facet.
         * All R2 filter pickers read/write this object. Filters compose with AND.
         * @type {{ sign_language: string|null, edition: string|null, typology: string|null }}
         */
        var filterState = {
            sign_language: null,
            edition: null,
            typology: null,
        };

        /** Participant name when a participant playlist is active (D18). '' = not in participant mode. */
        var participantName = typeof cfg.participantName === 'string' ? cfg.participantName.trim() : '';
        var isParticipantMode = participantName !== '';

        /** @type {boolean} */
        var captionPickerDynamic = !!cfg.captionPickerDynamic;

        /** @type {number[]} */
        var filteredMasterIndices = [];

        /** Index into filteredMasterIndices — which video inside the filtered list is playing. */
        var filteredCursor = 0;

        /** Absolute index into fullPlaylistItems (master). */
        var playlistIndex =
            typeof cfg.playlistIndex === 'number' ? cfg.playlistIndex : 0;

        /**
         * When true the server has already shuffled the playlist and placed the chosen
         * poster at index 0 (D12). JS must NOT re-shuffle; instead treat the server's
         * order as the shuffle sequence so the paused poster is exactly what Play continues.
         * @type {boolean}
         */
        var serverShuffled = !!cfg.serverShuffled;

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
        /** Label of the viewer's chosen caption track; persists across Videos. */
        var stickyCaptionLabel = '';

        /** @type {unknown} */
        var vimeoPlayer = null;

        /** @type {HTMLSelectElement | null} */
        var captionLangSelect = /** @type {HTMLSelectElement | null} */ (
            root.querySelector('.vpc-caption-lang-select')
        );

        /**
         * Recompute which master-playlist indices are in scope given filterState (D17, D18).
         * Filters compose with AND. null values are inactive (match all).
         */
        function recomputeFilteredMasterIndices() {
            var hasFilter = filterState.sign_language !== null
                || filterState.edition !== null
                || filterState.typology !== null;
            if (!hasFilter || fullPlaylistItems.length === 0) {
                filteredMasterIndices = fullPlaylistItems.map(function (_, ix) { return ix; });
                return;
            }
            filteredMasterIndices = fullPlaylistItems
                .map(function (item, ix) {
                    if (filterState.sign_language !== null
                        && (item.signLanguage || '') !== filterState.sign_language) return -1;
                    if (filterState.edition !== null
                        && (item.edition || '') !== filterState.edition) return -1;
                    if (filterState.typology !== null
                        && (item.typology || '') !== filterState.typology) return -1;
                    return ix;
                })
                .filter(function (ix) { return ix >= 0; });
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
            return root.querySelector('.vpc-caption-lang-filter');
        }

        function rebuildDynamicCaptionSelect() {
            if (!captionPickerDynamic || !captionLangSelect) return;

            var tracks = currentItemCueTracksRaw();
            var prevValue = captionLangSelect.value;

            captionLangSelect.innerHTML = '';

            tracks.forEach(function (t, i) {
                if (!t || typeof t.label === 'undefined') return;
                var opt = document.createElement('option');
                opt.value = String(i);
                opt.textContent = t.label || '';
                captionLangSelect.appendChild(opt);
            });

            if (prevValue !== '' && captionLangSelect.querySelector('option[value="' + prevValue + '"]')) {
                captionLangSelect.value = prevValue;
            }
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

        function syncCaptionSelectValue(index) {
            if (!captionLangSelect) return;
            if (captionLangSelect.options.length === 0) return;
            var safeIndex = Math.min(Math.max(index, 0), captionLangSelect.options.length - 1);
            captionLangSelect.value = String(safeIndex);
        }

        function setActiveCaptionTrack(index) {
            var cueTracks = currentItemCueTracksRaw();
            if (cueTracks.length === 0) {
                activeCaptionTrackIndex = 0;
                syncCaptionBox(captionBoxId, [], 0);
                return;
            }
            if (index < 0 || index >= cueTracks.length) return;
            activeCaptionTrackIndex = index;
            if (cueTracks[index] && cueTracks[index].label) {
                stickyCaptionLabel = cueTracks[index].label;
            }

            syncCaptionSelectValue(index);
            syncAllCaptions();
        }

        if (captionLangSelect) {
            captionLangSelect.addEventListener('change', function () {
                var idx = parseInt(captionLangSelect.value, 10);
                if (!isNaN(idx)) setActiveCaptionTrack(idx);
            });
        }

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

        /** When true, prev/next follow shuffledSequence[shuffleStep]. */
        var shuffleMode = false;
        /** Permutation of filtered indices 0..n-1. */
        var shuffledSequence = [];
        var shuffleStep = 0;

        recomputeFilteredMasterIndices();
        if (serverShuffled && filteredCount() > 0) {
            // Server pre-shuffled (D12): the playlist order IS the shuffle order.
            // Build the identity sequence [0, 1, 2, ... n-1] — item 0 is the poster
            // and the queue head; pressing Play continues that exact video, no swap.
            shuffleMode = true;
            shuffledSequence = [];
            for (var _si = 0; _si < filteredCount(); _si++) { shuffledSequence.push(_si); }
            shuffleStep = 0;
            filteredCursor = 0;
            playlistIndex = filteredMasterIndices[0];
        } else {
            var defaultShuffle = L.createDefaultShuffleState(filteredCount());
            shuffleMode = defaultShuffle.shuffleMode;
            shuffledSequence = defaultShuffle.shuffledSequence;
            shuffleStep = defaultShuffle.shuffleStep;
            filteredCursor = defaultShuffle.filteredCursor;
            if (filteredCount() > 0) {
                playlistIndex = filteredMasterIndices[filteredCursor];
            }
        }

        function attachPlayer() {
            var iframe = document.getElementById(iframeId);
            if (!iframe || !window.Vimeo || !window.Vimeo.Player) return;
            vimeoPlayer = new window.Vimeo.Player(iframe);

            /** @type {any} */
            var p = vimeoPlayer;

            /** Once true, subsequent Videos load unmuted (browser permits after first gesture). */
            var sessionSoundOn = false;
            /** Once true, end-of-video may advance within the Playlist. */
            var visitorStartedPlayback = false;

            var videoShell = root.querySelector('.video-shell');
            var videoStack = root.querySelector('.video-stack');

            function syncCaptionTypography() {
                var measureEl = iframe || videoShell || videoStack;
                if (!measureEl) return;
                var rect = measureEl.getBoundingClientRect();
                var h = rect.height;
                if (h <= 0 && videoShell) {
                    h = videoShell.getBoundingClientRect().height;
                }
                root.style.setProperty(
                    '--vpc-caption-font-size',
                    captionFontSizePxFromVideoHeight(h) + 'px'
                );
                var w = rect.width;
                if (videoShell) {
                    var shellW = videoShell.getBoundingClientRect().width;
                    if (w <= 0 || w > shellW) w = shellW;
                }
                if (w <= 0 && videoStack) {
                    w = videoStack.getBoundingClientRect().width;
                }
                if (w > 0) {
                    root.style.setProperty('--vpc-caption-width', w + 'px');
                }
            }

            syncCaptionTypography();
            if (typeof window.ResizeObserver === 'function') {
                var captionResizeObserver = new window.ResizeObserver(function () {
                    syncCaptionTypography();
                });
                if (iframe) captionResizeObserver.observe(iframe);
                else if (videoShell) captionResizeObserver.observe(videoShell);
            } else {
                window.addEventListener('resize', syncCaptionTypography);
            }

            var playBtn = root.querySelector('.vpc-play-pause-btn');

            function setTransportPlaying(isPlaying) {
                if (!playBtn) return;
                var icon = playBtn.querySelector('.material-icons');
                if (isPlaying) {
                    if (icon) icon.textContent = 'pause';
                    playBtn.setAttribute('aria-label', 'Pause video');
                } else {
                    if (icon) icon.textContent = 'play_arrow';
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

            function markPlaybackStarted() {
                visitorStartedPlayback = true;
                sessionSoundOn = true;
            }

            function unmuteForPlayback() {
                if (typeof p.setMuted !== 'function') return Promise.resolve();
                return p.setMuted(false);
            }

            function togglePlayPause() {
                p.getPaused()
                    .then(function (paused) {
                        if (paused) {
                            markPlaybackStarted();
                            return unmuteForPlayback().then(function () {
                                return p.play();
                            });
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
                markPlaybackStarted();
                p.setCurrentTime(0)
                    .then(function () {
                        return unmuteForPlayback().then(function () {
                            return p.play();
                        });
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
                        var mutedP =
                            typeof p.setMuted === 'function'
                                ? p.setMuted(!sessionSoundOn)
                                : Promise.resolve();
                        return mutedP.then(function () {
                            return p.getPaused().then(function (paused) {
                                if (paused) return p.play();
                            });
                        });
                    })
                    .catch(function () {})
                    .then(function () {
                        refreshTransport();
                        syncAllCaptions();
                    });
            }

            function applyVideoAspectRatio() {
                if (!iframe) return Promise.resolve();
                var wP =
                    typeof p.getVideoWidth === 'function'
                        ? p.getVideoWidth().catch(function () {
                              return 0;
                          })
                        : Promise.resolve(0);
                var hP =
                    typeof p.getVideoHeight === 'function'
                        ? p.getVideoHeight().catch(function () {
                              return 0;
                          })
                        : Promise.resolve(0);
                return Promise.all([wP, hP]).then(function (dims) {
                    iframe.style.aspectRatio = aspectRatioFrom(dims[0], dims[1]);
                    syncCaptionTypography();
                });
            }

            function resetVideoAspectPlaceholder() {
                if (iframe) iframe.style.aspectRatio = '16 / 9';
            }

            function iframeEmbedVideoId() {
                var el = document.getElementById(iframeId);
                if (!el || !el.src) return '';
                var m = el.src.match(/\/video\/(\d+)/);
                return m ? m[1] : '';
            }

            /**
             * Update the Spoken Language picker button face to reflect the currently
             * active (or sticky-but-unavailable) track after a video change.
             * Called from applyLoadedVideoUi() so it fires on every video transition.
             */
            function syncSpokenLanguagePickerUi() {
                var pickerEl = /** @type {HTMLElement|null} */ (
                    root.querySelector('.vpc-picker[data-picker="spoken_language"]')
                );
                if (!pickerEl) return;

                var genericLabel = 'Spoken Language';
                var btn = pickerEl.querySelector('.vpc-picker-btn');
                if (btn) {
                    genericLabel = btn.getAttribute('data-generic-label') || genericLabel;
                }

                var cueTracks = currentItemCueTracksRaw();
                // Check if the sticky label is available in the current video's tracks.
                var matchIdx = stickyCaptionLabel ? pickStickyTrackIndex(cueTracks, stickyCaptionLabel) : -1;
                var trackAvailable = stickyCaptionLabel !== ''
                    && cueTracks.length > 0
                    && matchIdx > 0; // index 0 means fallback (label not found), so only >0 is a real match

                // Edge: if only one track and it matches at index 0, treat as matched.
                if (stickyCaptionLabel !== '' && cueTracks.length > 0 && matchIdx === 0
                    && cueTracks[0] && cueTracks[0].label === stickyCaptionLabel) {
                    trackAvailable = true;
                }

                var options = pickerEl.querySelectorAll('.vpc-picker-option');
                options.forEach(function (o) { o.setAttribute('aria-selected', 'false'); });

                if (trackAvailable) {
                    // Mark the matching option selected in the dropdown.
                    options.forEach(function (o) {
                        if ((o.getAttribute('data-value') || '') === stickyCaptionLabel) {
                            o.setAttribute('aria-selected', 'true');
                        }
                    });
                    updatePickerUi(pickerEl, stickyCaptionLabel, genericLabel, stickyCaptionLabel);
                } else {
                    // Sticky track unavailable for this video — fall back to "no selection".
                    var clearOpt = pickerEl.querySelector('.vpc-picker-clear');
                    if (clearOpt) clearOpt.setAttribute('aria-selected', 'true');
                    updatePickerUi(pickerEl, null, genericLabel, '');
                }
            }

            function applyLoadedVideoUi() {
                if (captionPickerDynamic) rebuildDynamicCaptionSelect();
                var cueTracks = currentItemCueTracksRaw();
                var trackIx = pickStickyTrackIndex(cueTracks, stickyCaptionLabel);
                setActiveCaptionTrack(trackIx);
                updateCaptionPickerVisibility();
                syncSpokenLanguagePickerUi();
            }

            function resolveLoadVideoPromise(vidNum, vidRaw, wantAutoplay) {
                if (isNaN(vidNum) || typeof p.loadVideo !== 'function') {
                    return Promise.reject(new Error('Vimeo.Player.loadVideo unavailable'));
                }

                var currentIdP =
                    typeof p.getVideoId === 'function'
                        ? p.getVideoId().catch(function () {
                              return null;
                          })
                        : Promise.resolve(null);

                return currentIdP.then(function (currentId) {
                    var currentRaw =
                        currentId !== null && currentId !== undefined && currentId !== ''
                            ? String(currentId)
                            : iframeEmbedVideoId();
                    if (currentRaw === vidRaw) {
                        return Promise.resolve();
                    }
                    return p
                        .loadVideo({
                            id: vidNum,
                            autoplay: wantAutoplay,
                            muted: !sessionSoundOn,
                        })
                        .then(function () {});
                });
            }

            function loadVideoMaster(masterIx, autoPlayPreferred) {
                var target =
                    typeof masterIx === 'number' && masterIx >= 0 ? masterIx : playlistIndex;

                playlistIndex = target;

                var item = fullPlaylistItems[playlistIndex];
                var vidRaw = item && item.videoId ? String(item.videoId) : '';
                var vidNum = parseInt(vidRaw, 10);
                var wantAutoplay = autoPlayPreferred !== false;

                resetVideoAspectPlaceholder();

                return resolveLoadVideoPromise(vidNum, vidRaw, wantAutoplay)
                    .then(function () {
                        applyLoadedVideoUi();
                        /** @type {Promise<void>} */
                        var autoplayP = wantAutoplay ? tryAutoplayFallback() : Promise.resolve();
                        return autoplayP;
                    })
                    .then(function () {
                        return applyVideoAspectRatio();
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
                if (shuffleMode) {
                    prevBtn.disabled = fc <= 1 || shuffleStep <= 0;
                    nextBtn.disabled = fc <= 1 || shuffleStep >= fc - 1;
                } else {
                    prevBtn.disabled = fc <= 1 || filteredCursor <= 0;
                    nextBtn.disabled = fc <= 1 || filteredCursor >= fc - 1;
                }
            }

            function setShuffleToggleUi(on) {
                var btn = root.querySelector('.vpc-shuffle-btn');
                if (!btn) return;
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                var icon = btn.querySelector('.material-icons');
                if (icon) icon.textContent = 'shuffle';
            }

            /**
             * Reset to a fresh reshuffled ALL Playlist, paused on a new random poster (D19).
             * Called when the ALL Playlist (or a collection) reaches its last video.
             */
            function resetToFreshShuffledPlaylist() {
                var fc = filteredCount();
                if (fc <= 0) return;
                // Build a brand-new shuffle — item at sequence[0] becomes the new poster.
                shuffledSequence = L.buildShuffledSequence(fc);
                shuffleStep = 0;
                filteredCursor = shuffledSequence[0];
                shuffleMode = true;
                var masterIx = filteredMasterIndices[filteredCursor];
                if (masterIx === undefined) return;
                // Load the new head, paused (false = no autoplay).
                loadVideoMaster(masterIx, false).then(function () {
                    updatePlaylistNavButtons();
                });
            }

            function advanceOnEnded() {
                if (!L.shouldAdvanceOnEnded(visitorStartedPlayback)) return;
                var step = L.nextPlaylistStep({
                    shuffleMode: shuffleMode,
                    filteredCursor: filteredCursor,
                    shuffleStep: shuffleStep,
                    filteredCount: filteredCount(),
                    shuffledSequence: shuffledSequence,
                });
                if (!step) {
                    if (isParticipantMode) {
                        // D19: participant collection finished → fresh ALL paused (page reload without ?participant)
                        window.location.href = '/preview/';
                        return;
                    }
                    // End of ALL playlist (D19): reset to fresh reshuffle, paused on new poster.
                    resetToFreshShuffledPlaylist();
                    return;
                }
                if (shuffleMode) {
                    shuffleStep = step.shuffleStep;
                    filteredCursor = step.filteredCursor;
                    var masterIx = filteredMasterIndices[filteredCursor];
                    if (masterIx === undefined) return;
                    loadVideoMaster(masterIx, true).then(function () {
                        updatePlaylistNavButtons();
                    });
                } else {
                    seekFiltered(1, true);
                }
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

            /** @param {number} deltaStep +1 / -1 in shuffled order */
            function seekShuffle(deltaStep, autoplayPreferred) {
                var fc = filteredCount();
                var ni = shuffleStep + deltaStep;
                if (fc <= 0 || ni < 0 || ni >= fc) {
                    return Promise.resolve();
                }
                var shuffledCursor = L.filteredCursorFromShuffleStep(shuffledSequence, ni, fc);
                if (shuffledCursor === null) return Promise.resolve();
                shuffleStep = ni;
                filteredCursor = shuffledCursor;
                var masterIx = filteredMasterIndices[filteredCursor];
                if (masterIx === undefined) return Promise.resolve();
                return loadVideoMaster(masterIx, autoplayPreferred !== false).then(function () {
                    updatePlaylistNavButtons();
                });
            }

            var shuffleBtn = root.querySelector('.vpc-shuffle-btn');
            if (shuffleBtn) {
                shuffleBtn.addEventListener('click', function () {
                    if (shuffleMode) {
                        shuffleMode = false;
                        shuffledSequence = [];
                        setShuffleToggleUi(false);
                        updatePlaylistNavButtons();
                        return;
                    }
                    shuffleMode = true;
                    shuffledSequence = L.buildShuffledSequence(filteredCount());
                    shuffleStep = 0;
                    var s;
                    for (s = 0; s < shuffledSequence.length; s++) {
                        if (shuffledSequence[s] === filteredCursor) {
                            shuffleStep = s;
                            break;
                        }
                    }
                    setShuffleToggleUi(true);
                    updatePlaylistNavButtons();
                });
            }

            var prevTransport = root.querySelector('.vpc-prev-btn');
            if (prevTransport) {
                prevTransport.addEventListener('click', function () {
                    if (shuffleMode) seekShuffle(-1, true);
                    else seekFiltered(-1, true);
                });
            }
            var nextTransport = root.querySelector('.vpc-next-btn');
            if (nextTransport) {
                nextTransport.addEventListener('click', function () {
                    if (shuffleMode) seekShuffle(1, true);
                    else seekFiltered(1, true);
                });
            }

            // ── R2 custom pickers (D15, D17, D18) ──────────────────────────────────

            /**
             * Apply a Spoken Language track change (D16).
             * Switches the subtitle track of the CURRENT video only.
             * Does NOT touch filterState, does NOT re-queue the playlist.
             * The selected label is persisted as stickyCaptionLabel so it sticks
             * across videos (falling back gracefully when the next video lacks it).
             * @param {string} label  Track label (e.g. "English"), or '' to clear.
             */
            function applySpokenLanguageChange(label) {
                var cueTracks = currentItemCueTracksRaw();
                if (label === '' || !label) {
                    // Clear: disable subtitles (track index 0 with empty stickyCaptionLabel)
                    stickyCaptionLabel = '';
                    activeCaptionTrackIndex = 0;
                    syncCaptionBox(captionBoxId, [], 0);
                    return;
                }
                stickyCaptionLabel = label;
                var idx = pickStickyTrackIndex(cueTracks, label);
                setActiveCaptionTrack(idx);
            }

            /**
             * Update the Participants nav button label to show the active participant name (D18).
             * Reverts to 'Participants' text when participant mode is cleared.
             */
            function syncParticipantButtonLabel() {
                var participantsBtn = root.querySelector('.preview-site-nav__btn[href="/preview/participants"]');
                if (!participantsBtn) return;
                if (isParticipantMode && participantName) {
                    participantsBtn.textContent = participantName;
                }
            }

            /**
             * Apply a filter change: update filterState, recompute filtered list,
             * shuffle within the new filtered set, load video[0] of new set.
             * Clearing (value=null) re-shuffles the full catalog (D7, issue #1 behaviour).
             * @param {string} facet  e.g. 'sign_language'
             * @param {string|null} value
             */
            function applyFilterChange(facet, value) {
                if (isParticipantMode) {
                    isParticipantMode = false;
                    participantName = '';
                    syncParticipantButtonLabel();
                }
                filterState[facet] = value || null;
                recomputeFilteredMasterIndices();

                var fc = filteredCount();
                if (fc === 0) {
                    // Edge case: empty result — clear this facet, restore all.
                    filterState[facet] = null;
                    recomputeFilteredMasterIndices();
                    fc = filteredCount();
                }

                // Shuffle within the new filtered set (or full catalog on clear).
                shuffledSequence = L.buildShuffledSequence(fc);
                shuffleStep = 0;
                filteredCursor = shuffledSequence[0];
                shuffleMode = true;
                setShuffleToggleUi(true);

                var masterIx = filteredMasterIndices[filteredCursor];
                if (masterIx === undefined) return;
                playlistIndex = masterIx;

                loadVideoMaster(playlistIndex, false).then(function () {
                    updatePlaylistNavButtons();
                });
            }

            /**
             * Update a picker's button face and data-active attribute.
             * @param {HTMLElement} pickerEl
             * @param {string|null} value  null = cleared
             * @param {string} genericLabel  Button face when no selection
             * @param {string} selectedLabel  Button face after selection
             */
            function updatePickerUi(pickerEl, value, genericLabel, selectedLabel) {
                var btn = pickerEl.querySelector('.vpc-picker-btn');
                if (!btn) return;
                if (value) {
                    btn.textContent = selectedLabel;
                    btn.setAttribute('data-generic-label', genericLabel);
                    pickerEl.setAttribute('data-active', 'true');
                } else {
                    btn.textContent = genericLabel;
                    pickerEl.setAttribute('data-active', 'false');
                }
                // Re-append the ::after pseudo-element is pure CSS; textContent replaces child nodes
                // so we need to keep the label text only — ::after is CSS-only, fine.
            }

            /**
             * Close all custom picker dropdowns in this player instance.
             */
            function closeAllPickers() {
                root.querySelectorAll('.vpc-picker-dropdown').forEach(function (dd) {
                    dd.hidden = true;
                    var picker = dd.closest('.vpc-picker');
                    var btn = picker && picker.querySelector('.vpc-picker-btn');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                });
            }

            /**
             * Wire up a single custom picker element.
             * @param {HTMLElement} pickerEl  .vpc-picker div
             */
            function initPicker(pickerEl) {
                var facet = pickerEl.getAttribute('data-picker');
                if (!facet) return;

                /** True for the Spoken Language track selector (D16 — not a filter). */
                var isSpokenLanguagePicker = facet === 'spoken_language';

                var btn = /** @type {HTMLButtonElement|null} */ (pickerEl.querySelector('.vpc-picker-btn'));
                var dropdown = /** @type {HTMLElement|null} */ (pickerEl.querySelector('.vpc-picker-dropdown'));
                var options = /** @type {NodeListOf<HTMLElement>} */ (
                    pickerEl.querySelectorAll('.vpc-picker-option')
                );
                if (!btn || !dropdown) return;

                var genericLabel = btn.getAttribute('data-generic-label') || btn.textContent.trim();

                // Toggle dropdown open/close
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var isOpen = !dropdown.hidden;
                    closeAllPickers();
                    if (!isOpen) {
                        dropdown.hidden = false;
                        btn.setAttribute('aria-expanded', 'true');
                    }
                });

                // Option click: select or clear
                options.forEach(function (opt) {
                    opt.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var value = opt.getAttribute('data-value') || '';
                        var isClear = value === '' || opt.classList.contains('vpc-picker-clear');

                        // Update ARIA selected state
                        options.forEach(function (o) { o.setAttribute('aria-selected', 'false'); });
                        opt.setAttribute('aria-selected', 'true');

                        closeAllPickers();

                        var label = isClear ? '' : opt.textContent.trim();
                        updatePickerUi(pickerEl, isClear ? null : value, genericLabel, label);

                        if (isSpokenLanguagePicker) {
                            // D16: track selector — swap subtitle track, do NOT re-queue.
                            applySpokenLanguageChange(isClear ? '' : value);
                        } else {
                            // Real filter: update filterState and re-queue playlist (D18).
                            applyFilterChange(facet, isClear ? null : value);
                        }
                    });
                });

                // Keyboard: Escape closes
                dropdown.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        closeAllPickers();
                        btn.focus();
                    }
                });
            }

            // Wire up all R2 pickers in this instance
            root.querySelectorAll('.vpc-picker').forEach(initPicker);

            // D18: Update Participants nav button label when in participant mode.
            syncParticipantButtonLabel();

            // Close pickers when clicking outside
            document.addEventListener('click', function () {
                closeAllPickers();
            });

            setShuffleToggleUi(shuffleMode);

            loadVideoMaster(playlistIndex, false)
                .then(function () {})
                .catch(function () {});

            p.on('play', function () {
                markPlaybackStarted();
                setTransportPlaying(true);
            });
            p.on('pause', function () {
                setTransportPlaying(false);
                p.getCurrentTime().then(syncVimeoCaptionBoxes);
            });
            p.on('ended', function () {
                setTransportPlaying(false);
                advanceOnEnded();
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
        document.querySelectorAll('.preview-vimeo-player-root').forEach(initOne);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
