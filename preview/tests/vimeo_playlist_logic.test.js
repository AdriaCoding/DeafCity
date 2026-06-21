'use strict';

var assert = require('assert');
var logic = require('../js/vimeo_playlist_logic.js');

// Default shuffle on visit: fresh permutation, step 0, cursor at sequence[0]
(function () {
    var state = logic.createDefaultShuffleState(5);
    assert.strictEqual(state.shuffleMode, true);
    assert.strictEqual(state.shuffleStep, 0);
    assert.strictEqual(state.shuffledSequence.length, 5);
    assert.strictEqual(state.filteredCursor, state.shuffledSequence[0]);
    var seen = {};
    state.shuffledSequence.forEach(function (n) {
        assert.ok(n >= 0 && n < 5);
        seen[n] = true;
    });
    assert.strictEqual(Object.keys(seen).length, 5);
})();

// End-of-video advance only after visitor started playback
assert.strictEqual(logic.shouldAdvanceOnEnded(false), false);
assert.strictEqual(logic.shouldAdvanceOnEnded(true), true);

// Shuffle mode: next step follows shuffled sequence
(function () {
    var step = logic.nextPlaylistStep({
        shuffleMode: true,
        filteredCursor: 2,
        shuffleStep: 0,
        filteredCount: 3,
        shuffledSequence: [2, 0, 1],
    });
    assert.deepStrictEqual(step, { shuffleStep: 1, filteredCursor: 0 });
})();

// Last video in shuffle: no next step (triggers end-of-ALL-playlist reset in JS)
assert.strictEqual(
    logic.nextPlaylistStep({
        shuffleMode: true,
        filteredCursor: 1,
        shuffleStep: 2,
        filteredCount: 3,
        shuffledSequence: [2, 0, 1],
    }),
    null
);

// Linear mode: next step follows cursor order
(function () {
    var step = logic.nextPlaylistStep({
        shuffleMode: false,
        filteredCursor: 1,
        shuffleStep: 0,
        filteredCount: 3,
        shuffledSequence: [],
    });
    assert.deepStrictEqual(step, { shuffleStep: 0, filteredCursor: 2 });
})();

// Linear mode: last video → null (end of playlist)
assert.strictEqual(
    logic.nextPlaylistStep({
        shuffleMode: false,
        filteredCursor: 2,
        shuffleStep: 0,
        filteredCount: 3,
        shuffledSequence: [],
    }),
    null
);

// buildShuffledSequence: correct length and all indices present
(function () {
    var seq = logic.buildShuffledSequence(6);
    assert.strictEqual(seq.length, 6);
    var seen = {};
    seq.forEach(function (n) {
        assert.ok(n >= 0 && n < 6);
        seen[n] = true;
    });
    assert.strictEqual(Object.keys(seen).length, 6);
})();

// buildShuffledSequence with custom random: deterministic test
(function () {
    // A fixed random fn that always returns 0 (picks the last remaining element each time)
    var seq = logic.buildShuffledSequence(4, function () { return 0; });
    assert.strictEqual(seq.length, 4);
    // All indices 0-3 must appear exactly once
    var sorted = seq.slice().sort(function (a, b) { return a - b; });
    assert.deepStrictEqual(sorted, [0, 1, 2, 3]);
})();

// buildShuffledSequence: over many runs, first element varies (randomness sanity)
(function () {
    var firstSeen = {};
    for (var i = 0; i < 50; i++) {
        var seq = logic.buildShuffledSequence(5);
        firstSeen[seq[0]] = true;
    }
    // With 5 items and 50 runs, at least 2 distinct first elements should appear
    assert.ok(Object.keys(firstSeen).length >= 2, 'buildShuffledSequence appears non-random');
})();

// Server-shuffle model: identity sequence preserves server order (item 0 is poster & queue head)
(function () {
    // Simulate what JS does when serverShuffled=true:
    // Build identity sequence [0,1,2,...,n-1], step=0, cursor=0 → masterIx=filteredMasterIndices[0]
    var n = 5;
    var identitySeq = [];
    for (var i = 0; i < n; i++) { identitySeq.push(i); }
    assert.strictEqual(identitySeq[0], 0, 'identity sequence starts at 0');
    // filteredCursor = 0 → playlistIndex = filteredMasterIndices[0] = 0
    // Next step after video 0 ends should advance to cursor 1
    var step = logic.nextPlaylistStep({
        shuffleMode: true,
        filteredCursor: 0,
        shuffleStep: 0,
        filteredCount: n,
        shuffledSequence: identitySeq,
    });
    assert.deepStrictEqual(step, { shuffleStep: 1, filteredCursor: 1 });
})();

