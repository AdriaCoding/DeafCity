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
    };
}));
