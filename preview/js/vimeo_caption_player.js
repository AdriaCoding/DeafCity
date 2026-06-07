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

    /**
     * Next playlist step after a video ends, or null when already on the last entry.
     * @param {{ shuffleMode: boolean, filteredCursor: number, shuffleStep: number, filteredCount: number, shuffledSequence: number[] }} opts
     * @returns {{ filteredCursor: number, shuffleStep: number } | null}
     */
    function nextPlaylistStep(opts) {
        var fc = opts.filteredCount;
        if (fc <= 0) return null;

        if (opts.shuffleMode) {
            var nextStep = opts.shuffleStep + 1;
            if (nextStep >= fc) return null;
            return {
                shuffleStep: nextStep,
                filteredCursor: opts.shuffledSequence[nextStep],
            };
        }

        var nextCursor = opts.filteredCursor + 1;
        if (nextCursor >= fc) return null;
        return { shuffleStep: opts.shuffleStep, filteredCursor: nextCursor };
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
        /** Label of the viewer's chosen caption track; persists across Videos. */
        var stickyCaptionLabel = '';

        /** @type {unknown} */
        var vimeoPlayer = null;

        /** @type {HTMLSelectElement | null} */
        var captionLangSelect = /** @type {HTMLSelectElement | null} */ (
            root.querySelector('.vpc-caption-lang-select')
        );

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

        recomputeFilteredMasterIndices();
        filteredCursor = 0;
        if (filteredCount() > 0) playlistIndex = filteredMasterIndices[0];

        function attachPlayer() {
            var iframe = document.getElementById(iframeId);
            if (!iframe || !window.Vimeo || !window.Vimeo.Player) return;
            vimeoPlayer = new window.Vimeo.Player(iframe);

            /** @type {any} */
            var p = vimeoPlayer;

            /** When true, prev/next follow shuffledSequence[shuffleStep]. */
            var shuffleMode = false;
            /** Permutation of filtered indices 0..n-1. */
            var shuffledSequence = [];
            var shuffleStep = 0;

            /** Once true, subsequent Videos load unmuted (browser permits after first gesture). */
            var sessionSoundOn = false;
            var badgeFlashTimer = null;

            var videoShell = root.querySelector('.video-shell');
            var soundBadge = root.querySelector('.vpc-sound-badge');

            function updateSoundUi(isMuted) {
                if (videoShell) {
                    videoShell.classList.toggle('vpc-sound-muted', isMuted);
                    videoShell.classList.toggle('vpc-sound-unmuted', !isMuted);
                }
                if (soundBadge) {
                    soundBadge.setAttribute('aria-pressed', isMuted ? 'false' : 'true');
                    soundBadge.setAttribute(
                        'aria-label',
                        isMuted ? 'Unmute video' : 'Mute video'
                    );
                    var badgeIcon = soundBadge.querySelector('.material-icons');
                    if (badgeIcon) {
                        badgeIcon.textContent = isMuted ? 'volume_off' : 'volume_up';
                    }
                }
            }

            function flashBadgeIfTouch() {
                if (!videoShell || window.matchMedia('(hover: hover)').matches) return;
                videoShell.classList.add('vpc-sound-badge-flash');
                if (badgeFlashTimer) clearTimeout(badgeFlashTimer);
                badgeFlashTimer = setTimeout(function () {
                    videoShell.classList.remove('vpc-sound-badge-flash');
                    badgeFlashTimer = null;
                }, 2000);
            }

            function toggleMute() {
                p.getMuted()
                    .then(function (wasMuted) {
                        var nextMuted = !wasMuted;
                        if (!nextMuted) sessionSoundOn = true;
                        return p.setMuted(nextMuted).then(function () {
                            updateSoundUi(nextMuted);
                            if (wasMuted) flashBadgeIfTouch();
                        });
                    })
                    .catch(function () {});
            }

            updateSoundUi(true);

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
                hitArea.addEventListener('click', toggleMute);
            }
            if (soundBadge) {
                soundBadge.addEventListener('click', function (ev) {
                    ev.stopPropagation();
                    toggleMute();
                });
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
                        return p.getMuted().then(function (muted) {
                            updateSoundUi(muted);
                        });
                    })
                    .then(function () {
                        refreshTransport();
                        syncAllCaptions();
                    });
            }

            function applyVideoAspectRatio() {
                if (!videoShell) return Promise.resolve();
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
                    videoShell.style.aspectRatio = aspectRatioFrom(dims[0], dims[1]);
                });
            }

            function resetVideoAspectPlaceholder() {
                if (videoShell) videoShell.style.aspectRatio = '16 / 9';
            }

            function iframeEmbedVideoId() {
                var el = document.getElementById(iframeId);
                if (!el || !el.src) return '';
                var m = el.src.match(/\/video\/(\d+)/);
                return m ? m[1] : '';
            }

            function applyLoadedVideoUi() {
                if (captionPickerDynamic) rebuildDynamicCaptionSelect();
                var cueTracks = currentItemCueTracksRaw();
                var trackIx = pickStickyTrackIndex(cueTracks, stickyCaptionLabel);
                setActiveCaptionTrack(trackIx);
                updateCaptionPickerVisibility();
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

            function buildShuffledSequence() {
                var fc = filteredCount();
                var arr = [];
                var i;
                for (i = 0; i < fc; i++) arr.push(i);
                for (i = fc - 1; i > 0; i--) {
                    var j = Math.floor(Math.random() * (i + 1));
                    var t = arr[i];
                    arr[i] = arr[j];
                    arr[j] = t;
                }
                return arr;
            }

            function setShuffleToggleUi(on) {
                var btn = root.querySelector('.vpc-shuffle-btn');
                if (!btn) return;
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                var icon = btn.querySelector('.material-icons');
                if (icon) icon.textContent = 'shuffle';
            }

            function advanceOnEnded() {
                var step = nextPlaylistStep({
                    shuffleMode: shuffleMode,
                    filteredCursor: filteredCursor,
                    shuffleStep: shuffleStep,
                    filteredCount: filteredCount(),
                    shuffledSequence: shuffledSequence,
                });
                if (!step) return;
                if (shuffleMode) {
                    shuffleStep = step.shuffleStep;
                    filteredCursor = step.filteredCursor;
                    var masterIx = filteredMasterIndices[filteredCursor];
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
                shuffleStep = ni;
                filteredCursor = shuffledSequence[shuffleStep];
                var masterIx = filteredMasterIndices[filteredCursor];
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
                    shuffledSequence = buildShuffledSequence();
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

            var signSel = /** @type {HTMLSelectElement | null} */ (root.querySelector(
                '.vpc-sign-lang-select'
            ));
            if (signSel && captionPickerDynamic) {
                signSel.value = selectedSignLang;
                signSel.addEventListener('change', function () {
                    selectedSignLang = signSel.value;
                    recomputeFilteredMasterIndices();
                    filteredCursor = 0;
                    shuffleMode = false;
                    shuffledSequence = [];
                    shuffleStep = 0;
                    setShuffleToggleUi(false);

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