// End-of-ALL-playlist: nextPlaylistStep returns null; fresh buildShuffledSequence gives new head
(function () {
    // Last step in a 3-video playlist
    var step = logic.nextPlaylistStep({
        shuffleMode: true,
        filteredCursor: 2,
        shuffleStep: 2,
        filteredCount: 3,
        shuffledSequence: [0, 1, 2],
    });
    assert.strictEqual(step, null, 'end of ALL playlist returns null');
    // After null, resetToFreshShuffledPlaylist builds a new sequence
    var newSeq = logic.buildShuffledSequence(3);
    assert.strictEqual(newSeq.length, 3, 'fresh sequence has correct length');
})();

// ── Issue #4: composable filter state and client-side filtering ───────────────

// Simulate the composable filter logic from vimeo_caption_player.js.
// The actual filter runs in the browser; here we test the algorithm in isolation.

/**
 * Recompute filtered master indices from fullPlaylistItems + filterState.
 * Mirrors the recomputeFilteredMasterIndices() function in vimeo_caption_player.js.
 */
function recomputeFilteredMasterIndices(fullPlaylistItems, filterState) {
    var hasFilter = filterState.sign_language !== null
        || filterState.edition !== null
        || filterState.typology !== null;
    if (!hasFilter || fullPlaylistItems.length === 0) {
        return fullPlaylistItems.map(function (_, ix) { return ix; });
    }
    return fullPlaylistItems
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

// Sample playlist mirroring the shape produced by vimeo_caption_player.php
var samplePlaylist = [
    { videoId: '111', signLanguage: 'libras', edition: '2023-sao-paulo', typology: 'acudits',     participant: 'Edinho'   },
    { videoId: '222', signLanguage: 'lse',    edition: '2020-valencia',  typology: 'malentesos',  participant: 'Aurora'   },
    { videoId: '333', signLanguage: 'lse',    edition: '2023-bilbao',    typology: 'anecdotes',   participant: 'Amaia'    },
    { videoId: '444', signLanguage: 'lsm',    edition: '2021-mexico',    typology: 'acudits',     participant: 'Miguel'   },
    { videoId: '555', signLanguage: 'gss',    edition: '2028-salamanca', typology: 'anecdotes',   participant: 'Hamida'   },
    { videoId: '666', signLanguage: 'libras', edition: '2023-sao-paulo', typology: 'endevinalles',participant: 'Fabio'    },
];

// Composable filter state object: initial cold load — all null (D7)
(function () {
    var filterState = { sign_language: null, edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(result.length, samplePlaylist.length, 'cold load: all videos in filtered list (D7)');
})();

// Filter by sign_language: only libras
(function () {
    var filterState = { sign_language: 'libras', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0, 5], 'sign_language=libras returns indices [0,5]');
    result.forEach(function (ix) {
        assert.strictEqual(samplePlaylist[ix].signLanguage, 'libras', 'each result has signLanguage=libras');
    });
})();

// Filter by sign_language: lse
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [1, 2], 'sign_language=lse returns indices [1,2]');
})();

// Filter by sign_language: gss (single video)
(function () {
    var filterState = { sign_language: 'gss', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [4], 'sign_language=gss returns index [4]');
})();

// Clear filter (set to null) restores all videos
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filtered.length, 2, 'LSE filter gives 2 videos');
    // Now clear
    filterState.sign_language = null;
    var restored = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(restored.length, samplePlaylist.length, 'clearing filter restores all videos');
})();

// Composable AND: sign_language + typology
(function () {
    var filterState = { sign_language: 'libras', edition: null, typology: 'acudits' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0], 'libras + acudits composes to [0] only');
})();

// Composable AND: all three facets
(function () {
    var filterState = { sign_language: 'lse', edition: '2020-valencia', typology: 'malentesos' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [1], 'lse + 2020-valencia + malentesos narrows to [1]');
})();

// Filter result can be reshuffled via buildShuffledSequence (issue #1 behaviour on clear)
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(result.length, 2);
    var seq = logic.buildShuffledSequence(result.length);
    assert.strictEqual(seq.length, 2);
    seq.forEach(function (n) { assert.ok(n >= 0 && n < 2); });
})();

// After clear, buildShuffledSequence over full catalog gives new random head
(function () {
    var filterState = { sign_language: null, edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(result.length, samplePlaylist.length);
    var seq = logic.buildShuffledSequence(result.length);
    assert.strictEqual(seq.length, samplePlaylist.length);
})();

console.log('vimeo_playlist_logic.test.js: all passed');
