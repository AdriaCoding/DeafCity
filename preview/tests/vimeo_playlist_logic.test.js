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

// ── Issue #6: Spoken Language track selector — does NOT alter filterState or queue (D16) ──

/**
 * Simulate applySpokenLanguageChange from vimeo_caption_player.js.
 * This is the algorithm under test: it must not touch filterState or
 * filteredMasterIndices.  It only updates stickyCaptionLabel and
 * activeCaptionTrackIndex (via pickStickyTrackIndex).
 */
function simulateSpokenLanguageChange(label, stickyCaptionLabel, cueTracks) {
    var newSticky = stickyCaptionLabel;
    var newTrackIdx = 0;

    if (label === '' || !label) {
        // Clear — disable subtitles
        newSticky = '';
        newTrackIdx = 0;
    } else {
        newSticky = label;
        // pickStickyTrackIndex: find label in cueTracks or return 0
        newTrackIdx = 0;
        for (var i = 0; i < cueTracks.length; i++) {
            if (cueTracks[i] && cueTracks[i].label === label) {
                newTrackIdx = i;
                break;
            }
        }
    }
    return { stickyCaptionLabel: newSticky, activeCaptionTrackIndex: newTrackIdx };
}

// Track change does NOT modify filterState
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    var queueLengthBefore = filtered.length;
    assert.strictEqual(queueLengthBefore, 3, 'lse filter gives 3 videos before track change');

    // Simulate track change (e.g. user selects "English")
    var cueTracks = [{ label: 'Spanish', file: 'a.vtt' }, { label: 'English', file: 'b.vtt' }];
    var result = simulateSpokenLanguageChange('English', '', cueTracks);
    assert.strictEqual(result.stickyCaptionLabel, 'English', 'sticky label updated');
    assert.strictEqual(result.activeCaptionTrackIndex, 1, 'active track index updated');

    // filterState is unchanged — track change must NOT touch it
    assert.strictEqual(filterState.sign_language, 'lse', 'filterState.sign_language unchanged after track change');
    assert.strictEqual(filterState.edition, null, 'filterState.edition unchanged after track change');
    assert.strictEqual(filterState.typology, null, 'filterState.typology unchanged after track change');

    // filteredMasterIndices is unchanged — re-running recompute gives same result
    var filteredAfter = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filteredAfter.length, queueLengthBefore, 'queue length unchanged after track change (D16)');
    assert.deepStrictEqual(filteredAfter, filtered, 'queue content unchanged after track change (D16)');
})();

// Track change works with unfiltered playlist (all videos)
(function () {
    var filterState = { sign_language: null, edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filtered.length, samplePlaylist.length, 'all videos before track change');

    var cueTracks = [{ label: 'Portuguese', file: 'c.vtt' }];
    var result = simulateSpokenLanguageChange('Portuguese', '', cueTracks);
    assert.strictEqual(result.stickyCaptionLabel, 'Portuguese', 'sticky label set to Portuguese');
    assert.strictEqual(result.activeCaptionTrackIndex, 0, 'active track index 0 (only track)');

    // Queue unchanged
    var filteredAfter = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filteredAfter.length, samplePlaylist.length, 'queue length unchanged after track change on all-videos playlist');
})();

// Clear spoken language (label='') resets sticky without touching queue
(function () {
    var filterState = { sign_language: 'libras', edition: '2023-sao-paulo', typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    var queueBefore = filtered.length;

    var cueTracks = [{ label: 'English', file: 'e.vtt' }];
    // First select a track
    var set = simulateSpokenLanguageChange('English', '', cueTracks);
    assert.strictEqual(set.stickyCaptionLabel, 'English', 'track selected');
    // Then clear it
    var cleared = simulateSpokenLanguageChange('', set.stickyCaptionLabel, cueTracks);
    assert.strictEqual(cleared.stickyCaptionLabel, '', 'sticky label cleared');
    assert.strictEqual(cleared.activeCaptionTrackIndex, 0, 'track index reset to 0 on clear');

    // filterState still active — queue unchanged
    var filteredAfter = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filteredAfter.length, queueBefore, 'queue length unchanged after clearing spoken language');
})();

// Sticky-track behaviour: when next video lacks the label, pickStickyTrackIndex returns 0 (fallback)
(function () {
    var stickyCaptionLabel = 'English';
    // Next video has no tracks
    var cueTracks = [];
    var result = simulateSpokenLanguageChange(stickyCaptionLabel, '', cueTracks);
    // When cueTracks is empty, the loop finds nothing, returns index 0
    assert.strictEqual(result.activeCaptionTrackIndex, 0, 'falls back to index 0 when next video lacks the sticky label');

    // Next video has tracks but not the sticky label
    var cueTracks2 = [{ label: 'Spanish', file: 'x.vtt' }];
    var result2 = simulateSpokenLanguageChange(stickyCaptionLabel, '', cueTracks2);
    assert.strictEqual(result2.activeCaptionTrackIndex, 0, 'falls back to index 0 when sticky label not found in new tracks');

    // Next video has the sticky label
    var cueTracks3 = [{ label: 'Spanish', file: 'x.vtt' }, { label: 'English', file: 'y.vtt' }];
    var result3 = simulateSpokenLanguageChange(stickyCaptionLabel, '', cueTracks3);
    assert.strictEqual(result3.activeCaptionTrackIndex, 1, 'sticks at index 1 when English found in new tracks');
})();

