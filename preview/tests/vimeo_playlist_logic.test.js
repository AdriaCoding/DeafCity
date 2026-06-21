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

// ── Issue #4 + #5: composable filter state and client-side filtering ──────────

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
// Uses real catalog field values (edition slugs, typology slugs, sign_language slugs)
var samplePlaylist = [
    { videoId: '111', signLanguage: 'libras', edition: '2023-sao-paulo', typology: 'acudits',      participant: 'Edinho'   },
    { videoId: '222', signLanguage: 'lse',    edition: '2020-valencia',  typology: 'malentesos',   participant: 'Aurora'   },
    { videoId: '333', signLanguage: 'lse',    edition: '2023-bilbao',    typology: 'anecdotes',    participant: 'Amaia'    },
    { videoId: '444', signLanguage: 'lsm',    edition: '2021-mexico',    typology: 'acudits',      participant: 'Miguel'   },
    { videoId: '555', signLanguage: 'gss',    edition: '2028-salamanca', typology: 'anecdotes',    participant: 'Hamida'   },
    { videoId: '666', signLanguage: 'libras', edition: '2023-sao-paulo', typology: 'endevinalles', participant: 'Fabio'    },
    { videoId: '777', signLanguage: 'lse',    edition: '2020-valencia',  typology: 'memories',     participant: 'Carme'    },
    { videoId: '888', signLanguage: 'lsm',    edition: '2021-mexico',    typology: 'malentesos',   participant: 'Lupita'   },
];

// Cold load: all-null filterState returns all videos (D7)
(function () {
    var filterState = { sign_language: null, edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(result.length, samplePlaylist.length, 'cold load: all videos in filtered list (D7)');
})();

// ── Sign language filter ──────────────────────────────────────────────────────

// Filter by sign_language: only libras
(function () {
    var filterState = { sign_language: 'libras', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0, 5], 'sign_language=libras returns indices [0,5]');
    result.forEach(function (ix) {
        assert.strictEqual(samplePlaylist[ix].signLanguage, 'libras', 'each result has signLanguage=libras');
    });
})();

// Filter by sign_language: lse (3 items)
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [1, 2, 6], 'sign_language=lse returns indices [1,2,6]');
})();

// Filter by sign_language: gss (single video)
(function () {
    var filterState = { sign_language: 'gss', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [4], 'sign_language=gss returns index [4]');
})();

// Clear sign_language filter restores all videos
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filtered.length, 3, 'LSE filter gives 3 videos');
    // Now clear
    filterState.sign_language = null;
    var restored = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(restored.length, samplePlaylist.length, 'clearing sign_language restores all videos');
})();

// ── Edition filter (issue #5) ────────────────────────────────────────────────

// Filter by edition: 2023-sao-paulo
(function () {
    var filterState = { sign_language: null, edition: '2023-sao-paulo', typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0, 5], 'edition=2023-sao-paulo returns indices [0,5]');
    result.forEach(function (ix) {
        assert.strictEqual(samplePlaylist[ix].edition, '2023-sao-paulo');
    });
})();

// Filter by edition: 2020-valencia
(function () {
    var filterState = { sign_language: null, edition: '2020-valencia', typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [1, 6], 'edition=2020-valencia returns indices [1,6]');
})();

// Filter by edition: 2028-salamanca (single video)
(function () {
    var filterState = { sign_language: null, edition: '2028-salamanca', typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [4], 'edition=2028-salamanca returns index [4]');
})();

// Clear edition filter restores all
(function () {
    var filterState = { sign_language: null, edition: '2023-sao-paulo', typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filtered.length, 2);
    filterState.edition = null;
    var restored = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(restored.length, samplePlaylist.length, 'clearing edition restores all videos');
})();

// ── Typology filter (issue #5) ────────────────────────────────────────────────

// Filter by typology: acudits
(function () {
    var filterState = { sign_language: null, edition: null, typology: 'acudits' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0, 3], 'typology=acudits returns indices [0,3]');
})();

// Filter by typology: anecdotes
(function () {
    var filterState = { sign_language: null, edition: null, typology: 'anecdotes' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [2, 4], 'typology=anecdotes returns indices [2,4]');
})();

// Clear typology filter restores all
(function () {
    var filterState = { sign_language: null, edition: null, typology: 'acudits' };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filtered.length, 2);
    filterState.typology = null;
    var restored = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(restored.length, samplePlaylist.length, 'clearing typology restores all videos');
})();

// ── AND composition: Sign + Edition (issue #5) ──────────────────────────────

