(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.VpcPlaylistLogic = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /**
     * Resolve filtered cursor from shuffle step, or null when sequence is invalid.
     * @param {unknown} sequence
     * @param {number} step
     * @param {number} filteredCount
     * @returns {number | null}
     */
    function filteredCursorFromShuffleStep(sequence, step, filteredCount) {
        if (!Array.isArray(sequence) || sequence.length < filteredCount) return null;
        if (step < 0 || step >= filteredCount) return null;
        var cursor = sequence[step];
        if (typeof cursor !== 'number' || cursor < 0 || cursor >= filteredCount) return null;
        return cursor;
    }

    /**
     * Fisher–Yates shuffle of indices 0..count-1.
     * @param {number} count
     * @param {() => number} [randomFn]
     * @returns {number[]}
     */
    function buildShuffledSequence(count, randomFn) {
        var random = randomFn || Math.random;
        var arr = [];
        var i;
        for (i = 0; i < count; i++) arr.push(i);
        for (i = count - 1; i > 0; i--) {
            var j = Math.floor(random() * (i + 1));
            var t = arr[i];
            arr[i] = arr[j];
            arr[j] = t;
        }
        return arr;
    }

    /**
     * Default visit: shuffle on, fresh sequence, first entry at step 0.
     * @param {number} filteredCount
     * @param {() => number} [randomFn]
     * @returns {{ shuffleMode: boolean, shuffledSequence: number[], shuffleStep: number, filteredCursor: number }}
     */
    function createDefaultShuffleState(filteredCount, randomFn) {
        if (filteredCount <= 0) {
            return {
                shuffleMode: false,
                shuffledSequence: [],
                shuffleStep: 0,
                filteredCursor: 0,
            };
        }
        var shuffledSequence = buildShuffledSequence(filteredCount, randomFn);
        return {
            shuffleMode: true,
            shuffledSequence: shuffledSequence,
            shuffleStep: 0,
            filteredCursor: shuffledSequence[0],
        };
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
            var shuffledCursor = filteredCursorFromShuffleStep(opts.shuffledSequence, nextStep, fc);
            if (shuffledCursor === null) return null;
            return {
                shuffleStep: nextStep,
                filteredCursor: shuffledCursor,
            };
        }

        var nextCursor = opts.filteredCursor + 1;
        if (nextCursor >= fc) return null;
        return { shuffleStep: opts.shuffleStep, filteredCursor: nextCursor };
    }

    /**
     * Whether end-of-video should advance within the Playlist.
     * @param {boolean} visitorStartedPlayback
     * @returns {boolean}
     */
    function shouldAdvanceOnEnded(visitorStartedPlayback) {
        return !!visitorStartedPlayback;
    }

    /** sessionStorage key set when a participant card is clicked (D23). */
    var GESTURE_STORAGE_KEY = 'vpc-gesture-activated';

    /**
     * Whether programmatic autoplay with sound is allowed (D12′).
     * @param {boolean} sessionActivated
     * @returns {boolean}
     */
    function isSessionSoundAllowed(sessionActivated) {
        return !!sessionActivated;
    }

    /**
     * Resolve whether a load/advance should autoplay (never before first gesture).
     * @param {boolean} sessionActivated
     * @param {boolean|undefined} autoPlayPreferred  false = caller forces pause
     * @returns {boolean}
     */
    function shouldAutoplayWithSound(sessionActivated, autoPlayPreferred) {
        if (autoPlayPreferred === false) return false;
        return isSessionSoundAllowed(sessionActivated);
    }

    /**
     * Participant→home carry: click on /preview/participants counts as a gesture (D23).
     * @param {boolean} isParticipantMode
     * @param {string|null|undefined} storageValue  sessionStorage value for GESTURE_STORAGE_KEY
     * @returns {boolean}
     */
    function resolveParticipantGestureCarry(isParticipantMode, storageValue) {
        if (!isParticipantMode) return false;
        return storageValue === '1';
    }

    /**
     * Normalize BCP-47-ish lang tag for comparison.
     * @param {string} lang
     * @returns {string}
     */
    function normalizeSpokenLangTag(lang) {
        return String(lang || '').trim().toLowerCase().replace(/_/g, '-');
    }

    /**
     * Map a caption track lang to studio-config subtitle_languages id (D16′).
     * @param {string} trackLang
     * @param {Array<{ id?: string, label?: string, vimeo_code?: string }>} subtitleLanguages
     * @returns {string}
     */
    function resolveSpokenLangId(trackLang, subtitleLanguages) {
        var norm = normalizeSpokenLangTag(trackLang);
        if (!norm) return '';
        var list = Array.isArray(subtitleLanguages) ? subtitleLanguages : [];
        var i;
        for (i = 0; i < list.length; i++) {
            var entry = list[i];
            if (entry && entry.id && normalizeSpokenLangTag(entry.id) === norm) {
                return entry.id;
            }
        }
        for (i = 0; i < list.length; i++) {
            var e = list[i];
            if (e && e.vimeo_code && normalizeSpokenLangTag(e.vimeo_code) === norm) {
                return e.id || '';
            }
        }
        var base = norm.split('-')[0];
        if (!base) return '';
        for (i = 0; i < list.length; i++) {
            var item = list[i];
            if (!item || !item.id) continue;
            if (normalizeSpokenLangTag(item.id) === base) return item.id;
            if (item.vimeo_code && normalizeSpokenLangTag(item.vimeo_code) === base) {
                return item.id;
            }
        }
        return '';
    }

    /**
     * Studio label for a subtitle language id.
     * @param {string} spokenLangId
     * @param {Array<{ id?: string, label?: string }>} subtitleLanguages
     * @returns {string}
     */
    function spokenLangLabel(spokenLangId, subtitleLanguages) {
        var list = Array.isArray(subtitleLanguages) ? subtitleLanguages : [];
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].id === spokenLangId) {
                return list[i].label || spokenLangId;
            }
        }
        return spokenLangId;
    }

    /**
     * Spoken-language options for the current video's caption tracks (region variants collapsed).
     * @param {Array<{ lang?: string }>} cueTracks
     * @param {Array<{ id?: string, label?: string, vimeo_code?: string }>} subtitleLanguages
     * @returns {Array<{ spokenLangId: string, label: string, trackIndex: number }>}
     */
    function buildSpokenOptionsForTracks(cueTracks, subtitleLanguages) {
        var options = [];
        var seen = {};
        var tracks = Array.isArray(cueTracks) ? cueTracks : [];
        for (var i = 0; i < tracks.length; i++) {
            var t = tracks[i];
            if (!t) continue;
            var spokenId = resolveSpokenLangId(t.lang || '', subtitleLanguages);
            if (!spokenId || seen[spokenId]) continue;
            seen[spokenId] = true;
            options.push({
                spokenLangId: spokenId,
                label: spokenLangLabel(spokenId, subtitleLanguages),
                trackIndex: i,
            });
        }
        return options;
    }

    /**
     * First track index matching sticky spoken language, or -1.
     * @param {Array<{ lang?: string }>} cueTracks
     * @param {string} spokenLangId
     * @param {Array<{ id?: string, label?: string, vimeo_code?: string }>} subtitleLanguages
     * @returns {number}
     */
    function pickTrackIndexForSpokenLang(cueTracks, spokenLangId, subtitleLanguages) {
        if (!spokenLangId) return -1;
        var tracks = Array.isArray(cueTracks) ? cueTracks : [];
        for (var i = 0; i < tracks.length; i++) {
            var t = tracks[i];
            if (!t) continue;
            if (resolveSpokenLangId(t.lang || '', subtitleLanguages) === spokenLangId) {
                return i;
            }
        }
        return -1;
    }

    /**
     * Active caption track: sticky spoken language when available, else first track (D16′).
     * @param {Array<{ lang?: string }>} cueTracks
     * @param {string} stickySpokenLangId
     * @param {Array<{ id?: string, label?: string, vimeo_code?: string }>} subtitleLanguages
     * @returns {number}
     */
    function resolveActiveCaptionTrackIndex(cueTracks, stickySpokenLangId, subtitleLanguages) {
        var tracks = Array.isArray(cueTracks) ? cueTracks : [];
        if (tracks.length === 0) return 0;
        if (stickySpokenLangId) {
            var stickyIdx = pickTrackIndexForSpokenLang(tracks, stickySpokenLangId, subtitleLanguages);
            if (stickyIdx >= 0) return stickyIdx;
        }
        return 0;
    }

    return {
        filteredCursorFromShuffleStep: filteredCursorFromShuffleStep,
        buildShuffledSequence: buildShuffledSequence,
        createDefaultShuffleState: createDefaultShuffleState,
        nextPlaylistStep: nextPlaylistStep,
        shouldAdvanceOnEnded: shouldAdvanceOnEnded,
        GESTURE_STORAGE_KEY: GESTURE_STORAGE_KEY,
        isSessionSoundAllowed: isSessionSoundAllowed,
        shouldAutoplayWithSound: shouldAutoplayWithSound,
        resolveParticipantGestureCarry: resolveParticipantGestureCarry,
        normalizeSpokenLangTag: normalizeSpokenLangTag,
        resolveSpokenLangId: resolveSpokenLangId,
        spokenLangLabel: spokenLangLabel,
        buildSpokenOptionsForTracks: buildSpokenOptionsForTracks,
        pickTrackIndexForSpokenLang: pickTrackIndexForSpokenLang,
        resolveActiveCaptionTrackIndex: resolveActiveCaptionTrackIndex,
    };
}));