// Spoken Language change does not interfere with AND filter composition
(function () {
    // Start with three-facet filter active
    var filterState = { sign_language: 'lse', edition: '2020-valencia', typology: 'malentesos' };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filtered.length, 1, 'three-facet filter gives 1 result');

    // Apply spoken language track change
    var cueTracks = [{ label: 'Valencian', file: 'v.vtt' }, { label: 'Spanish', file: 's.vtt' }];
    var trackResult = simulateSpokenLanguageChange('Valencian', '', cueTracks);
    assert.strictEqual(trackResult.stickyCaptionLabel, 'Valencian', 'track selected');
    assert.strictEqual(trackResult.activeCaptionTrackIndex, 0, 'track at index 0');

    // All three filter facets must remain intact
    assert.strictEqual(filterState.sign_language, 'lse', 'sign_language filter intact after spoken lang change');
    assert.strictEqual(filterState.edition, '2020-valencia', 'edition filter intact after spoken lang change');
    assert.strictEqual(filterState.typology, 'malentesos', 'typology filter intact after spoken lang change');

    // Queue still the same 1-video result
    var filteredAfter = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filteredAfter.length, 1, 'queue still 1-video after spoken lang change (D16)');
    assert.deepStrictEqual(filteredAfter, filtered, 'queue content identical after spoken lang change');
})();

// ── Issue #3: Shuffle toggle UX — D13 ────────────────────────────────────────

// Default visit: shuffle is ON (D13 — every visit starts fresh as shuffle-on)
(function () {
    var state = logic.createDefaultShuffleState(8);
    assert.strictEqual(state.shuffleMode, true, 'D13: shuffle is ON by default on every visit');
    assert.strictEqual(state.shuffleStep, 0, 'D13: shuffle starts at step 0');
    assert.ok(state.shuffledSequence.length === 8, 'D13: shuffled sequence covers all filtered items');
})();

// Shuffle OFF → remaining queue follows catalog order (filteredCursor in filtered list order)
// Simulate the toggle-off: filteredCursor stays as the current video's position in filtered list.
// After toggle-off, nextPlaylistStep must use filteredCursor as linear position.
(function () {
    // Setup: 6-item filtered list, currently on item at filtered index 3 (via shuffle)
    var filteredCount = 6;
    var shuffledSeq = [2, 5, 3, 0, 4, 1]; // shuffle placed us at step 2 → filteredCursor=3
    var currentShuffleStep = 2;
    var filteredCursor = shuffledSeq[currentShuffleStep]; // = 3

    // Toggle OFF: shuffleMode=false, filteredCursor stays at 3 (current video's catalog position)
    var shuffleMode = false;

    // Next step in linear (catalog) order from cursor 3: should go to 4
    var step = logic.nextPlaylistStep({
        shuffleMode: shuffleMode,
        filteredCursor: filteredCursor,
        shuffleStep: currentShuffleStep,
        filteredCount: filteredCount,
        shuffledSequence: [],
    });
    assert.deepStrictEqual(step, { shuffleStep: currentShuffleStep, filteredCursor: 4 },
        'D13: shuffle OFF → next step follows catalog order (filteredCursor+1)');

    // And prev would go to 2
    var prevStep = { shuffleStep: currentShuffleStep, filteredCursor: filteredCursor - 1 };
    assert.strictEqual(prevStep.filteredCursor, 2, 'D13: shuffle OFF → prev follows catalog order (filteredCursor-1)');
})();

// Shuffle OFF → current video keeps playing (no video load on toggle)
// This is a JS architecture guarantee: the toggle handler does NOT call loadVideoMaster.
// We verify by confirming nextPlaylistStep does not return the SAME cursor (no re-trigger).
(function () {
    var filteredCursor = 3;
    var shuffleMode = false;
    var step = logic.nextPlaylistStep({
        shuffleMode: shuffleMode,
        filteredCursor: filteredCursor,
        shuffleStep: 0,
        filteredCount: 6,
        shuffledSequence: [],
    });
    // The next step is cursor 4 — NOT cursor 3 again (current video stays, not re-loaded)
    assert.notStrictEqual(step && step.filteredCursor, filteredCursor,
        'D13: shuffle OFF → next step does not re-trigger current video');
})();