// Sign=lse + Edition=2020-valencia narrows to items matching BOTH
(function () {
    var filterState = { sign_language: 'lse', edition: '2020-valencia', typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    // Items [1] = lse/2020-valencia, [6] = lse/2020-valencia
    assert.deepStrictEqual(result, [1, 6], 'lse + 2020-valencia narrows to [1,6]');
    result.forEach(function (ix) {
        assert.strictEqual(samplePlaylist[ix].signLanguage, 'lse');
        assert.strictEqual(samplePlaylist[ix].edition, '2020-valencia');
    });
})();

// Sign=libras + Edition=2021-mexico → empty intersection (no libras video from mexico)
(function () {
    var filterState = { sign_language: 'libras', edition: '2021-mexico', typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(result.length, 0, 'libras + 2021-mexico has no intersection');
})();

// ── AND composition: Sign + Typology (issue #4 + #5) ─────────────────────────

// Composable AND: sign_language + typology
(function () {
    var filterState = { sign_language: 'libras', edition: null, typology: 'acudits' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0], 'libras + acudits composes to [0] only');
})();

// Sign=lsm + Typology=malentesos
(function () {
    var filterState = { sign_language: 'lsm', edition: null, typology: 'malentesos' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [7], 'lsm + malentesos narrows to [7]');
})();

// ── AND composition: Sign + Edition + Typology (issue #5) ────────────────────

// All three facets: lse + 2020-valencia + malentesos → single match
(function () {
    var filterState = { sign_language: 'lse', edition: '2020-valencia', typology: 'malentesos' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [1], 'lse + 2020-valencia + malentesos narrows to [1]');
})();

// All three facets: lse + 2020-valencia + memories → index 6
(function () {
    var filterState = { sign_language: 'lse', edition: '2020-valencia', typology: 'memories' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [6], 'lse + 2020-valencia + memories narrows to [6]');
})();

// All three: libras + 2023-sao-paulo + acudits → single match
(function () {
    var filterState = { sign_language: 'libras', edition: '2023-sao-paulo', typology: 'acudits' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [0], 'libras + 2023-sao-paulo + acudits narrows to [0]');
})();

// All three: libras + 2023-sao-paulo + endevinalles → single match (idx 5)
(function () {
    var filterState = { sign_language: 'libras', edition: '2023-sao-paulo', typology: 'endevinalles' };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.deepStrictEqual(result, [5], 'libras + 2023-sao-paulo + endevinalles narrows to [5]');
})();

// Clearing one filter from three restores a wider set (D19 loop-within-filter correctness)
(function () {
    var filterState = { sign_language: 'lse', edition: '2020-valencia', typology: 'malentesos' };
    var narrow = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(narrow.length, 1, 'three facets gives 1 result');

    // Clear typology: sign + edition still apply
    filterState.typology = null;
    var wider = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(wider.length, 2, 'clearing typology widens to 2 (lse + 2020-valencia)');

    // Clear edition: only sign remains
    filterState.edition = null;
    var widest = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(widest.length, 3, 'clearing edition widens to 3 (lse only)');

    // Clear all: full catalog
    filterState.sign_language = null;
    var all = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(all.length, samplePlaylist.length, 'clearing all facets restores full catalog (D7)');
})();

// ── D19: End-of-filtered-playlist loops within filter ────────────────────────
// When a facet-filtered playlist reaches its last video, nextPlaylistStep returns null.
// resetToFreshShuffledPlaylist then reshuffles WITHIN the current filtered set (not ALL).
// Verify: after filter, a new shuffle over filteredCount loops correctly.

(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    var fc = filtered.length; // 3 (indices [1,2,6])
    assert.strictEqual(fc, 3);

    // Simulate playing through the last step → null → reshuffle within filtered set
    var lastStep = logic.nextPlaylistStep({
        shuffleMode: true,
        filteredCursor: 2,
        shuffleStep: 2,
        filteredCount: fc,
        shuffledSequence: [0, 1, 2],
    });
    assert.strictEqual(lastStep, null, 'end of filtered playlist returns null (triggers loop)');

    // After null: build new shuffled sequence over filteredCount (not fullCount)
    var newSeq = logic.buildShuffledSequence(fc);
    assert.strictEqual(newSeq.length, fc, 'reshuffle stays within filtered count');
    // Verify indices are within filtered range [0, fc-1]
    newSeq.forEach(function (n) {
        assert.ok(n >= 0 && n < fc, 'reshuffled index within filtered range');
    });
    // New head is a valid filtered cursor
    var newCursor = newSeq[0];
    var newMasterIx = filtered[newCursor];
    assert.ok(newMasterIx !== undefined, 'new head maps to a valid master index');
    assert.strictEqual(samplePlaylist[newMasterIx].signLanguage, 'lse', 'new head still matches the active filter');
})();

// Filter result can be reshuffled via buildShuffledSequence (issue #1 behaviour on clear)
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(result.length, 3);
    var seq = logic.buildShuffledSequence(result.length);
    assert.strictEqual(seq.length, 3);
    seq.forEach(function (n) { assert.ok(n >= 0 && n < 3); });
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
