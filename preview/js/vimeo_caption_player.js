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

        /** @type {Array<{ id?: string, label?: string, vimeo_code?: string }>} */
        var subtitleLanguages = Array.isArray(cfg.subtitleLanguages) ? cfg.subtitleLanguages : [];

        /** @type {Array<{ videoId: string, tracks: Array, signLanguage: string, edition: string, typology: string, participant: string }>} */
        var serverPlaylist =
            Array.isArray(cfg.playlist) && cfg.playlist.length > 0 ? cfg.playlist : [];

        /**
         * Master playlist — full catalog for filtering (D18′). Server playlist carries
         * visit/poster order; catalogPlaylist is the stable master when provided.
         */
        var fullPlaylistItems =
            Array.isArray(cfg.catalogPlaylist) && cfg.catalogPlaylist.length > 0
                ? cfg.catalogPlaylist
                : serverPlaylist;

        /**
         * @param {string} videoId
         * @returns {number}
         */
        function masterIndexForVideoId(videoId) {
            var id = String(videoId || '');
            if (id === '') return 0;
            for (var i = 0; i < fullPlaylistItems.length; i++) {
                if (String(fullPlaylistItems[i].videoId) === id) return i;
            }
            return 0;
        }

        var posterItem =
            serverPlaylist[
                typeof cfg.playlistIndex === 'number' ? cfg.playlistIndex : 0
            ] || serverPlaylist[0];

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

        /** Full option catalogs from server (studio-config labels). */
        var filterOptionCatalog = {
            sign_language:
                cfg.signLanguageFilter && Array.isArray(cfg.signLanguageFilter.options)
                    ? cfg.signLanguageFilter.options
                    : [],
            edition:
                cfg.editionFilter && Array.isArray(cfg.editionFilter.options)
                    ? cfg.editionFilter.options
                    : [],
            typology:
                cfg.typologyFilter && Array.isArray(cfg.typologyFilter.options)
                    ? cfg.typologyFilter.options
                    : [],
        };

        /** "All [category]" labels per facet (D17′). */
        var filterClearLabels = {
            sign_language: 'All sign languages',
            edition: 'All cities',
            typology: 'All typologies',
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
        var playlistIndex = posterItem
            ? masterIndexForVideoId(posterItem.videoId)
            : typeof cfg.playlistIndex === 'number'
              ? cfg.playlistIndex
              : 0;

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
        /** Spoken language id (studio-config) the viewer chose; persists across videos (D16′). */
        var stickySpokenLangId = '';

        /** @type {unknown} */
        var vimeoPlayer = null;

        /** @type {HTMLSelectElement | null} */
        var captionLangSelect = /** @type {HTMLSelectElement | null} */ (
            root.querySelector('.vpc-caption-lang-select')
        );

        /**
         * Recompute which master-playlist indices are in scope given filterState (D17, D18).
         */
        function recomputeFilteredMasterIndices() {
            filteredMasterIndices = L.recomputeFilteredMasterIndices(fullPlaylistItems, filterState);
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

        function setActiveCaptionTrack(index, updateSticky) {
            var cueTracks = currentItemCueTracksRaw();
            if (cueTracks.length === 0) {
                activeCaptionTrackIndex = 0;
                syncCaptionBox(captionBoxId, [], 0);
                return;
            }
            if (index < 0 || index >= cueTracks.length) return;
            activeCaptionTrackIndex = index;
            if (updateSticky !== false && cueTracks[index]) {
                stickySpokenLangId = L.resolveSpokenLangId(
                    cueTracks[index].lang || '',
                    subtitleLanguages
                );
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

        if (isParticipantMode) {
            filteredMasterIndices = fullPlaylistItems
                .map(function (item, ix) {
                    return (item.participant || '') === participantName ? ix : -1;
                })
                .filter(function (ix) { return ix >= 0; });
        }

        if (serverShuffled && filteredCount() > 0 && !isParticipantMode) {
            shuffleMode = true;
            shuffledSequence = [];
            serverPlaylist.forEach(function (entry) {
                var mix = masterIndexForVideoId(entry.videoId);
                var fpos = filteredMasterIndices.indexOf(mix);
                if (fpos >= 0 && shuffledSequence.indexOf(fpos) < 0) {
                    shuffledSequence.push(fpos);
                }
            });
            filteredMasterIndices.forEach(function (_, fpos) {
                if (shuffledSequence.indexOf(fpos) < 0) {
                    shuffledSequence.push(fpos);
                }
            });
            var startFpos = filteredMasterIndices.indexOf(playlistIndex);
            shuffleStep = startFpos >= 0 ? shuffledSequence.indexOf(startFpos) : 0;
            if (shuffleStep < 0) shuffleStep = 0;
            filteredCursor = shuffledSequence[shuffleStep];
        } else if (serverShuffled && filteredCount() > 0 && isParticipantMode) {
            shuffleMode = true;
            shuffledSequence = [];
            for (var _psi = 0; _psi < filteredCount(); _psi++) {
                shuffledSequence.push(_psi);
            }
            shuffleStep = 0;
            filteredCursor = Math.max(0, filteredMasterIndices.indexOf(playlistIndex));
            if (filteredCursor < 0) filteredCursor = 0;
            playlistIndex = filteredMasterIndices[filteredCursor];
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
            /** While >0, suppress play events from forced-pause loads (D1′, D19). */
            var forcedPauseLoads = 0;

            /**
             * Sticky activation for this page view (D12′). First gesture unlocks sound;
             * thereafter playlist advances and rebuilds auto-play with sound.
             */
            function markGestureActivation() {
                visitorStartedPlayback = true;
                sessionSoundOn = true;
            }

            /** @deprecated alias — use markGestureActivation */
            function markPlaybackStarted() {
                markGestureActivation();
            }

            /** D23: participant card click on /preview/participants counts as a gesture. */
            var participantGestureCarried = false;
            if (typeof sessionStorage !== 'undefined' && L.resolveParticipantGestureCarry) {
                try {
                    var storedGesture = sessionStorage.getItem(L.GESTURE_STORAGE_KEY);
                    participantGestureCarried = L.resolveParticipantGestureCarry(
                        isParticipantMode,
                        storedGesture
                    );
                    if (participantGestureCarried) {
                        sessionStorage.removeItem(L.GESTURE_STORAGE_KEY);
                    }
                } catch (e) {}
            }
            if (participantGestureCarried) {
                markGestureActivation();
            }

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

            function unmuteForPlayback() {
                if (typeof p.setMuted !== 'function') return Promise.resolve();
                return p.setMuted(false);
            }

            function togglePlayPause() {
                p.getPaused()
                    .then(function (paused) {
                        if (paused) {
                            markGestureActivation();
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

            /**
             * Reset (D1′): clear all filters & collections, reshuffle unfiltered ALL,
             * land paused on a fresh random poster. Sole post-gesture action that pauses.
             */
            function resetToNeutralAll() {
                markGestureActivation();

                var plan = L.planResetToNeutralAll({
                    fullPlaylistItems: fullPlaylistItems,
                });
                if (!plan) return;

                filterState = plan.filterState;
                isParticipantMode = plan.isParticipantMode;
                participantName = plan.participantName;
                filteredMasterIndices = plan.filteredMasterIndices;
                filteredCursor = plan.filteredCursor;
                shuffleStep = plan.shuffleStep;
                shuffledSequence = plan.shuffledSequence;
                shuffleMode = plan.shuffleMode;

                setShuffleToggleUi(shuffleMode);
                syncCollectionNavButtons();
                updateAllFilterPickerReadouts();
                rebuildAllCascadingDropdowns();

                if (typeof history !== 'undefined' && history.replaceState) {
                    try {
                        var url = new URL(window.location.href);
                        if (url.searchParams.has('participant')) {
                            url.searchParams.delete('participant');
                            history.replaceState(null, '', url.pathname + url.search + url.hash);
                        }
                    } catch (e) {}
                }

                playlistIndex = plan.loadMasterIndex;
                loadVideoMaster(playlistIndex, plan.shouldAutoplay).then(function () {
                    updatePlaylistNavButtons();
                });
            }

            var resetBtn = root.querySelector('.vpc-reset-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', resetToNeutralAll);
            }

            function tryAutoplayFallback() {
                if (!L.shouldAutoplayWithSound(sessionSoundOn, true)) {
                    return Promise.resolve();
                }
                var readyPromise =
                    typeof p.ready === 'function' ? p.ready() : Promise.resolve();
                return readyPromise
                    .then(function () {
                        return unmuteForPlayback().then(function () {
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
             * Rebuild Spoken Language dropup from the current video's caption tracks (D16′).
             */
            function rebuildSpokenLanguagePickerOptions() {
                var pickerEl = /** @type {HTMLElement|null} */ (
                    root.querySelector('.vpc-picker[data-picker="spoken_language"]')
                );
                if (!pickerEl) return;

                var dropdown = pickerEl.querySelector('.vpc-picker-dropdown');
                if (!dropdown) return;

                var cueTracks = currentItemCueTracksRaw();
                var options = L.buildSpokenOptionsForTracks(cueTracks, subtitleLanguages);
                var activeSpokenId = '';
                if (cueTracks.length > 0 && cueTracks[activeCaptionTrackIndex]) {
                    activeSpokenId = L.resolveSpokenLangId(
                        cueTracks[activeCaptionTrackIndex].lang || '',
                        subtitleLanguages
                    );
                }

                dropdown.innerHTML = '';
                options.forEach(function (opt) {
                    var li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.className = 'vpc-picker-option';
                    li.setAttribute('data-value', opt.spokenLangId);
                    li.setAttribute('data-track-index', String(opt.trackIndex));
                    li.setAttribute(
                        'aria-selected',
                        opt.spokenLangId === activeSpokenId ? 'true' : 'false'
                    );
                    li.textContent = opt.label;
                    dropdown.appendChild(li);
                });
            }

            /**
             * Update Spoken Language picker button — neutral readout, never green (D16′, D21).
             */
            function updateSpokenLanguagePickerUi() {
                var pickerEl = /** @type {HTMLElement|null} */ (
                    root.querySelector('.vpc-picker[data-picker="spoken_language"]')
                );
                if (!pickerEl) return;

                var btn = /** @type {HTMLButtonElement|null} */ (pickerEl.querySelector('.vpc-picker-btn'));
                if (!btn) return;

                var genericLabel = btn.getAttribute('data-generic-label') || 'Spoken Language';
                var cueTracks = currentItemCueTracksRaw();
                var hasTracks = cueTracks.length > 0
                    && L.buildSpokenOptionsForTracks(cueTracks, subtitleLanguages).length > 0;

                pickerEl.setAttribute('data-active', 'false');
                pickerEl.setAttribute('data-disabled', hasTracks ? 'false' : 'true');
                btn.disabled = !hasTracks;

                if (!hasTracks) {
                    btn.textContent = 'No subtitles';
                    return;
                }

                var activeTrack = cueTracks[activeCaptionTrackIndex] || cueTracks[0];
                var activeId = L.resolveSpokenLangId(activeTrack.lang || '', subtitleLanguages);
                btn.textContent = activeId
                    ? L.spokenLangLabel(activeId, subtitleLanguages)
                    : genericLabel;
            }

            function applyLoadedVideoUi() {
                if (captionPickerDynamic) rebuildDynamicCaptionSelect();
                var cueTracks = currentItemCueTracksRaw();
                var trackIx = L.resolveActiveCaptionTrackIndex(
                    cueTracks,
                    stickySpokenLangId,
                    subtitleLanguages
                );
                setActiveCaptionTrack(trackIx, false);
                rebuildSpokenLanguagePickerOptions();
                updateSpokenLanguagePickerUi();
                updateCaptionPickerVisibility();
                updateAllFilterPickerReadouts();
                rebuildAllCascadingDropdowns();
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
                applyLoadedVideoUi();

                var vidRaw = item && item.videoId ? String(item.videoId) : '';
                var vidNum = parseInt(vidRaw, 10);
                var wantAutoplay = L.shouldAutoplayWithSound(sessionSoundOn, autoPlayPreferred);

                resetVideoAspectPlaceholder();
                if (!wantAutoplay) {
                    forcedPauseLoads++;
                }

                return resolveLoadVideoPromise(vidNum, vidRaw, wantAutoplay)
                    .then(function () {
                        applyLoadedVideoUi();
                        /** @type {Promise<void>} */
                        var autoplayP;
                        if (wantAutoplay) {
                            autoplayP = tryAutoplayFallback();
                        } else {
                            autoplayP = p.pause()
                                .catch(function () {})
                                .then(function () {
                                    setTransportPlaying(false);
                                })
                                .then(function () {
                                    return new Promise(function (resolve) {
                                        window.setTimeout(function () {
                                            forcedPauseLoads = Math.max(0, forcedPauseLoads - 1);
                                            resolve();
                                        }, 300);
                                    });
                                });
                        }
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
                        if (!wantAutoplay) {
                            forcedPauseLoads = Math.max(0, forcedPauseLoads - 1);
                        }
                        console.warn('Vimeo playlist: loadVideo failed', e);
                        applyLoadedVideoUi();
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

            /**
             * Update shuffle button visual state (D13).
             * aria-pressed drives the CSS; is-active class mirrors it for robustness.
             * @param {boolean} on
             */
            function setShuffleToggleUi(on) {
                var btn = root.querySelector('.vpc-shuffle-btn');
                if (!btn) return;
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.classList.toggle('is-active', !!on);
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
                        // D13: Shuffle OFF — remaining queue follows catalog order from
                        // the current video onward. Current video keeps playing.
                        // Sync filteredCursor to current video's position in the
                        // catalog-ordered filtered list (it was set by shuffle step,
                        // which is already the filtered-list position, so this is a
                        // no-op in most cases — but explicit for clarity).
                        var currentMasterIx = filteredMasterIndices[filteredCursor];
                        var catalogPos = filteredMasterIndices.indexOf(currentMasterIx);
                        if (catalogPos >= 0) filteredCursor = catalogPos;
                        shuffleMode = false;
                        shuffledSequence = [];
                        setShuffleToggleUi(false);
                        updatePlaylistNavButtons();
                        return;
                    }
                    // D13: Shuffle ON — re-shuffle remaining queue from current position.
                    // Current video keeps playing; future queue is re-randomised.
                    shuffleMode = true;
                    shuffledSequence = L.buildShuffledSequence(filteredCount());
                    // Place current video at step 0 of the new shuffle so Prev/Next
                    // navigate coherently relative to what is playing now.
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
                    markGestureActivation();
                    if (shuffleMode) seekShuffle(-1, true);
                    else seekFiltered(-1, true);
                });
            }
            var nextTransport = root.querySelector('.vpc-next-btn');
            if (nextTransport) {
                nextTransport.addEventListener('click', function () {
                    markGestureActivation();
                    if (shuffleMode) seekShuffle(1, true);
                    else seekFiltered(1, true);
                });
            }

            // ── R2 custom pickers (D15, D17, D18) ──────────────────────────────────

            /**
             * Apply a Spoken Language track change (D16′).
             * Switches the subtitle track of the CURRENT video only.
             * @param {string} spokenLangId  studio-config subtitle language id
             */
            function applySpokenLanguageChange(spokenLangId) {
                var cueTracks = currentItemCueTracksRaw();
                if (!spokenLangId) return;
                stickySpokenLangId = spokenLangId;
                var idx = L.pickTrackIndexForSpokenLang(
                    cueTracks,
                    spokenLangId,
                    subtitleLanguages
                );
                if (idx >= 0) {
                    setActiveCaptionTrack(idx, false);
                    rebuildSpokenLanguagePickerOptions();
                    updateSpokenLanguagePickerUi();
                }
            }

            /**
             * Active label for an R3 collection nav button (D21). Empty = neutral.
             * @param {string} collectionKey  e.g. "participants", "tags"
             * @returns {string}
             */
            function getActiveCollectionLabel(collectionKey) {
                if (collectionKey === 'participants') {
                    return isParticipantMode && participantName ? participantName : '';
                }
                if (collectionKey === 'tags') {
                    return '';
                }
                return '';
            }

            /**
             * Sync all R3 collection nav buttons: green + name when active, generic label otherwise (D21).
             */
            function syncCollectionNavButtons() {
                root.querySelectorAll('.preview-site-nav__btn[data-collection]').forEach(function (btn) {
                    var key = btn.getAttribute('data-collection') || '';
                    var activeLabel = getActiveCollectionLabel(key);
                    var genericLabel = btn.getAttribute('data-generic-label');
                    if (!genericLabel) {
                        genericLabel = btn.textContent.trim();
                        btn.setAttribute('data-generic-label', genericLabel);
                    }
                    if (activeLabel) {
                        btn.textContent = activeLabel;
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-current', 'true');
                    } else {
                        btn.textContent = genericLabel;
                        btn.classList.remove('is-active');
                        btn.removeAttribute('aria-current');
                    }
                });
            }

            /**
             * Update one R2 filter picker: live readout (neutral) or green when fixed (D14′, D21).
             * @param {string} facet
             */
            function updateFilterPickerReadout(facet) {
                var pickerEl = root.querySelector('.vpc-picker[data-picker="' + facet + '"]');
                if (!pickerEl) return;

                var btn = pickerEl.querySelector('.vpc-picker-btn');
                if (!btn) return;

                var genericLabel = btn.getAttribute('data-generic-label') || facet;
                var item = fullPlaylistItems[playlistIndex];
                var readout = L.resolveFilterPickerReadout(
                    item,
                    facet,
                    filterState,
                    filterOptionCatalog[facet] || [],
                    genericLabel
                );

                btn.textContent = readout.label;
                pickerEl.setAttribute('data-active', readout.fixed ? 'true' : 'false');
            }

            function updateAllFilterPickerReadouts() {
                ['sign_language', 'edition', 'typology'].forEach(updateFilterPickerReadout);
            }

            /**
             * Repopulate one dropup from cascading options (D17′).
             * @param {string} facet
             */
            function rebuildCascadingDropdown(facet) {
                var pickerEl = root.querySelector('.vpc-picker[data-picker="' + facet + '"]');
                if (!pickerEl) return;

                var dropdown = pickerEl.querySelector('.vpc-picker-dropdown');
                if (!dropdown) return;

                var cascade = L.buildCascadingFilterOptions(
                    fullPlaylistItems,
                    filterState,
                    filterOptionCatalog
                );
                var options = cascade[facet] || [];
                var fixedValue = filterState[facet];
                var clearLabel = filterClearLabels[facet] || 'All';

                dropdown.innerHTML = '';

                var clearLi = document.createElement('li');
                clearLi.setAttribute('role', 'option');
                clearLi.className = 'vpc-picker-option vpc-picker-clear';
                clearLi.setAttribute('data-value', '');
                clearLi.setAttribute(
                    'aria-selected',
                    fixedValue === null || fixedValue === undefined ? 'true' : 'false'
                );
                clearLi.textContent = clearLabel;
                dropdown.appendChild(clearLi);

                options.forEach(function (opt) {
                    var li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.className = 'vpc-picker-option';
                    li.setAttribute('data-value', opt.value);
                    li.setAttribute(
                        'aria-selected',
                        fixedValue === opt.value ? 'true' : 'false'
                    );
                    li.textContent = opt.label;
                    dropdown.appendChild(li);
                });
            }

            function rebuildAllCascadingDropdowns() {
                ['sign_language', 'edition', 'typology'].forEach(rebuildCascadingDropdown);
            }

            /**
             * Apply a filter change with keep-if-matches playback (D22) and cascade (D17′).
             * @param {string} facet
             * @param {string|null} value
             */
            function applyFilterChange(facet, value) {
                var newValue = value || null;

                if (L.shouldClearCollectionOnFilterFix(isParticipantMode, newValue)) {
                    isParticipantMode = false;
                    participantName = '';
                    syncCollectionNavButtons();
                }

                filterState[facet] = newValue;

                var plan = L.planFilterPlaylistRebuild({
                    fullPlaylistItems: fullPlaylistItems,
                    filterState: filterState,
                    currentMasterIndex: playlistIndex,
                    shuffleMode: shuffleMode,
                });

                if (!plan) {
                    filterState[facet] = null;
                    recomputeFilteredMasterIndices();
                    updateAllFilterPickerReadouts();
                    rebuildAllCascadingDropdowns();
                    return;
                }

                filteredMasterIndices = plan.filteredMasterIndices;
                filteredCursor = plan.filteredCursor;
                shuffleStep = plan.shuffleStep;
                shuffledSequence = plan.shuffledSequence;
                shuffleMode = plan.shuffleMode;
                setShuffleToggleUi(shuffleMode);

                updateAllFilterPickerReadouts();
                rebuildAllCascadingDropdowns();

                if (plan.keepCurrentVideo) {
                    updatePlaylistNavButtons();
                    tryAutoplayFallback();
                    return;
                }

                playlistIndex = plan.loadMasterIndex;
                loadVideoMaster(playlistIndex, true).then(function () {
                    updatePlaylistNavButtons();
                });
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
                if (!btn || !dropdown) return;

                // Toggle dropdown open/close
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (isSpokenLanguagePicker && pickerEl.getAttribute('data-disabled') === 'true') {
                        return;
                    }
                    if (!isSpokenLanguagePicker) {
                        rebuildCascadingDropdown(facet);
                    }
                    var isOpen = !dropdown.hidden;
                    closeAllPickers();
                    if (!isOpen) {
                        dropdown.hidden = false;
                        btn.setAttribute('aria-expanded', 'true');
                    }
                });

                // Option click — delegation for dynamically rebuilt filter dropups
                dropdown.addEventListener('click', function (e) {
                    var target = /** @type {HTMLElement|null} */ (e.target);
                    if (!target || !target.classList.contains('vpc-picker-option')) return;
                    e.stopPropagation();
                    if (isSpokenLanguagePicker && pickerEl.getAttribute('data-disabled') === 'true') {
                        return;
                    }

                    var value = target.getAttribute('data-value') || '';
                    var isClear = value === '' || target.classList.contains('vpc-picker-clear');

                    dropdown.querySelectorAll('.vpc-picker-option').forEach(function (o) {
                        o.setAttribute('aria-selected', 'false');
                    });
                    target.setAttribute('aria-selected', 'true');
                    closeAllPickers();

                    if (isSpokenLanguagePicker) {
                        applySpokenLanguageChange(value);
                        return;
                    }

                    markGestureActivation();
                    applyFilterChange(facet, isClear ? null : value);
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

            rebuildAllCascadingDropdowns();
            updateAllFilterPickerReadouts();

            // D18: Update Participants nav button label when in participant mode.
            syncCollectionNavButtons();

            // Close pickers when clicking outside
            document.addEventListener('click', function () {
                closeAllPickers();
            });

            setShuffleToggleUi(shuffleMode);

            loadVideoMaster(playlistIndex, participantGestureCarried)
                .then(function () {})
                .catch(function () {});

            p.on('play', function () {
                if (forcedPauseLoads > 0) {
                    p.pause().catch(function () {});
                    setTransportPlaying(false);
                    return;
                }
                markGestureActivation();
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