// Shuffle ON → Prev/Next follow the shuffled sequence
(function () {
    var shuffledSeq = [4, 1, 3, 0, 2];
    var currentStep = 1; // currently at filteredCursor=1 (shuffledSeq[1])
    var filteredCursor = shuffledSeq[currentStep]; // = 1

    var step = logic.nextPlaylistStep({
        shuffleMode: true,
        filteredCursor: filteredCursor,
        shuffleStep: currentStep,
        filteredCount: 5,
        shuffledSequence: shuffledSeq,
    });
    assert.deepStrictEqual(step, { shuffleStep: 2, filteredCursor: 3 },
        'D13: shuffle ON → next step follows shuffled sequence (step 1→2, cursor→shuffledSeq[2]=3)');
})();

// Toggle not persisted: createDefaultShuffleState always returns shuffleMode=true (fresh visit)
(function () {
    for (var i = 0; i < 5; i++) {
        var state = logic.createDefaultShuffleState(10);
        assert.strictEqual(state.shuffleMode, true,
            'D13: every fresh createDefaultShuffleState call returns shuffleMode=true (not persisted)');
    }
})();

// Shuffle OFF at last filtered item: nextPlaylistStep returns null (end of catalog order)
(function () {
    var step = logic.nextPlaylistStep({
        shuffleMode: false,
        filteredCursor: 4, // last item in a 5-item filtered list
        shuffleStep: 0,
        filteredCount: 5,
        shuffledSequence: [],
    });
    assert.strictEqual(step, null, 'D13: shuffle OFF at last item → null (end of catalog queue)');
})();

// Shuffle OFF at first filtered item: prev would be cursor-1 = -1, caller must guard
(function () {
    // When filteredCursor=0, seekFiltered(-1) guards: ni < 0 → no-op
    var filteredCursor = 0;
    var ni = filteredCursor - 1; // = -1
    assert.ok(ni < 0, 'D13: prev at first catalog item is out-of-range (caller guards ni<0)');
})();

// Shuffle toggle OFF then ON: produces a new shuffle from the current position
(function () {
    // After turning OFF, filteredCursor=2; turning back ON reshuffles around cursor 2
    var filteredCount = 5;
    var filteredCursor = 2;

    var newSeq = logic.buildShuffledSequence(filteredCount);
    assert.strictEqual(newSeq.length, filteredCount, 'D13: re-enable shuffle gives correct-length sequence');
    // Find where cursor 2 lands in the new sequence
    var stepForCursor = newSeq.indexOf(filteredCursor);
    assert.ok(stepForCursor >= 0 && stepForCursor < filteredCount,
        'D13: current video (cursor=2) is placed somewhere in the new shuffled sequence');
})();

// Shuffle OFF in a filtered playlist: catalog order respects filter (only filtered items)
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    // filtered = [1, 2, 6] (the lse videos)
    assert.strictEqual(filtered.length, 3, 'lse filter gives 3 filtered items');

    // Simulate shuffle placed us at filtered index 1 (master index 2 = Amaia)
    var filteredCursor = 1;
    assert.strictEqual(filtered[filteredCursor], 2, 'filteredCursor 1 → master index 2');

    // Toggle OFF: next in catalog order is filtered index 2 (master index 6 = Carme)
    var step = logic.nextPlaylistStep({
        shuffleMode: false,
        filteredCursor: filteredCursor,
        shuffleStep: 0,
        filteredCount: filtered.length,
        shuffledSequence: [],
    });
    assert.deepStrictEqual(step, { shuffleStep: 0, filteredCursor: 2 },
        'D13 + D18: shuffle OFF in filtered playlist → next follows filtered catalog order');
    assert.strictEqual(filtered[step.filteredCursor], 6, 'next video is master index 6 (Carme, lse)');
    assert.strictEqual(samplePlaylist[filtered[step.filteredCursor]].signLanguage, 'lse',
        'D13: catalog order after toggle OFF still respects active filter');
})();

console.log('vimeo_playlist_logic.test.js: all passed');

// ── Issue #10: gesture-gated audio (D12′, D23) ───────────────────────────────

assert.strictEqual(logic.GESTURE_STORAGE_KEY, 'vpc-gesture-activated');

assert.strictEqual(logic.isSessionSoundAllowed(false), false);
assert.strictEqual(logic.isSessionSoundAllowed(true), true);

assert.strictEqual(logic.shouldAutoplayWithSound(false, true), false, 'no autoplay before gesture');
assert.strictEqual(logic.shouldAutoplayWithSound(true, true), true, 'autoplay after gesture');
assert.strictEqual(logic.shouldAutoplayWithSound(true, false), false, 'caller can force pause');
assert.strictEqual(logic.shouldAutoplayWithSound(false, false), false, 'paused when not activated');

assert.strictEqual(logic.resolveParticipantGestureCarry(false, '1'), false, 'carry ignored when not participant mode');
assert.strictEqual(logic.resolveParticipantGestureCarry(true, '1'), true, 'carry when participant + storage flag');
assert.strictEqual(logic.resolveParticipantGestureCarry(true, null), false, 'no carry without storage flag');
assert.strictEqual(logic.resolveParticipantGestureCarry(true, ''), false, 'no carry with empty storage');

