(function () {
    'use strict';

    /** @type {typeof import('./vimeo_playlist_logic')} */
    var L = typeof VpcPlaylistLogic !== 'undefined' ? VpcPlaylistLogic : null;
    if (!L) {
        console.warn('Vimeo caption player: VpcPlaylistLogic missing');
        return;
    }

    /**
     * @param {() => void} onReady
     * @param {(() => void)=} onError  Called once if the SDK script fails to load
     *   (e.g. offline, blocked). Never called once onReady has fired.
     */
    function ensureVimeoSdk(onReady, onError) {
        if (window.Vimeo && window.Vimeo.Player) {
            onReady();
            return;
        }
        window.__vpcVimeoSdkCallbacks = window.__vpcVimeoSdkCallbacks || [];
        window.__vpcVimeoSdkCallbacks.push(onReady);
        window.__vpcVimeoSdkErrorCallbacks = window.__vpcVimeoSdkErrorCallbacks || [];
        if (typeof onError === 'function') {
            window.__vpcVimeoSdkErrorCallbacks.push(onError);
        }

        if (window.__vpcVimeoSdkLoading) return;
        window.__vpcVimeoSdkLoading = true;

        var s = document.createElement('script');
        s.src = 'https://player.vimeo.com/api/player.js';
        s.onload = function () {
            window.__vpcVimeoSdkLoading = false;
            var pending = window.__vpcVimeoSdkCallbacks || [];
            window.__vpcVimeoSdkCallbacks = [];
            window.__vpcVimeoSdkErrorCallbacks = [];
            pending.forEach(function (cb) {
                try {
                    cb();
                } catch (e) {}
            });
        };
        s.onerror = function () {
            window.__vpcVimeoSdkLoading = false;
            window.__vpcVimeoSdkCallbacks = [];
            var pendingErrors = window.__vpcVimeoSdkErrorCallbacks || [];
            window.__vpcVimeoSdkErrorCallbacks = [];
            console.warn('Vimeo Player SDK failed to load');
            pendingErrors.forEach(function (cb) {
                try {
                    cb();
                } catch (e) {}
            });
        };
        document.head.appendChild(s);
    }

    /**
     * Visible, minimal, non-intrusive error message in a player instance's media
     * area — reuses the same empty-state slot the empty-queue state renders into
     * (D18 unknown-participant fix), so it never fights the normal loading scrim.
     * @param {HTMLElement} root
     * @param {string} message
     */
    function showVpcPlayerError(root, message) {
        var shell = root.querySelector('.video-shell');
        if (!shell) return;
        var el = shell.querySelector('.vpc-empty-state');
        if (!el) {
            el = document.createElement('p');
            el.className = 'vpc-empty-state';
            shell.appendChild(el);
        }
        el.textContent = message;
        var scrim = shell.querySelector('.vpc-load-scrim');
        if (scrim) scrim.classList.remove('is-hidden');
        var poster = shell.querySelector('.vpc-poster-cover');
        if (poster) poster.classList.add('is-hidden');
    }

    /**
     * Vimeo embed query params the server always applies (components/
     * vimeo_caption_player.php's $defaultParams) — mirrored here so a Player
     * constructed lazily, client-side, from a bare id ends up configured exactly
     * like one the server would have rendered (no native controls, no title/byline/
     * portrait chrome, our own keyboard shortcuts only, playsinline).
     */
    var VPC_DEFAULT_EMBED_PARAMS = {
        title: '0',
        byline: '0',
        portrait: '0',
        dnt: '1',
        controls: '0',
        preload: 'auto',
        playsinline: '1',
        keyboard: '0',
    };

    /**
     * DOM-facing wrapper over L.createLazyVimeoPlayerFacade() (see there for why):
     * supplies the real `new Vimeo.Player(iframe, ...)` construction and whether the
     * iframe already carries a src (the common case — a valid initial Video,
     * server-rendered as today, constructed immediately, synchronously, before this
     * function returns — behavior unchanged from a direct `new Vimeo.Player(iframe)`
     * call) vs. none (an unknown/empty Participant filter — constructed lazily, on
     * first genuine loadVideo()). The real SDK throws
     * "The player element passed isn’t a Vimeo embed." if construct is
     * `new Player(iframe, {id})` on a src-less iframe — so loadVideo assigns
     * the embed src first (`assignEmbedSrc`), then constructs with no options.
     *
     * @param {HTMLIFrameElement} iframe
     * @returns {any}
     */
    function createLazyVimeoPlayer(iframe) {
        return L.createLazyVimeoPlayerFacade({
            hasInitialSrc: !!iframe.getAttribute('src'),
            defaultEmbedParams: VPC_DEFAULT_EMBED_PARAMS,
            assignEmbedSrc: function (src) {
                if (src) {
                    iframe.setAttribute('src', src);
                }
            },
            constructReal: function (playerOpts) {
                return playerOpts
                    ? new window.Vimeo.Player(iframe, playerOpts)
                    : new window.Vimeo.Player(iframe);
            },
        });
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

    var captionMeasureCanvas = null;

    /**
     * @param {string} text
     * @param {number} fontSizePx
     * @returns {number}
     */
    function measureCaptionTextWidth(text, fontSizePx) {
        if (!text) return 0;
        if (!captionMeasureCanvas) {
            captionMeasureCanvas = document.createElement('canvas');
        }
        var ctx = captionMeasureCanvas.getContext('2d');
        if (!ctx) return 0;
        ctx.font = '400 ' + fontSizePx + 'px Roboto, "Noto Sans Arabic", sans-serif';
        return ctx.measureText(text).width;
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

        /** Assigned inside attachPlayer(); noop until the iframe is ready. */
        var syncCaptionBox = function (_events, _timeMs) {};
        var captionsEndpoint =
            cfg.captionsEndpoint && typeof cfg.captionsEndpoint === 'string'
                ? cfg.captionsEndpoint
                : '/captions-static.php';
        var assetBasePath =
            cfg.assetBasePath && typeof cfg.assetBasePath === 'string'
                ? cfg.assetBasePath
                : '';
        var localeApiPath =
            cfg.localeApiPath && typeof cfg.localeApiPath === 'string'
                ? cfg.localeApiPath
                : '/api/locale.php';

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
         * @returns {number} index into fullPlaylistItems, or -1 when videoId is empty
         *   or matches nothing — callers must handle -1 explicitly rather than
         *   silently falling back to (and playing) index 0.
         */
        function masterIndexForVideoId(videoId) {
            return L.masterIndexForVideoId(fullPlaylistItems, videoId);
        }

        var posterItem =
            serverPlaylist[
                typeof cfg.playlistIndex === 'number' ? cfg.playlistIndex : 0
            ] || serverPlaylist[0];

        /**
         * D18 — Composable filter state. null = not active for that facet.
         * All R2 filter pickers read/write this object. Filters compose with AND.
         * tag is array-membership (DH16), not equality.
         * @type {{ sign_language: string|null, edition: string|null, typology: string|null, tag: string|null }}
         */
        var filterState = {
            sign_language: null,
            edition: null,
            typology: null,
            tag: null,
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

        /** Localized chrome strings from vpc-config (player.* keys). */
        var strings = cfg.strings && typeof cfg.strings === 'object' ? cfg.strings : {};
        function vpcString(key, fallback) {
            if (strings[key]) return strings[key];
            return fallback !== undefined ? fallback : key;
        }

        /** Active Website language — changes in session without a reload. */
        var websiteLang = typeof cfg.websiteLang === 'string' && cfg.websiteLang !== ''
            ? cfg.websiteLang
            : 'en';

        /** "All [category]" labels per facet (D17′). Rebuilt on a Website language switch. */
        var filterClearLabels = {
            sign_language: vpcString('player.filter.all_sign_languages', 'All sign languages'),
            edition: vpcString('player.filter.all_cities', 'All cities'),
            typology: vpcString('player.filter.all_typologies', 'All typologies'),
        };

        function rebuildFilterClearLabels() {
            filterClearLabels = {
                sign_language: vpcString('player.filter.all_sign_languages', 'All sign languages'),
                edition: vpcString('player.filter.all_cities', 'All cities'),
                typology: vpcString('player.filter.all_typologies', 'All typologies'),
            };
        }

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
        var posterMasterIndex = posterItem ? masterIndexForVideoId(posterItem.videoId) : -1;
        var playlistIndex =
            posterMasterIndex >= 0
                ? posterMasterIndex
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
        /** Website language id — drives subtitle track selection on load and video change (issue #19). */
        var stickySpokenLangId = typeof cfg.initialSubtitleLang === 'string' ? cfg.initialSubtitleLang : '';

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
            // playlistIndex is legitimately -1 while no Video is selected — an unknown
            // or empty Participant collection resolves to loadMasterIndex -1, which
            // index.php treats as a supported state rather than an error.
            var item = fullPlaylistItems[playlistIndex];
            if (!item) return [];
            return Array.isArray(item.tracks) ? item.tracks : [];
        }

        /** @returns {VpcTrackDecl[]} */
        function currentItemUiTracksForPicker() {
            if (captionPickerDynamic) return currentItemCueTracksRaw();
            if (playlistIndex !== 0) return [];
            return uiTracks;
        }

        var captionFetchStarted = {};
        var captionFetchErrorShown = false;

        /** Number of attempts for a caption fetch before giving up and showing an error. */
        var CAPTION_FETCH_MAX_ATTEMPTS = 3;
        /** Base delay (ms) for exponential backoff between caption fetch retries. */
        var CAPTION_FETCH_RETRY_BASE_MS = 500;

        /**
         * Small, non-intrusive notice next to the caption row — captions failing does
         * not stop Video playback, so this must never cover the Video itself (unlike
         * the SDK-load error, which does — nothing plays without the SDK).
         */
        function showCaptionFetchError() {
            if (captionFetchErrorShown) return;
            captionFetchErrorShown = true;
            var el = root.querySelector('.vpc-caption-error');
            if (!el) return;
            el.textContent = vpcString('player.error.captions_failed', 'Captions could not be loaded.');
            el.classList.remove('is-hidden');
        }

        function fetchStaticVttOnce(url, label) {
            return fetch(url).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok || !Array.isArray(data)) {
                        throw new Error('Static VTT failed (' + label + ')');
                    }
                    return data;
                });
            });
        }

        function loadStaticVtt(file, masterIndex, cueTrackIndex, label) {
            var key = masterIndex + ':' + cueTrackIndex + ':' + file;
            if (captionFetchStarted[key]) return;
            captionFetchStarted[key] = true;
            var url =
                captionsEndpoint +
                (captionsEndpoint.indexOf('?') >= 0 ? '&' : '?') +
                'f=' +
                encodeURIComponent(file);

            function attempt(attemptNumber) {
                fetchStaticVttOnce(url, label)
                    .then(function (data) {
                        var tier = vimeoTracksState[masterIndex];
                        if (tier && tier[cueTrackIndex]) {
                            // Binary search in findCaption() assumes start-sorted cues; nothing
                            // upstream guarantees that, so sort defensively after parsing.
                            tier[cueTrackIndex].events = L.sortCaptionEventsByStart(data);
                        }
                        syncAllCaptions();
                    })
                    .catch(function (e) {
                        if (attemptNumber < CAPTION_FETCH_MAX_ATTEMPTS) {
                            var delayMs = CAPTION_FETCH_RETRY_BASE_MS * Math.pow(2, attemptNumber - 1);
                            window.setTimeout(function () {
                                attempt(attemptNumber + 1);
                            }, delayMs);
                            return;
                        }
                        console.warn(
                            'Static VTT fetch failed after ' + CAPTION_FETCH_MAX_ATTEMPTS + ' attempts (' + label + '):',
                            e
                        );
                        showCaptionFetchError();
                    });
            }

            attempt(1);
        }

        function ensureCaptionsForMasterIndex(masterIndex) {
            var plan = L.planCaptionFetchesForMasterIndex(fullPlaylistItems, masterIndex);
            var i;
            for (i = 0; i < plan.length; i++) {
                var entry = plan[i];
                loadStaticVtt(
                    entry.file,
                    entry.masterIndex,
                    entry.cueTrackIndex,
                    'vpc-' + entry.masterIndex + '-' + entry.cueTrackIndex
                );
            }
        }

        ensureCaptionsForMasterIndex(playlistIndex);

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
                syncCaptionBox([], 0);
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
            if (videoSwitchInProgress) {
                seconds = 0;
            }
            var ms = seconds * 1000;
            var events = /** @type {unknown[]} */ (eventsForSync());
            syncCaptionBox(events, ms);
        }

        function syncAllCaptions() {
            if (videoSwitchInProgress) {
                syncVimeoCaptionBoxes(0);
                return;
            }
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
        /** While switching videos, caption sync must use t=0 (title cue), not stale player time. */
        var videoSwitchInProgress = false;

        recomputeFilteredMasterIndices();

        /** Resume from sessionStorage when returning from About/Participants (issue #02). */
        var restoredFromSession = false;
        var pendingPlaybackTimeSec = 0;
        var sessionRestoreAutoplay = false;

        if (typeof sessionStorage !== 'undefined') {
            try {
                var navIntentRaw = sessionStorage.getItem(L.NAV_INTENT_KEY);
                if (navIntentRaw) {
                    sessionStorage.removeItem(L.NAV_INTENT_KEY);
                }
                var sessionRaw = sessionStorage.getItem(L.PLAYBACK_SESSION_KEY);
                var parsedSession = sessionRaw ? L.parsePlaybackSession(sessionRaw) : null;
                var navPlan = L.planSecondaryNavRestore({
                    session: parsedSession,
                    navIntent: navIntentRaw,
                    fullPlaylistItems: fullPlaylistItems,
                    explicitParticipantName: participantName,
                });

                if (participantName !== '') {
                    try {
                        sessionStorage.removeItem(L.PLAYBACK_SESSION_KEY);
                    } catch (e) {}
                } else if (navPlan && navPlan.kind === 'reset' && navPlan.plan) {
                    sessionStorage.removeItem(L.PLAYBACK_SESSION_KEY);
                    filterState = navPlan.plan.filterState;
                    isParticipantMode = false;
                    participantName = '';
                    filteredMasterIndices = navPlan.plan.filteredMasterIndices;
                    filteredCursor = navPlan.plan.filteredCursor;
                    shuffleStep = navPlan.plan.shuffleStep;
                    shuffledSequence = navPlan.plan.shuffledSequence;
                    shuffleMode = navPlan.plan.shuffleMode;
                    playlistIndex = navPlan.plan.loadMasterIndex;
                    restoredFromSession = true;
                    serverShuffled = false;
                } else if (navPlan && navPlan.kind === 'fresh') {
                    sessionStorage.removeItem(L.PLAYBACK_SESSION_KEY);
                } else if (navPlan && (navPlan.kind === 'restore' || navPlan.kind === 'deaf-hearing' || navPlan.kind === 'filter')) {
                    filterState = {
                        sign_language: navPlan.filterState.sign_language !== undefined
                            ? navPlan.filterState.sign_language
                            : null,
                        edition: navPlan.filterState.edition !== undefined
                            ? navPlan.filterState.edition
                            : null,
                        typology: navPlan.filterState.typology !== undefined
                            ? navPlan.filterState.typology
                            : null,
                        tag: typeof navPlan.filterState.tag === 'string' && navPlan.filterState.tag !== ''
                            ? navPlan.filterState.tag
                            : null,
                    };
                    participantName = navPlan.participantName || '';
                    isParticipantMode = !!navPlan.isParticipantMode;
                    filteredMasterIndices = navPlan.filteredMasterIndices;
                    filteredCursor = navPlan.filteredCursor;
                    shuffleStep = navPlan.shuffleStep;
                    shuffledSequence = navPlan.shuffledSequence;
                    shuffleMode = navPlan.shuffleMode;
                    playlistIndex = navPlan.loadMasterIndex;
                    pendingPlaybackTimeSec = navPlan.playbackTimeSec || 0;
                    sessionRestoreAutoplay = !!navPlan.shouldAutoplay;
                    restoredFromSession = true;
                    serverShuffled = false;
                }
            } catch (e) {}
        }

        if (!restoredFromSession) {
            if (isParticipantMode) {
                var participantPlan = L.planParticipantCollectionPlaylist({
                    fullPlaylistItems: fullPlaylistItems,
                    participantName: participantName,
                });
                filteredMasterIndices = participantPlan.filteredMasterIndices;
                filteredCursor = participantPlan.filteredCursor;
                shuffleStep = participantPlan.shuffleStep;
                shuffledSequence = participantPlan.shuffledSequence;
                shuffleMode = participantPlan.shuffleMode;
                playlistIndex = participantPlan.loadMasterIndex;
            } else if (serverShuffled && filteredCount() > 0) {
                shuffleMode = true;
                var serverMasterIndices = [];
                serverPlaylist.forEach(function (entry) {
                    var mix = masterIndexForVideoId(entry.videoId);
                    if (mix < 0) return; // no matching master entry — do not seed a bogus index
                    if (serverMasterIndices.indexOf(mix) < 0) {
                        serverMasterIndices.push(mix);
                    }
                });
                // Issue #01: the server already built the exact playlist for cold entry
                // (the base playlist — one video per city). Trust its order and size
                // as-is; do not pad with the rest of the full-catalog filteredMasterIndices.
                filteredMasterIndices = serverMasterIndices;
                shuffledSequence = filteredMasterIndices.map(function (_, fpos) { return fpos; });
                var startFpos = filteredMasterIndices.indexOf(playlistIndex);
                shuffleStep = startFpos >= 0 ? startFpos : 0;
                filteredCursor = shuffleStep;
            } else {
                // Issue #01: cold entry without a server-pre-built playlist still gets
                // the base playlist (one video per city), never a full-catalog shuffle.
                var basePlan = L.planBaseCityPlaylist({ fullPlaylistItems: fullPlaylistItems });
                if (basePlan) {
                    filteredMasterIndices = basePlan.filteredMasterIndices;
                    shuffleMode = basePlan.shuffleMode;
                    shuffledSequence = basePlan.shuffledSequence;
                    shuffleStep = basePlan.shuffleStep;
                    filteredCursor = basePlan.filteredCursor;
                    playlistIndex = basePlan.loadMasterIndex;
                }
            }
        }

        function attachPlayer() {
            var iframe = document.getElementById(iframeId);
            if (!iframe || !window.Vimeo || !window.Vimeo.Player) return;
            vimeoPlayer = createLazyVimeoPlayer(iframe);

            /** @type {any} */
            var p = vimeoPlayer;

            /** Once true, later Videos may autoplay after a qualifying visitor interaction. */
            var sessionPlaybackActivated = false;
            try {
                sessionPlaybackActivated = sessionStorage.getItem('vpc-playback-activated') === '1';
            } catch (e) {}
            /** Once true, end-of-video may advance within the Playlist. */
            var visitorStartedPlayback = false;
            /** While >0, suppress play events from forced-pause loads (D1′, D19). */
            var forcedPauseLoads = 0;

            /**
             * Sticky activation for this browser session. A qualifying intent gesture
             * authorizes later Video playback with sound.
             */
            function markGestureActivation() {
                visitorStartedPlayback = true;
                sessionPlaybackActivated = true;
                try {
                    sessionStorage.setItem('vpc-playback-activated', '1');
                } catch (e) {}
            }

            /** D23: participant card click on /participants counts as a gesture. */
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
            var posterCover = root.querySelector('.vpc-poster-cover');
            var loadScrim = root.querySelector('.vpc-load-scrim');
            var posterRequestToken = 0;
            var posterTargetVideoId = '';
            var posterLoadedVideoId = '';
            var posterPlaybackStarted = false;
            var coverBuffering = false;
            var coverUsesScrim = false;
            /** Epoch ms — re-show white scrim on bufferstart until this time (load + short grace). */
            var suppressGrayUntil = 0;

            /**
             * Cover Vimeo's internal loading UI while the target Video loads.
             * Autoplays (running playlist) use a solid white scrim — never the next thumb.
             * Paused/cold loads may show the target thumbnail poster.
             * @param {any} item
             * @param {boolean} willAutoplay
             * @returns {number}
             */
            function beginPosterCoveredLoad(item, willAutoplay) {
                posterRequestToken++;
                var token = posterRequestToken;
                posterTargetVideoId = item && item.videoId ? String(item.videoId) : '';
                posterPlaybackStarted = false;

                var thumbnailUrl =
                    item && item.thumbnailUrl ? String(item.thumbnailUrl) : '';
                var coverPlan = L.planLoadCover({
                    willAutoplay: !!willAutoplay,
                    thumbnailUrl: thumbnailUrl,
                });
                coverUsesScrim = coverPlan.kind === 'solid-white';
                coverBuffering = false;

                if (coverPlan.kind === 'solid-white') {
                    suppressGrayUntil = Date.now() + 120000;
                    if (posterCover) posterCover.classList.add('is-hidden');
                    if (loadScrim) loadScrim.classList.remove('is-hidden');
                    return token;
                }

                suppressGrayUntil = 0;
                if (loadScrim) loadScrim.classList.add('is-hidden');

                if (coverPlan.kind === 'none' || !posterCover) {
                    if (posterCover) posterCover.classList.add('is-hidden');
                    return token;
                }

                posterCover.classList.remove('is-hidden');
                if (posterCover.getAttribute('src') === coverPlan.thumbnailUrl) {
                    return token;
                }

                var pendingPoster = new window.Image();
                pendingPoster.onload = function () {
                    if (token !== posterRequestToken) return;
                    posterCover.setAttribute('src', coverPlan.thumbnailUrl);
                };
                pendingPoster.src = coverPlan.thumbnailUrl;
                return token;
            }

            function markPosterVideoLoaded(item, token) {
                if (token !== posterRequestToken) return;
                posterLoadedVideoId = item && item.videoId ? String(item.videoId) : '';
            }

            function revealLoadedPosterVideo() {
                if (
                    posterTargetVideoId === '' ||
                    posterLoadedVideoId !== posterTargetVideoId
                ) {
                    return;
                }
                if (posterCover) posterCover.classList.add('is-hidden');
                if (loadScrim) loadScrim.classList.add('is-hidden');
                coverUsesScrim = false;
                // Keep a short guard: Vimeo often bufferstarts again right after reveal.
                suppressGrayUntil = Date.now() + 1000;
            }

            function markPosterPlaybackStarted() {
                if (posterLoadedVideoId === posterTargetVideoId) {
                    posterPlaybackStarted = true;
                }
            }

            function revealPosterAfterPlaybackProgress(seconds) {
                if (
                    !L.shouldRevealLoadCover({
                        playbackStarted: posterPlaybackStarted,
                        seconds: seconds,
                        buffering: coverBuffering,
                    })
                ) {
                    return;
                }
                revealLoadedPosterVideo();
            }

            /* Fixed block height so layout never feeds back into font sizing. */
            var captionTypography = { baseFontSize: 18, boxWidth: 0, twoLineMode: false };

            function syncCaptionBlockLayout() {
                var box = document.getElementById(captionBoxId);
                var twoLine = L.captionUsesTwoLineWrap(captionTypography.boxWidth);
                captionTypography.twoLineMode = twoLine;
                var maxLines = twoLine ? 2 : 1;
                var blockHeight = L.captionBlockHeightPx(captionTypography.baseFontSize, maxLines);
                root.style.setProperty('--vpc-caption-block-height', blockHeight + 'px');
                if (box) {
                    box.classList.toggle('caption-box--two-line', twoLine);
                }
            }

            function captionTextAvailableWidth(box) {
                if (!box) return captionTypography.boxWidth;
                var style = window.getComputedStyle(box);
                var padL = parseFloat(style.paddingLeft) || 0;
                var padR = parseFloat(style.paddingRight) || 0;
                var available = box.clientWidth - padL - padR;
                if (available > 0) return available;
                return Math.max(0, captionTypography.boxWidth - padL - padR);
            }

            function refitCaptionBox() {
                var box = document.getElementById(captionBoxId);
                if (!box) return;
                var text = box.textContent || '';
                if (text === '') {
                    box.style.fontSize = '';
                    return;
                }
                var available = captionTextAvailableWidth(box);
                // Measures the text AS IT WOULD ACTUALLY WRAP against `available`
                // (greedy word-wrap simulation, binary-searched to the largest font
                // size that fits) rather than a single aggregate line width — see
                // captionFitFontSizeForDisplay in vimeo_playlist_logic.js for why a
                // naive "double the width for two lines" guess sizes some cues wrong.
                var fit = L.captionFitFontSizeForDisplay({
                    text: text,
                    baseFontSizePx: captionTypography.baseFontSize,
                    boxWidthPx: available,
                    maxLines: captionTypography.twoLineMode ? 2 : 1,
                    measureWidthFn: measureCaptionTextWidth,
                });
                box.style.fontSize = fit + 'px';
            }

            /**
             * BCP-47-ish lang tag for the active subtitle track, so a screen reader
             * picks the right voice for the caption text — falls back to the active
             * Website language when the track carries no explicit lang.
             * @returns {string}
             */
            function activeCaptionLangCode() {
                var cueTracks = currentItemCueTracksRaw();
                var track = cueTracks[activeCaptionTrackIndex];
                var tag = track && typeof track.lang === 'string' ? track.lang.trim() : '';
                return tag !== '' ? tag : websiteLang;
            }

            syncCaptionBox = function (events, timeMs) {
                var box = document.getElementById(captionBoxId);
                if (!box) return;
                var caption = findCaption(events, timeMs);
                var newText = caption ? L.normalizeCaptionText(caption.text) : '';
                box.textContent = newText;
                var langCode = activeCaptionLangCode();
                if (langCode) {
                    box.setAttribute('lang', langCode);
                } else {
                    box.removeAttribute('lang');
                }
                refitCaptionBox();
            };

            function syncCaptionTypography(trigger) {
                if (!videoShell) return;
                var shellRect = videoShell.getBoundingClientRect();
                var h = shellRect.height;
                if (h <= 0) return;
                var fontSize = captionFontSizePxFromVideoHeight(h);
                var w = shellRect.width;
                if (iframe) {
                    var iframeRect = iframe.getBoundingClientRect();
                    var iframeW = iframeRect.width;
                    if (iframeW > 0 && iframeW <= w) w = iframeW;
                }
                if (w <= 0 && videoStack) {
                    w = videoStack.getBoundingClientRect().width;
                }
                var fontChanged = Math.abs(fontSize - captionTypography.baseFontSize) >= 0.5;
                var widthChanged = Math.abs(w - captionTypography.boxWidth) >= 1;
                var prevTwoLine = captionTypography.twoLineMode;
                captionTypography.baseFontSize = fontSize;
                captionTypography.boxWidth = w;
                if (fontChanged) {
                    root.style.setProperty('--vpc-caption-font-size', fontSize + 'px');
                }
                if (widthChanged && w > 0) {
                    root.style.setProperty('--vpc-caption-width', w + 'px');
                }
                syncCaptionBlockLayout();
                if (fontChanged || widthChanged || prevTwoLine !== captionTypography.twoLineMode) {
                    refitCaptionBox();
                }
            }

            syncCaptionTypography('init');
            if (typeof window.ResizeObserver === 'function') {
                var captionResizeObserver = new window.ResizeObserver(function () {
                    syncCaptionTypography('resizeObserver');
                });
                captionResizeObserver.observe(videoShell);
                if (videoStack) captionResizeObserver.observe(videoStack);
            } else {
                window.addEventListener('resize', syncCaptionTypography);
            }

            var playBtn = root.querySelector('.vpc-play-pause-btn');
            var transportPlaying = false;
            var transportLoading = false;
            var transportCurrentTimeSec = 0;

            function publishTransportState(reason) {
                window.__vpcTransportState = {
                    playing: !!transportPlaying,
                    loading: !!transportLoading,
                    currentTimeSec: transportCurrentTimeSec,
                    playlistIndex: playlistIndex,
                    updatedAt: Date.now(),
                    reason: reason || '',
                };
            }

            /** Persist playback context for About/Participants transport (issue #02). */
            function savePlaybackSession(done) {
                if (typeof sessionStorage === 'undefined') {
                    if (typeof done === 'function') done();
                    return;
                }
                if (!L.shouldPersistPlaybackSession({
                    filteredMasterIndices: filteredMasterIndices,
                    isParticipantMode: isParticipantMode,
                })) {
                    if (typeof done === 'function') done();
                    return;
                }
                var timeP =
                    vimeoPlayer && typeof vimeoPlayer.getCurrentTime === 'function'
                        ? vimeoPlayer.getCurrentTime().catch(function () { return 0; })
                        : Promise.resolve(0);
                timeP.then(function (sec) {
                    var curItem = fullPlaylistItems[playlistIndex];
                    var participantSequence = '';
                    if (curItem && curItem.participant_sequence !== undefined && curItem.participant_sequence !== null) {
                        participantSequence = String(curItem.participant_sequence).trim();
                    }
                    var snap = L.buildPlaybackSessionSnapshot({
                        masterIndex: playlistIndex,
                        filterState: filterState,
                        participantName: participantName,
                        participantSequence: participantSequence,
                        shuffleMode: shuffleMode,
                        shuffledSequence: shuffledSequence,
                        shuffleStep: shuffleStep,
                        filteredCursor: filteredCursor,
                        filteredMasterIndices: filteredMasterIndices,
                        playbackTimeSec: typeof sec === 'number' ? sec : 0,
                    });
                    try {
                        sessionStorage.setItem(L.PLAYBACK_SESSION_KEY, JSON.stringify(snap));
                    } catch (e) {}
                    if (typeof done === 'function') done();
                });
            }

            window.__vpcSavePlaybackSession = savePlaybackSession;

            window.addEventListener('pagehide', function () {
                savePlaybackSession();
            });

            function setTransportPlaying(isPlaying) {
                if (!playBtn) return;
                var icon = playBtn.querySelector('.vpc-chrome-icon');
                if (playBtn.getAttribute('data-loading') === 'true') return;
                transportPlaying = !!isPlaying;
                var spaceKeyLabel = vpcString('player.transport.space_key', 'Space');
                if (isPlaying) {
                    if (icon) {
                        icon.src = playBtn.getAttribute('data-icon-pause')
                            || assetBasePath + '/img/pause_circle_80dp_007800.svg';
                    }
                    var pauseLabel = vpcString('player.transport.pause', 'Pause video') + ' (' + spaceKeyLabel + ')';
                    playBtn.setAttribute('aria-label', pauseLabel);
                    playBtn.setAttribute('title', pauseLabel);
                } else {
                    if (icon) {
                        icon.src = playBtn.getAttribute('data-icon-play')
                            || assetBasePath + '/img/play_circle_80dp_007800.svg';
                    }
                    var playLabel = vpcString('player.transport.play', 'Play video') + ' (' + spaceKeyLabel + ')';
                    playBtn.setAttribute('aria-label', playLabel);
                    playBtn.setAttribute('title', playLabel);
                }
                publishTransportState('setTransportPlaying');
                syncCollectionNavButtons();
            }

            function setTransportLoading(isLoading) {
                if (!playBtn) return;
                if (isLoading) {
                    playBtn.setAttribute('data-loading', 'true');
                    playBtn.disabled = true;
                    transportLoading = true;
                    publishTransportState('setTransportLoading:true');
                } else {
                    playBtn.removeAttribute('data-loading');
                    playBtn.disabled = false;
                    transportLoading = false;
                    publishTransportState('setTransportLoading:false');
                    refreshTransport();
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
                var bootstrapP = Promise.resolve();
                if (
                    vimeoPlayer &&
                    typeof vimeoPlayer.isReal === 'function' &&
                    !vimeoPlayer.isReal() &&
                    filteredCount() > 0 &&
                    playlistIndex >= 0
                ) {
                    bootstrapP = loadVideoMaster(playlistIndex, true, false, true);
                }
                bootstrapP
                    .then(function () {
                        return p.getPaused();
                    })
                    .then(function (paused) {
                        if (paused) {
                            markGestureActivation();
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
                playBtn.addEventListener('selectstart', function (e) {
                    e.preventDefault();
                });
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

                try {
                    sessionStorage.removeItem(L.PLAYBACK_SESSION_KEY);
                } catch (e) {}

                dismissEmptyQueueState();

                playlistIndex = plan.loadMasterIndex;
                loadVideoMaster(playlistIndex, plan.shouldAutoplay, false, true).then(function () {
                    updatePlaylistNavButtons();
                });
            }

            var resetBtn = root.querySelector('.vpc-reset-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', resetToNeutralAll);
            }

            function tryAutoplayFallback() {
                var readyPromise =
                    typeof p.ready === 'function' ? p.ready() : Promise.resolve();
                return readyPromise
                    .then(function () {
                        if (!L.shouldAutoplayWithSound(sessionPlaybackActivated, true)) {
                            return;
                        }
                        return p.getPaused().then(function (paused) {
                            if (paused) return p.play();
                            if (!paused) markPosterPlaybackStarted();
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
                    var ratio = aspectRatioFrom(dims[0], dims[1]);
                    iframe.style.aspectRatio = ratio;
                    if (videoShell) videoShell.style.aspectRatio = ratio;
                    if (posterCover) posterCover.style.aspectRatio = ratio;
                    syncCaptionTypography('aspectRatio');
                });
            }

            function resetVideoAspectPlaceholder() {
                if (iframe) iframe.style.aspectRatio = '16 / 9';
                if (videoShell) videoShell.style.aspectRatio = '';
                if (posterCover) posterCover.style.aspectRatio = '';
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
                var trackIx = L.resolveActiveCaptionTrackIndex(
                    cueTracks,
                    stickySpokenLangId,
                    subtitleLanguages
                );
                setActiveCaptionTrack(trackIx, false);
                updateCaptionPickerVisibility();
                updateAllFilterPickerReadouts();
                rebuildAllCascadingDropdowns();
            }

            function dismissEmptyQueueState() {
                var emptyEl = root.querySelector('.vpc-empty-state');
                if (emptyEl) emptyEl.classList.add('is-hidden');
            }

            function resolveLoadVideoPromise(item, wantAutoplay, forceReload) {
                if (typeof p.loadVideo !== 'function') {
                    return Promise.reject(new Error('Vimeo.Player.loadVideo unavailable'));
                }

                var vidRaw = item && item.videoId ? String(item.videoId) : '';
                var vidNum = parseInt(vidRaw, 10);
                var embedUrl = item && item.embedUrl ? String(item.embedUrl) : '';
                if (embedUrl === '' && (isNaN(vidNum) || vidRaw === '')) {
                    return Promise.reject(new Error('Vimeo playlist item lacks video id'));
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
                    if (currentRaw === vidRaw && !forceReload) {
                        return Promise.resolve();
                    }
                    /** @type {{ autoplay: boolean, preload: string, id?: number, url?: string }} */
                    var loadPayload = {
                        autoplay: wantAutoplay,
                        preload: 'auto',
                    };
                    if (embedUrl !== '') {
                        loadPayload.url = embedUrl;
                    } else {
                        loadPayload.id = vidNum;
                    }
                    return p.loadVideo(loadPayload).then(function () {});
                });
            }

            function loadVideoMaster(masterIx, autoPlayPreferred, seekToStart, forceReload) {
                var target =
                    typeof masterIx === 'number' && masterIx >= 0 ? masterIx : playlistIndex;

                videoSwitchInProgress = true;
                setTransportLoading(true);

                playlistIndex = target;
                ensureCaptionsForMasterIndex(playlistIndex);

                var item = fullPlaylistItems[playlistIndex];
                if (item) dismissEmptyQueueState();
                syncCaptionBox(eventsForSync(), 0);
                applyLoadedVideoUi();

                var vidRaw = item && item.videoId ? String(item.videoId) : '';
                var vidNum = parseInt(vidRaw, 10);
                var wantAutoplay = L.shouldAutoplayWithSound(
                    sessionPlaybackActivated,
                    autoPlayPreferred
                );
                var posterToken = beginPosterCoveredLoad(item, wantAutoplay);

                if (!wantAutoplay) {
                    forcedPauseLoads++;
                }

                return resolveLoadVideoPromise(item, wantAutoplay, !!forceReload)
                    .then(function () {
                        markPosterVideoLoaded(item, posterToken);
                        applyLoadedVideoUi();
                        /** @type {Promise<void>} */
                        var autoplayP;
                        if (wantAutoplay) {
                            autoplayP = tryAutoplayFallback();
                        } else {
                            var resetP = Promise.resolve();
                            if (
                                seekToStart &&
                                !forceReload &&
                                typeof p.setCurrentTime === 'function'
                            ) {
                                resetP = p.setCurrentTime(0).catch(function () {});
                            }
                            autoplayP = resetP
                                .then(function () {
                                    return p.pause().catch(function () {});
                                })
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
                        syncCollectionNavButtons();
                        syncCaptionBox(eventsForSync(), 0);
                        videoSwitchInProgress = false;
                        setTransportLoading(false);
                        refreshTransport();
                        savePlaybackSession();
                        if (pendingPlaybackTimeSec > 0 && typeof p.setCurrentTime === 'function') {
                            var seekSec = pendingPlaybackTimeSec;
                            pendingPlaybackTimeSec = 0;
                            return p.setCurrentTime(seekSec).catch(function () {});
                        }
                    })
                    .catch(function (e) {
                        if (!wantAutoplay) {
                            forcedPauseLoads = Math.max(0, forcedPauseLoads - 1);
                        }
                        videoSwitchInProgress = false;
                        setTransportLoading(false);
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
                // Prev/next always visible; disabled at ends or on single-video playlists.
                prevBtn.classList.remove('vpc-nav-hidden');
                nextBtn.classList.remove('vpc-nav-hidden');
                if (shuffleMode) {
                    prevBtn.disabled = fc <= 1 || shuffleStep <= 0;
                    nextBtn.disabled = fc <= 1 || shuffleStep >= fc - 1;
                } else {
                    prevBtn.disabled = fc <= 1 || filteredCursor <= 0;
                    nextBtn.disabled = fc <= 1 || filteredCursor >= fc - 1;
                }
            }

            /**
             * End of playlist: pause on first video of current playback sequence (issue #05).
             */
            function pauseAtPlaylistHead() {
                var plan = L.planEndOfPlaylist({
                    shuffleMode: shuffleMode,
                    shuffledSequence: shuffledSequence,
                    filteredCount: filteredCount(),
                    filteredMasterIndices: filteredMasterIndices,
                });
                if (!plan) return;
                shuffleStep = plan.shuffleStep;
                filteredCursor = plan.filteredCursor;
                loadVideoMaster(
                    plan.loadMasterIndex,
                    plan.shouldAutoplay,
                    true,
                    plan.forceReload
                ).then(function () {
                    updatePlaylistNavButtons();
                    syncCollectionNavButtons();
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
                    pauseAtPlaylistHead();
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
             * Nav state for an R3 collection button (D21). label empty = use generic.
             * @param {string} collectionKey  e.g. "participants", "tags"
             * @returns {{ label: string, isActive: boolean }}
             */
            function getCollectionNavState(collectionKey) {
                if (collectionKey === 'participants') {
                    var item = fullPlaylistItems[playlistIndex];
                    var currentVideoParticipant = item && typeof item.participant === 'string'
                        ? item.participant.trim()
                        : '';
                    var currentVideoParticipantSequence = '';
                    if (item && item.participant_sequence !== undefined && item.participant_sequence !== null) {
                        currentVideoParticipantSequence = String(item.participant_sequence).trim();
                    }
                    return L.resolveParticipantsNavState({
                        isParticipantMode: isParticipantMode,
                        participantName: participantName,
                        onPlayerPage: true,
                        isPlaying: transportPlaying,
                        currentVideoParticipant: currentVideoParticipant,
                        currentVideoParticipantSequence: currentVideoParticipantSequence,
                    });
                }
                if (collectionKey === 'tags') {
                    return { label: '', isActive: false };
                }
                return { label: '', isActive: false };
            }

            function chromeBtnLabelEl(btn) {
                return btn.querySelector('.vpc-chrome-btn__label');
            }

            /**
             * Set chrome face text. Optional shortLabel is shown at maketa ≤1024 via CSS.
             * @param {HTMLElement} btn
             * @param {string} label
             * @param {string} [shortLabel]
             */
            function setChromeBtnLabel(btn, label, shortLabel) {
                var el = chromeBtnLabelEl(btn);
                var short = shortLabel != null ? shortLabel : label;
                if (!el) {
                    btn.textContent = label;
                    return;
                }
                var fullEl = el.querySelector('.vpc-chrome-btn__label-full');
                var shortEl = el.querySelector('.vpc-chrome-btn__label-short');
                if (fullEl && shortEl) {
                    fullEl.textContent = label;
                    shortEl.textContent = short;
                    return;
                }
                el.textContent = '';
                fullEl = document.createElement('span');
                fullEl.className = 'vpc-chrome-btn__label-full';
                fullEl.textContent = label;
                shortEl = document.createElement('span');
                shortEl.className = 'vpc-chrome-btn__label-short';
                shortEl.textContent = short;
                el.appendChild(fullEl);
                el.appendChild(shortEl);
            }

            function getChromeBtnLabel(btn) {
                var el = chromeBtnLabelEl(btn);
                if (!el) {
                    return btn.textContent.trim();
                }
                var fullEl = el.querySelector('.vpc-chrome-btn__label-full');
                if (fullEl) {
                    return fullEl.textContent.trim();
                }
                return el.textContent.trim();
            }

            /**
             * Sync all R3 collection nav buttons: green + name when active, generic label otherwise (D21).
             */
            function syncChromeButtonWidths() {
                if (typeof window.vpcSyncChromeButtonWidths === 'function') {
                    window.vpcSyncChromeButtonWidths();
                }
            }

            function syncCollectionNavButtons() {
                root.querySelectorAll('.preview-site-nav__btn[data-collection]').forEach(function (btn) {
                    var key = btn.getAttribute('data-collection') || '';
                    var navState = getCollectionNavState(key);
                    var genericLabel = btn.getAttribute('data-generic-label');
                    if (!genericLabel) {
                        genericLabel = getChromeBtnLabel(btn);
                        btn.setAttribute('data-generic-label', genericLabel);
                    }
                    setChromeBtnLabel(btn, navState.label || genericLabel);
                    // Keep the accessible name in step with the visible label, which may
                    // be a Participant's name rather than the generic route label.
                    if (btn.hasAttribute('data-i18n-aria')) {
                        var displayed = navState.label || genericLabel;
                        var navHint = btn.getAttribute('data-i18n-hint') || '';
                        var navAria = navHint ? displayed + ' (' + navHint + ')' : displayed;
                        btn.setAttribute('aria-label', navAria);
                        if (btn.hasAttribute('title')) btn.setAttribute('title', navAria);
                    }
                    if (navState.isActive) {
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-current', 'true');
                    } else {
                        btn.classList.remove('is-active');
                        btn.removeAttribute('aria-current');
                    }
                    if (key === 'participants' && navState.label) {
                        btn.classList.add('preview-site-nav__btn--person-name');
                    } else if (key === 'participants') {
                        btn.classList.remove('preview-site-nav__btn--person-name');
                    }
                });
                syncDeafHearingButton();
                syncChromeButtonWidths();
            }

            /**
             * DEAF+HEARING toggle chrome: green + aria-pressed when tag facet pinned (DH2/DH13).
             */
            function syncDeafHearingButton() {
                var btn = root.querySelector('.vpc-deaf-hearing-btn');
                if (!btn) return;
                var on = L.isTagPinned(filterState);
                if (on) {
                    btn.classList.add('is-active');
                    btn.setAttribute('aria-pressed', 'true');
                } else {
                    btn.classList.remove('is-active');
                    btn.setAttribute('aria-pressed', 'false');
                }
            }

            /**
             * Update one R2 filter picker: live readout, fixed green, or green when
             * other filters leave only one possible value for the live facet (D14′, D21).
             * @param {string} facet
             */
            function updateFilterPickerReadout(facet) {
                var pickerEl = root.querySelector('.vpc-picker[data-picker="' + facet + '"]');
                if (!pickerEl) return;

                var btn = pickerEl.querySelector('.vpc-picker-btn');
                if (!btn) return;

                var genericLabel = btn.getAttribute('data-generic-label') || facet;
                var item = fullPlaylistItems[playlistIndex];
                var derivedActive = L.isFacetNarrowedByOtherFilters(
                    fullPlaylistItems,
                    filterState,
                    facet
                );
                var readout = L.resolveFilterPickerReadout(
                    item,
                    facet,
                    filterState,
                    filterOptionCatalog[facet] || [],
                    genericLabel,
                    derivedActive
                );

                setChromeBtnLabel(btn, readout.label, readout.short_label);
                pickerEl.setAttribute('data-active', readout.active ? 'true' : 'false');
            }

            function updateAllFilterPickerReadouts() {
                ['sign_language', 'edition', 'typology'].forEach(updateFilterPickerReadout);
                syncDeafHearingButton();
                syncChromeButtonWidths();
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
                var dropdownIdBase = dropdown.id || facet;
                var optN = 0;

                var clearLi = document.createElement('li');
                clearLi.setAttribute('role', 'option');
                clearLi.id = dropdownIdBase + '__opt-' + optN++;
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
                    li.id = dropdownIdBase + '__opt-' + optN++;
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

            // ── In-session Website language switch (no page reload) ───────────
            // The Vimeo iframe is never touched here: playback, user activation,
            // filter facets, shuffle order and Playlist cursor all simply continue.

            /**
             * Repaint every server-rendered string carrying a data-i18n-* marker.
             * Runs before the picker re-renders, which read data-generic-label back.
             */
            function applyI18nMarkedStrings() {
                root.querySelectorAll('[data-i18n-text]').forEach(function (el) {
                    el.textContent = vpcString(el.getAttribute('data-i18n-text'), el.textContent);
                });

                root.querySelectorAll('[data-i18n-generic]').forEach(function (el) {
                    el.setAttribute(
                        'data-generic-label',
                        vpcString(
                            el.getAttribute('data-i18n-generic'),
                            el.getAttribute('data-generic-label') || ''
                        )
                    );
                });

                root.querySelectorAll('[data-i18n-aria]').forEach(function (el) {
                    var label = vpcString(el.getAttribute('data-i18n-aria'), '');
                    // Keyboard hint suffix mirrors the composition in bottom_bar.php:
                    // "<label> (<hint>)". The hint is a physical key except Space,
                    // whose name is itself localized and so arrives as a key.
                    var hintKey = el.getAttribute('data-i18n-hint-key');
                    var hint = hintKey
                        ? vpcString(hintKey, '')
                        : el.getAttribute('data-i18n-hint') || '';
                    var full = hint ? label + ' (' + hint + ')' : label;
                    if (!label) return;
                    el.setAttribute('aria-label', full);
                    if (el.hasAttribute('title')) el.setAttribute('title', full);
                });
            }

            /** Move the language picker's own face and selection to the new language. */
            function syncLanguagePicker(langId) {
                var pickerEl = root.querySelector('.vpc-picker[data-picker="language"]');
                if (!pickerEl) return;

                var selectedLabel = '';
                pickerEl.querySelectorAll('.vpc-picker-option').forEach(function (li) {
                    var isSelected = li.getAttribute('data-lang-id') === langId;
                    li.setAttribute('aria-selected', isSelected ? 'true' : 'false');
                    if (isSelected) selectedLabel = li.textContent;
                });

                var btn = pickerEl.querySelector('.vpc-picker-btn');
                if (btn && selectedLabel) {
                    setChromeBtnLabel(btn, selectedLabel);
                }
                pickerEl.setAttribute('data-active', langId !== 'en' ? 'true' : 'false');
            }

            /**
             * @param {{ lang: string, dir: string, strings: Object, filter_options: Object }} payload
             * @param {{ subtitleLangId: string, captionTrackIndex: number, url: string }} plan
             */
            function applyLocalePayload(payload, plan) {
                strings = payload.strings && typeof payload.strings === 'object' ? payload.strings : {};
                websiteLang = payload.lang;
                rebuildFilterClearLabels();

                var opts = payload.filter_options || {};
                ['sign_language', 'edition', 'typology'].forEach(function (facet) {
                    if (Array.isArray(opts[facet])) {
                        filterOptionCatalog[facet] = opts[facet];
                    }
                });

                document.documentElement.setAttribute('lang', payload.lang);
                document.documentElement.setAttribute('dir', payload.dir);

                applyI18nMarkedStrings();
                syncLanguagePicker(payload.lang);
                updateAllFilterPickerReadouts();
                rebuildAllCascadingDropdowns();
                syncCollectionNavButtons();

                // Website language drives Subtitle track (issue #19). Sticky id is set
                // first so setActiveCaptionTrack does not overwrite it from the track.
                stickySpokenLangId = plan.subtitleLangId;
                setActiveCaptionTrack(plan.captionTrackIndex, false);

                if (window.history && typeof window.history.replaceState === 'function') {
                    try {
                        window.history.replaceState(null, '', plan.url);
                    } catch (e) {}
                }
            }

            /**
             * Switch Website language in session. Resolves false when nothing changed,
             * and rejects when the payload could not be fetched — the caller then falls
             * back to navigation, so this can never leave the page half-translated.
             * @param {string} targetLang
             * @returns {Promise<boolean>}
             */
            function applyWebsiteLanguage(targetLang) {
                var plan;
                try {
                    plan = L.planWebsiteLanguageSwitch({
                        currentLang: websiteLang,
                        targetLang: targetLang,
                        cueTracks: currentItemCueTracksRaw(),
                        subtitleLanguages: subtitleLanguages,
                        currentUrl:
                            window.location.pathname + window.location.search + window.location.hash,
                    });
                } catch (e) {
                    // Never throw synchronously: the caller's .catch() is what falls back
                    // to navigation, and it can only run if this returns a promise.
                    return Promise.reject(e);
                }
                if (!plan.changed) return Promise.resolve(false);

                return fetch(
                    localeApiPath + '?lang=' + encodeURIComponent(targetLang),
                    { credentials: 'same-origin' }
                )
                    .then(function (res) {
                        if (!res.ok) throw new Error('locale payload ' + res.status);
                        return res.json();
                    })
                    .then(function (payload) {
                        applyLocalePayload(payload, plan);
                        return true;
                    });
            }

            window.__vpcApplyWebsiteLanguage = applyWebsiteLanguage;

            /**
             * Apply a filter change with keep-if-matches playback (D22) and cascade (D17′).
             * Tag facet uses membership + DH15b empty-AND fallback.
             * @param {string} facet
             * @param {string|null} value
             */
            function applyFilterChange(facet, value) {
                var newValue = value || null;
                var previousValue = filterState[facet] !== undefined ? filterState[facet] : null;

                if (L.shouldClearCollectionOnFilterFix(isParticipantMode, newValue)) {
                    isParticipantMode = false;
                    participantName = '';
                    syncCollectionNavButtons();
                }

                filterState[facet] = newValue;

                if (facet === 'tag' && newValue) {
                    filterState = L.resolveTagToggleOnFilterState(filterState, fullPlaylistItems);
                }

                // Issue #01: clearing back to neutral (every facet unset) behaves like
                // Reset — a fresh base playlist (one random Participant's Video per
                // city), not a widen-and-keep-current of the full catalog.
                if (L.isFilterStateNeutral(filterState)) {
                    var basePlan = L.planResetToNeutralAll({ fullPlaylistItems: fullPlaylistItems });
                    if (basePlan) {
                        filterState = basePlan.filterState;
                        filteredMasterIndices = basePlan.filteredMasterIndices;
                        filteredCursor = basePlan.filteredCursor;
                        shuffleStep = basePlan.shuffleStep;
                        shuffledSequence = basePlan.shuffledSequence;
                        shuffleMode = basePlan.shuffleMode;

                        updateAllFilterPickerReadouts();
                        rebuildAllCascadingDropdowns();
                        savePlaybackSession();

                        playlistIndex = basePlan.loadMasterIndex;
                        loadVideoMaster(playlistIndex, basePlan.shouldAutoplay).then(function () {
                            updatePlaylistNavButtons();
                            savePlaybackSession();
                        });
                        return;
                    }
                }

                var plan = L.planFilterPlaylistRebuild({
                    fullPlaylistItems: fullPlaylistItems,
                    filterState: filterState,
                    currentMasterIndex: playlistIndex,
                    shuffleMode: shuffleMode,
                });

                if (!plan) {
                    // DH7: revert the R2 pin that emptied the set; keep tag if pinned.
                    filterState[facet] = previousValue;
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

                updateAllFilterPickerReadouts();
                rebuildAllCascadingDropdowns();
                savePlaybackSession();

                if (plan.keepCurrentVideo) {
                    updatePlaylistNavButtons();
                    syncCollectionNavButtons();
                    tryAutoplayFallback();
                    return;
                }

                playlistIndex = plan.loadMasterIndex;
                loadVideoMaster(playlistIndex, true).then(function () {
                    updatePlaylistNavButtons();
                    savePlaybackSession();
                });
            }

            function toggleDeafHearingFilter() {
                markGestureActivation();
                if (L.isTagPinned(filterState)) {
                    applyFilterChange('tag', null);
                    return;
                }
                applyFilterChange('tag', L.DEAF_HEARING_TAG);
            }

            /**
             * Close all custom picker dropdowns in this player instance.
             */
            function closeAllPickers() {
                root.querySelectorAll('.vpc-picker-dropdown').forEach(function (dd) {
                    dd.hidden = true;
                    dd.removeAttribute('aria-activedescendant');
                    dd.querySelectorAll('.vpc-picker-option--active').forEach(function (o) {
                        o.classList.remove('vpc-picker-option--active');
                    });
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
                if (!facet || facet === 'language') return;

                var btn = /** @type {HTMLButtonElement|null} */ (pickerEl.querySelector('.vpc-picker-btn'));
                var dropdown = /** @type {HTMLElement|null} */ (pickerEl.querySelector('.vpc-picker-dropdown'));
                if (!btn || !dropdown) return;

                // Rebuilt on every open (cascading options depend on other active
                // filters), so option elements — and thus activeIndex — must always
                // be re-read from the DOM rather than cached across opens.
                var activeIndex = -1;

                function currentOptions() {
                    return Array.prototype.slice.call(dropdown.querySelectorAll('.vpc-picker-option'));
                }

                function setActiveIndex(index) {
                    var options = currentOptions();
                    if (index < 0 || index >= options.length) return;
                    options.forEach(function (o) { o.classList.remove('vpc-picker-option--active'); });
                    activeIndex = index;
                    var el = options[activeIndex];
                    el.classList.add('vpc-picker-option--active');
                    dropdown.setAttribute('aria-activedescendant', el.id);
                    if (typeof el.scrollIntoView === 'function') el.scrollIntoView({ block: 'nearest' });
                }

                function selectedOptionIndex() {
                    var options = currentOptions();
                    for (var i = 0; i < options.length; i++) {
                        if (options[i].getAttribute('aria-selected') === 'true') return i;
                    }
                    return 0;
                }

                function activateOption(target) {
                    var value = target.getAttribute('data-value') || '';
                    var isClear = value === '' || target.classList.contains('vpc-picker-clear');

                    currentOptions().forEach(function (o) {
                        o.setAttribute('aria-selected', 'false');
                    });
                    target.setAttribute('aria-selected', 'true');
                    closeAllPickers();

                    markGestureActivation();
                    applyFilterChange(facet, isClear ? null : value);
                }

                // Toggle dropdown open/close
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    rebuildCascadingDropdown(facet);
                    var isOpen = !dropdown.hidden;
                    closeAllPickers();
                    if (!isOpen) {
                        dropdown.hidden = false;
                        btn.setAttribute('aria-expanded', 'true');
                        setActiveIndex(selectedOptionIndex());
                        dropdown.focus();
                    }
                });

                // Keyboard on the closed trigger button: Up/Down/Enter/Space open it.
                btn.addEventListener('keydown', function (e) {
                    var action = L.resolvePickerListboxKeyAction({
                        key: e.key,
                        activeIndex: activeIndex,
                        optionCount: currentOptions().length,
                        isOpen: !dropdown.hidden,
                    });
                    if (action.action === 'open') {
                        e.preventDefault();
                        btn.click();
                    }
                });

                // Option click — delegation for dynamically rebuilt filter dropups
                dropdown.addEventListener('click', function (e) {
                    var target = /** @type {HTMLElement|null} */ (e.target);
                    if (!target || !target.classList.contains('vpc-picker-option')) return;
                    e.stopPropagation();
                    activateOption(target);
                });

                // Keyboard on the open listbox: arrows/Home/End move, Enter/Space
                // select, Escape closes.
                dropdown.addEventListener('keydown', function (e) {
                    var action = L.resolvePickerListboxKeyAction({
                        key: e.key,
                        activeIndex: activeIndex,
                        optionCount: currentOptions().length,
                        isOpen: true,
                    });
                    if (action.action === 'move') {
                        e.preventDefault();
                        setActiveIndex(action.index);
                    } else if (action.action === 'close') {
                        e.preventDefault();
                        closeAllPickers();
                        btn.focus();
                    } else if (action.action === 'select') {
                        e.preventDefault();
                        var options = currentOptions();
                        var el = options[action.index];
                        if (el) activateOption(el);
                    }
                });
            }

            // Wire up all R2 pickers in this instance
            root.querySelectorAll('.vpc-picker').forEach(initPicker);

            var deafHearingBtn = root.querySelector('.vpc-deaf-hearing-btn');
            if (deafHearingBtn && !deafHearingBtn.disabled) {
                deafHearingBtn.addEventListener('click', function () {
                    toggleDeafHearingFilter();
                });
            }

            rebuildAllCascadingDropdowns();
            updateAllFilterPickerReadouts();

            // D18: Update Participants nav button label when in participant mode.
            syncCollectionNavButtons();

            // Close pickers when clicking outside
            document.addEventListener('click', function () {
                closeAllPickers();
            });

            // Unknown/empty Participant collection: stay on empty queue (do not load ALL poster).
            if (filteredCount() > 0 && playlistIndex >= 0) {
                loadVideoMaster(playlistIndex, participantGestureCarried || sessionRestoreAutoplay)
                    .then(function () {})
                    .catch(function () {});
            } else {
                // SSR may still have a technical ALL poster; blank it so we do not show unrelated videos.
                var emptyIframe = document.getElementById(iframeId);
                if (emptyIframe) {
                    emptyIframe.removeAttribute('src');
                }
                // Keep the empty player state visible: cover any leftover frame with the
                // load scrim and hide a stale poster (matches SSR's rendered empty state).
                if (loadScrim) loadScrim.classList.remove('is-hidden');
                if (posterCover) posterCover.classList.add('is-hidden');
                updatePlaylistNavButtons();
                syncCollectionNavButtons();
            }
            p.on('play', function () {
                if (forcedPauseLoads > 0) {
                    p.pause().catch(function () {});
                    setTransportPlaying(false);
                    return;
                }
                visitorStartedPlayback = true;
                markGestureActivation();
                setTransportPlaying(true);
                savePlaybackSession();
            });
            p.on('playing', function () {
                markPosterPlaybackStarted();
            });
            p.on('bufferstart', function () {
                coverBuffering = true;
                // Cover Vimeo's gray loader during the load window and a short post-reveal grace.
                if (loadScrim && Date.now() < suppressGrayUntil) {
                    loadScrim.classList.remove('is-hidden');
                }
            });
            p.on('bufferend', function () {
                coverBuffering = false;
                p.getCurrentTime()
                    .then(function (seconds) {
                        revealPosterAfterPlaybackProgress(seconds);
                    })
                    .catch(function () {});
            });
            p.on('pause', function () {
                setTransportPlaying(false);
                p.getCurrentTime().then(function (seconds) {
                    if (typeof seconds === 'number' && isFinite(seconds) && seconds >= 0) {
                        transportCurrentTimeSec = seconds;
                        publishTransportState('pauseCurrentTime');
                    }
                    syncVimeoCaptionBoxes(seconds);
                });
                savePlaybackSession();
            });
            p.on('ended', function () {
                setTransportPlaying(false);
                advanceOnEnded();
            });

            p.on('timeupdate', function (data) {
                if (data && typeof data.seconds === 'number' && isFinite(data.seconds) && data.seconds >= 0) {
                    transportCurrentTimeSec = data.seconds;
                }
                revealPosterAfterPlaybackProgress(data.seconds);
                syncVimeoCaptionBoxes(data.seconds);
            });
            p.on('seeked', function () {
                p.getCurrentTime().then(syncVimeoCaptionBoxes);
            });

            var aboutNavLink = root.querySelector('[data-route="about"]');
            var participantsNavLink = root.querySelector('[data-route="participants"]');
            function clearParticipantNavigationSession() {
                try {
                    sessionStorage.removeItem(L.PLAYBACK_SESSION_KEY);
                    sessionStorage.removeItem(L.NAV_INTENT_KEY);
                } catch (e) {}
            }
            if (participantsNavLink) participantsNavLink.addEventListener('click', function () {
                clearParticipantNavigationSession();
            });

            document.addEventListener('keydown', function (e) {
                var action = L.resolveTransportShortcutAction({
                    key: e.key,
                    ctrlKey: e.ctrlKey,
                    altKey: e.altKey,
                    metaKey: e.metaKey,
                    shiftKey: e.shiftKey,
                    activeElement: document.activeElement,
                });
                if (!action) return;
                e.preventDefault();
                if (action === 'play-pause' && playBtn) playBtn.click();
                else if (action === 'prev' && prevTransport) prevTransport.click();
                else if (action === 'next' && nextTransport) nextTransport.click();
                else if (action === 'reset' && resetBtn) resetBtn.click();
                else if (action === 'deaf-hearing' && deafHearingBtn) deafHearingBtn.click();
                else if (action === 'about' && aboutNavLink) aboutNavLink.click();
                else if (action === 'participants' && participantsNavLink) participantsNavLink.click();
            });
        }

        ensureVimeoSdk(attachPlayer, function () {
            showVpcPlayerError(
                root,
                vpcString('player.error.sdk_load_failed', 'Video player could not load. Please check your connection and try again.')
            );
        });
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
