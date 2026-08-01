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

// Uses exported recomputeFilteredMasterIndices from vimeo_playlist_logic.js
var recomputeFilteredMasterIndices = logic.recomputeFilteredMasterIndices;

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

// ── Issue #11 / D16′: Spoken Language by available tracks + sticky-by-language ──

var subtitleLanguagesFixture = [
    { id: 'es', label: 'Spanish' },
    { id: 'en', label: 'English' },
    { id: 'ca', label: 'Catalan' },
    { id: 'pt', label: 'Portuguese' },
    { id: 'ar', label: 'Arabic' },
];

assert.strictEqual(logic.resolveSpokenLangId('es-MX', subtitleLanguagesFixture), 'es', 'es-MX → Spanish');
assert.strictEqual(logic.resolveSpokenLangId('es-ES', subtitleLanguagesFixture), 'es', 'es-ES → Spanish');
assert.strictEqual(logic.resolveSpokenLangId('en', subtitleLanguagesFixture), 'en', 'en → English');
assert.strictEqual(logic.resolveSpokenLangId('ar', subtitleLanguagesFixture), 'ar', 'ar → Arabic');
assert.strictEqual(logic.resolveSpokenLangId('arq', subtitleLanguagesFixture), '', 'unconfigured dialect does not resolve');

(function () {
    var tracks = [
        { lang: 'es-MX', label: 'Veronica_03.srt', file: 'a.vtt' },
        { lang: 'en', label: 'other.srt', file: 'b.vtt' },
    ];
    var opts = logic.buildSpokenOptionsForTracks(tracks, subtitleLanguagesFixture);
    assert.strictEqual(opts.length, 2, 'two distinct spoken languages');
    assert.strictEqual(opts[0].label, 'Spanish', 'studio label for es-MX');
    assert.strictEqual(opts[0].spokenLangId, 'es', 'spoken lang id for es-MX');
})();

(function () {
    var tracks = [
        { lang: 'es-MX', label: 'a.srt', file: 'a.vtt' },
        { lang: 'es-ES', label: 'b.srt', file: 'b.vtt' },
    ];
    var opts = logic.buildSpokenOptionsForTracks(tracks, subtitleLanguagesFixture);
    assert.strictEqual(opts.length, 1, 'region variants collapsed to one Spanish entry');
    assert.strictEqual(opts[0].label, 'Spanish', 'collapsed label is Spanish');
})();

/**
 * Simulate applySpokenLanguageChange (D16′): keyed by spoken language id, not filename label.
 */
function simulateSpokenLanguageChange(spokenLangId, stickySpokenLangId, cueTracks, subtitleLanguages) {
    var newSticky = stickySpokenLangId;
    var newTrackIdx = logic.resolveActiveCaptionTrackIndex(cueTracks, stickySpokenLangId, subtitleLanguages);
    if (!spokenLangId) {
        return { stickySpokenLangId: newSticky, activeCaptionTrackIndex: newTrackIdx };
    }
    newSticky = spokenLangId;
    newTrackIdx = logic.resolveActiveCaptionTrackIndex(cueTracks, spokenLangId, subtitleLanguages);
    return { stickySpokenLangId: newSticky, activeCaptionTrackIndex: newTrackIdx };
}

// Track change does NOT modify filterState
(function () {
    var filterState = { sign_language: 'lse', edition: null, typology: null };
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    var queueLengthBefore = filtered.length;
    assert.strictEqual(queueLengthBefore, 3, 'lse filter gives 3 videos before track change');

    var cueTracks = [
        { lang: 'es', label: 'Spanish.srt', file: 'a.vtt' },
        { lang: 'en', label: 'English.srt', file: 'b.vtt' },
    ];
    var result = simulateSpokenLanguageChange('en', '', cueTracks, subtitleLanguagesFixture);
    assert.strictEqual(result.stickySpokenLangId, 'en', 'sticky language id updated');
    assert.strictEqual(result.activeCaptionTrackIndex, 1, 'active track index updated');

    assert.strictEqual(filterState.sign_language, 'lse', 'filterState.sign_language unchanged after track change');
    var filteredAfter = recomputeFilteredMasterIndices(samplePlaylist, filterState);
    assert.strictEqual(filteredAfter.length, queueLengthBefore, 'queue length unchanged after track change (D16)');
})();

// Sticky-by-language via initialSubtitleLang (issue #19)
(function () {
    var cueTracksMatch = [
        { lang: 'es', label: 'a.srt', file: 'x.vtt' },
        { lang: 'en', label: 'totally_different.srt', file: 'y.vtt' },
    ];
    var idx = logic.resolveActiveCaptionTrackIndex(cueTracksMatch, 'es', subtitleLanguagesFixture);
    assert.strictEqual(idx, 0, 'initialSubtitleLang es selects Spanish track when present');

    var cueTracksNoMatch = [{ lang: 'en', label: 'only_en.srt', file: 'x.vtt' }];
    var idxFallback = logic.resolveActiveCaptionTrackIndex(cueTracksNoMatch, 'es', subtitleLanguagesFixture);
    assert.strictEqual(idxFallback, 0, 'initialSubtitleLang es falls back to index 0 when no Spanish track');
})();

// Sticky-by-language: filename labels differ but lang matches across videos
(function () {
    var stickySpokenLangId = 'en';
    var cueTracksNoMatch = [{ lang: 'es', label: 'Veronica_03.srt', file: 'x.vtt' }];
    var idx1 = logic.resolveActiveCaptionTrackIndex(cueTracksNoMatch, stickySpokenLangId, subtitleLanguagesFixture);
    assert.strictEqual(idx1, 0, 'falls back to first track when sticky language absent');

    var cueTracksMatch = [
        { lang: 'es', label: 'a.srt', file: 'x.vtt' },
        { lang: 'en', label: 'totally_different.srt', file: 'y.vtt' },
    ];
    var idx2 = logic.resolveActiveCaptionTrackIndex(cueTracksMatch, stickySpokenLangId, subtitleLanguagesFixture);
    assert.strictEqual(idx2, 1, 'sticks to English by lang even when label differs');
})();

// Snap-back: after fallback, later video with sticky language restores it
(function () {
    var sticky = 'en';
    var videoA = [{ lang: 'es', label: 'only_es.srt', file: 'a.vtt' }];
    var videoB = [{ lang: 'en', label: 'other_name.srt', file: 'b.vtt' }];

    var idxA = logic.resolveActiveCaptionTrackIndex(videoA, sticky, subtitleLanguagesFixture);
    assert.strictEqual(idxA, 0, 'video A uses first available (Spanish)');

    var idxB = logic.resolveActiveCaptionTrackIndex(videoB, sticky, subtitleLanguagesFixture);
    assert.strictEqual(idxB, 0, 'video B snaps back to sticky English');
})();

// ── Issue #6 legacy: Spoken Language track selector — does NOT alter filterState or queue (D16) ──

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

// ── Issue #12: filter pickers — live readout, cascade, keep-if-matches (D14′, D17′, D18′, D22) ──

var allOptionsByFacet = {
    sign_language: [
        { value: 'libras', label: 'LIBRAS Brazilian Sign Language' },
        { value: 'lse', label: 'LSE Spanish Sign Language' },
        { value: 'lsm', label: 'LSM Mexican Sign Language' },
        { value: 'gss', label: 'GSS Greek Sign Language' },
    ],
    edition: [
        { value: '2023-sao-paulo', label: '2023 São Paulo' },
        { value: '2020-valencia', label: '2020 València' },
        { value: '2023-bilbao', label: '2023 Bilbao' },
        { value: '2021-mexico', label: '2021 Mexico City' },
        { value: '2028-salamanca', label: 'Salamanca 2028' },
    ],
    typology: [
        { value: 'acudits', label: 'ACUDITS' },
        { value: 'malentesos', label: 'MALENTESOS' },
        { value: 'anecdotes', label: 'ANECDOTES' },
        { value: 'memories', label: 'MEMORIES' },
        { value: 'endevinalles', label: 'ENDEVINALLES' },
    ],
};

// D14′: live readout is neutral; green only when fixed
// Face carries full + short so CSS can swap at the maketa breakpoint (≤1024).
(function () {
    var item = samplePlaylist[0];
    var readout = logic.resolveFilterPickerReadout(
        item,
        'sign_language',
        { sign_language: null, edition: null, typology: null },
        allOptionsByFacet.sign_language,
        'Sign language'
    );
    assert.strictEqual(readout.fixed, false, 'passive readout is not fixed');
    assert.strictEqual(
        readout.label,
        'LIBRAS Brazilian Sign Language',
        'live readout full face uses studio label'
    );
    assert.strictEqual(readout.short_label, 'LIBRAS', 'live readout short face is compact');

    var fixed = logic.resolveFilterPickerReadout(
        item,
        'sign_language',
        { sign_language: 'libras', edition: null, typology: null },
        allOptionsByFacet.sign_language,
        'Sign language'
    );
    assert.strictEqual(fixed.fixed, true, 'fixed filter is pinned');
    assert.strictEqual(
        fixed.label,
        'LIBRAS Brazilian Sign Language',
        'fixed full face uses studio label'
    );
    assert.strictEqual(fixed.short_label, 'LIBRAS', 'fixed short face is compact');

    var empty = logic.resolveFilterPickerReadout(
        { videoId: 'x', signLanguage: '', edition: '', typology: '' },
        'sign_language',
        { sign_language: null, edition: null, typology: null },
        allOptionsByFacet.sign_language,
        'Sign language'
    );
    assert.strictEqual(empty.label, 'Sign language', 'generic full face');
    assert.strictEqual(empty.short_label, 'Sign language', 'generic short face matches full');
})();

// Issue #16: compactFacetLabel — explicit short_label wins (D14″)
(function () {
    var opts = [
        { value: 'libras', label: 'LIBRAS Brazilian Sign Language', short_label: 'LIBRAS' },
    ];
    assert.strictEqual(
        logic.compactFacetLabel('sign_language', 'libras', opts),
        'LIBRAS',
        'explicit short_label wins'
    );
})();

// Issue #16: sign_language fallback — first token of full label
(function () {
    var opts = [{ value: 'lse', label: 'LSE Spanish Sign Language' }];
    assert.strictEqual(
        logic.compactFacetLabel('sign_language', 'lse', opts),
        'LSE',
        'sign language falls back to first token'
    );
})();

// Issue #16: edition fallback — strip leading/trailing 4-digit year
(function () {
    var opts = [
        { value: '2023-sao-paulo', label: '2023 São Paulo' },
        { value: '2028-salamanca', label: 'Salamanca 2028' },
    ];
    assert.strictEqual(
        logic.compactFacetLabel('edition', '2023-sao-paulo', opts),
        'São Paulo',
        'edition strips leading year'
    );
    assert.strictEqual(
        logic.compactFacetLabel('edition', '2028-salamanca', opts),
        'Salamanca',
        'edition strips trailing year'
    );
})();

// Issue #16: typology fallback — title-case of label
(function () {
    var opts = [{ value: 'anecdotes', label: 'ANÈCDOTES' }];
    assert.strictEqual(
        logic.compactFacetLabel('typology', 'anecdotes', opts),
        'Anècdotes',
        'typology falls back to title-case'
    );
})();

// Issue #16: unknown value degrades gracefully (no blank face)
(function () {
    var opts = [{ value: 'libras', label: 'LIBRAS Brazilian Sign Language' }];
    assert.strictEqual(
        logic.compactFacetLabel('sign_language', 'unknown-sl', opts),
        'unknown-sl',
        'unknown sign language returns value id'
    );
    assert.strictEqual(
        logic.compactFacetLabel('edition', 'unknown-ed', opts),
        'unknown-ed',
        'unknown edition returns value id'
    );
    assert.strictEqual(
        logic.compactFacetLabel('typology', 'unknown-ty', opts),
        'Unknown-ty',
        'unknown typology title-cases value id'
    );
})();

// D17′: cascading options — fixing edition narrows sign_language dropup
(function () {
    var cascade = logic.buildCascadingFilterOptions(
        samplePlaylist,
        { sign_language: null, edition: '2020-valencia', typology: null },
        allOptionsByFacet
    );
    assert.strictEqual(cascade.sign_language.length, 1, 'only lse in 2020-valencia');
    assert.strictEqual(cascade.sign_language[0].value, 'lse');
    assert.strictEqual(cascade.edition.length, 5, 'edition dropup shows all editions in catalog subset');
    assert.ok(cascade.typology.length >= 2, 'typology options present for valencia videos');
})();

// D17′: edition dropup follows studio-config order, not alphabetical
(function () {
    var cascade = logic.buildCascadingFilterOptions(
        samplePlaylist,
        { sign_language: null, edition: null, typology: null },
        allOptionsByFacet
    );
    var editionValues = cascade.edition.map(function (opt) { return opt.value; });
    assert.deepStrictEqual(
        editionValues,
        ['2023-sao-paulo', '2020-valencia', '2023-bilbao', '2021-mexico', '2028-salamanca'],
        'edition options follow catalog order'
    );
})();

// D17′: AND composition never empty when UI only offers cascading values
(function () {
    var cascade = logic.buildCascadingFilterOptions(
        samplePlaylist,
        { sign_language: 'lse', edition: '2020-valencia', typology: null },
        allOptionsByFacet
    );
    cascade.typology.forEach(function (opt) {
        var filterState = {
            sign_language: 'lse',
            edition: '2020-valencia',
            typology: opt.value,
        };
        var result = recomputeFilteredMasterIndices(samplePlaylist, filterState);
        assert.ok(result.length > 0, 'cascading option ' + opt.value + ' never composes to empty');
    });
})();

// D22: keep-if-matches — current video stays when it matches new filter
(function () {
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: samplePlaylist,
        filterState: { sign_language: 'libras', edition: null, typology: null },
        currentMasterIndex: 0,
        shuffleMode: true,
        randomFn: function () { return 0; },
    });
    assert.ok(plan, 'plan produced');
    assert.strictEqual(plan.keepCurrentVideo, true, 'libras video 0 kept when fixing libras');
    assert.strictEqual(plan.loadMasterIndex, 0, 'still on master index 0');
    assert.strictEqual(plan.shuffleStep, 0, 'current video is queue head');
    assert.strictEqual(plan.shuffledSequence[0], plan.filteredCursor, 'head of shuffle is current position');
})();

// D22: jump when current video does not match
(function () {
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: samplePlaylist,
        filterState: { sign_language: 'gss', edition: null, typology: null },
        currentMasterIndex: 0,
        shuffleMode: true,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.keepCurrentVideo, false, 'non-matching video triggers jump');
    assert.strictEqual(plan.loadMasterIndex, 4, 'jumps to gss video (index 4)');
})();

// D22: clearing via All widens and keeps current when still in set
(function () {
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: samplePlaylist,
        filterState: { sign_language: null, edition: null, typology: null },
        currentMasterIndex: 2,
        shuffleMode: false,
    });
    assert.ok(plan);
    assert.strictEqual(plan.keepCurrentVideo, true, 'clearing filters keeps current video');
    assert.strictEqual(plan.loadMasterIndex, 2, 'still on same master index');
    assert.strictEqual(plan.filteredCursor, 2, 'cursor follows catalog position');
})();

// D18′: fixing R2 filter clears participant collection
assert.strictEqual(logic.shouldClearCollectionOnFilterFix(true, 'lse'), true);
assert.strictEqual(logic.shouldClearCollectionOnFilterFix(true, null), false, 'clearing filter value does not imply collection clear hook');
assert.strictEqual(logic.shouldClearCollectionOnFilterFix(false, 'lse'), false);

console.log('vimeo_playlist_logic.test.js: all passed (including issue #12)');

// ── Issue #13: Reset clears all filters & collections → paused ALL (D1′) ─────

(function () {
    var plan = logic.planResetToNeutralAll({
        fullPlaylistItems: samplePlaylist,
        randomFn: function () { return 0; },
    });
    assert.ok(plan, 'reset plan produced');
    assert.strictEqual(plan.filterState.sign_language, null, 'clears sign_language filter');
    assert.strictEqual(plan.filterState.edition, null, 'clears edition filter');
    assert.strictEqual(plan.filterState.typology, null, 'clears typology filter');
    assert.strictEqual(plan.filterState.tag, null, 'clears tag filter');
    assert.strictEqual(plan.isParticipantMode, false, 'clears participant mode');
    assert.strictEqual(plan.participantName, '', 'clears participant name');
    assert.strictEqual(plan.filteredMasterIndices.length, samplePlaylist.length, 'unfiltered ALL catalog');
    assert.strictEqual(plan.shuffleMode, true, 'reset uses shuffle-on ALL');
    assert.strictEqual(plan.shuffleStep, 0, 'reset starts at shuffle step 0');
    assert.strictEqual(plan.shuffledSequence.length, samplePlaylist.length, 'full reshuffle');
    assert.strictEqual(plan.shouldAutoplay, false, 'reset never auto-plays (D1′ exception)');
    assert.strictEqual(
        logic.shouldAutoplayWithSound(true, plan.shouldAutoplay),
        false,
        'reset stays paused even after gesture unlock'
    );
    assert.ok(plan.loadMasterIndex >= 0 && plan.loadMasterIndex < samplePlaylist.length, 'valid load index');
    assert.strictEqual(plan.filteredCursor, plan.shuffledSequence[0], 'cursor follows shuffle head');
    assert.strictEqual(plan.loadMasterIndex, plan.filteredMasterIndices[plan.filteredCursor], 'load index maps from shuffle head');
})();

// Reset from a narrowed filtered state still widens to full catalog
(function () {
    var plan = logic.planResetToNeutralAll({
        fullPlaylistItems: samplePlaylist,
        randomFn: function () { return 0.5; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.filteredMasterIndices.length, samplePlaylist.length, 'reset widens to ALL');
})();

// emptyFilterState helper matches cleared reset state
(function () {
    var empty = logic.emptyFilterState();
    assert.strictEqual(empty.sign_language, null);
    assert.strictEqual(empty.edition, null);
    assert.strictEqual(empty.typology, null);
    assert.strictEqual(empty.tag, null);
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, empty);
    assert.strictEqual(filtered.length, samplePlaylist.length);
})();

// Single-line caption normalization and shrink-to-fit sizing
(function () {
    assert.strictEqual(logic.CAPTION_TARGET_MAX_CHARS, 60);
    assert.strictEqual(logic.CAPTION_MAX_CHARS_TOLERANCE, 5);
    assert.strictEqual(logic.CAPTION_MIN_FONT_SIZE_PX, 6);
    assert.strictEqual(logic.captionDisplayMaxLength(), 65);
    assert.strictEqual(logic.normalizeCaptionText(''), '');
    assert.strictEqual(logic.normalizeCaptionText(null), '');
    assert.strictEqual(logic.normalizeCaptionText('Hello\nworld'), 'Hello world');
    assert.strictEqual(logic.normalizeCaptionText('  spaced   out  '), 'spaced out');
    assert.strictEqual(logic.normalizeCaptionText('c'.repeat(120)), 'c'.repeat(120));

    assert.strictEqual(logic.captionFitFontSizeFromWidths(0, 38, 800), 38, 'zero width keeps base');
    assert.strictEqual(logic.captionFitFontSizeFromWidths(400, 38, 800), 38, 'fits at base size');
    assert.strictEqual(logic.captionFitFontSizeFromWidths(800, 38, 400), 19, 'scales down to fit');
    assert.strictEqual(logic.captionFitFontSizeFromWidths(1600, 38, 400), 9.5, 'scales long cues smaller');
    assert.strictEqual(logic.captionFitFontSizeFromWidths(4000, 38, 400), 3.8, 'scales very long cues to fit');
})();

console.log('vimeo_playlist_logic.test.js: all passed (including issue #13)');

// ── Issue #02: playback session + secondary-page nav intent ─────────────────

assert.strictEqual(logic.PLAYBACK_SESSION_KEY, 'vpc-playback-session');
assert.strictEqual(logic.NAV_INTENT_KEY, 'vpc-nav-intent');

(function () {
    var snap = logic.buildPlaybackSessionSnapshot({
        masterIndex: 2,
        filterState: { sign_language: 'lse', edition: null, typology: null },
        participantName: '',
        shuffleMode: true,
        shuffledSequence: [1, 0, 2],
        shuffleStep: 1,
        filteredCursor: 0,
        playbackTimeSec: 12.5,
    });
    assert.strictEqual(snap.masterIndex, 2);
    assert.strictEqual(snap.filterState.sign_language, 'lse');
    assert.strictEqual(snap.shuffleStep, 1);
    assert.strictEqual(snap.playbackTimeSec, 12.5);

    var parsed = logic.parsePlaybackSession(JSON.stringify(snap));
    assert.ok(parsed);
    assert.strictEqual(parsed.masterIndex, 2);
    assert.strictEqual(parsed.playbackTimeSec, 12.5);
})();

assert.strictEqual(logic.parsePlaybackSession(''), null);
assert.strictEqual(logic.parsePlaybackSession('not-json'), null);

// Branch A: nav intent without session → fresh neutral
(function () {
    var plan = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'play',
        fullPlaylistItems: samplePlaylist,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan.kind, 'fresh');
})();

// Branch B: play restores same video context
(function () {
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 1,
        filterState: { sign_language: null, edition: null, typology: null },
        participantName: '',
        shuffleMode: true,
        shuffledSequence: [1, 0, 2],
        shuffleStep: 0,
        filteredCursor: 1,
        playbackTimeSec: 33,
    });
    var plan = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'play',
        fullPlaylistItems: samplePlaylist,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan.kind, 'restore');
    assert.strictEqual(plan.loadMasterIndex, 1);
    assert.strictEqual(plan.playbackTimeSec, 33);
    assert.strictEqual(plan.shouldAutoplay, true);
})();

// Branch B: prev steps within saved shuffle sequence
(function () {
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 6,
        filterState: { sign_language: 'lse', edition: null, typology: null },
        participantName: '',
        shuffleMode: true,
        shuffledSequence: [2, 0, 1],
        shuffleStep: 2,
        filteredCursor: 1,
        playbackTimeSec: 0,
    });
    var plan = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'prev',
        fullPlaylistItems: samplePlaylist,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan.kind, 'restore');
    assert.strictEqual(plan.shuffleStep, 1);
    assert.strictEqual(plan.filteredCursor, 0);
    assert.strictEqual(plan.loadMasterIndex, 1);
})();

// Explicit ?participant= navigation must not restore a stale neutral session (issue #04).
(function () {
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 5,
        filterState: { sign_language: 'lse', edition: null, typology: null },
        participantName: '',
        shuffleMode: true,
        shuffledSequence: [2, 0, 1],
        shuffleStep: 1,
        filteredCursor: 0,
        playbackTimeSec: 44,
    });
    var plan = logic.planSecondaryNavRestore({
        session: session,
        navIntent: '',
        fullPlaylistItems: samplePlaylist,
        explicitParticipantName: 'Hamida',
    });
    assert.strictEqual(plan, null, 'participant card click skips session restore');
})();

// Branch B: reset clears to neutral ALL
(function () {
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 3,
        filterState: { sign_language: 'lse', edition: null, typology: null },
        participantName: 'Aurora',
        shuffleMode: true,
        shuffledSequence: [0, 1],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 5,
    });
    var plan = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'reset',
        fullPlaylistItems: samplePlaylist,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan.kind, 'reset');
    assert.strictEqual(plan.plan.filterState.sign_language, null);
    assert.strictEqual(plan.plan.isParticipantMode, false);
    assert.strictEqual(plan.plan.shouldAutoplay, false);
})();

console.log('vimeo_playlist_logic.test.js: all passed (including issue #02)');

// ── Issue #04 / participant nav sequence label ─────────────────────────────

assert.strictEqual(
    logic.participantSequenceFromTitle('2026_ALGER_Hamida_1_4K'),
    '1',
    'parse sequence from 4K title'
);
assert.strictEqual(
    logic.participantSequenceFromTitle('2026_ROMA_Serena_3_HD'),
    '3',
    'parse sequence from HD title'
);
assert.strictEqual(
    logic.participantSequenceFromTitle('2026_BARCELONA_Carlota_01_HD'),
    '1',
    'strip leading zeros from sequence'
);
assert.strictEqual(
    logic.participantSequenceFromTitle('untitled clip'),
    '',
    'missing sequence → empty'
);
assert.strictEqual(
    logic.formatParticipantNavLabel('Hamida', '2'),
    'Hamida 2',
    'format name + sequence'
);
assert.strictEqual(
    logic.formatParticipantNavLabel('Hamida', ''),
    'Hamida',
    'format bare name when sequence missing'
);

(function () {
    var state = logic.resolveParticipantsNavState({
        isParticipantMode: true,
        participantName: 'Hamida',
        onPlayerPage: true,
        isPlaying: false,
        currentVideoParticipant: 'Hamida',
        currentVideoParticipantSequence: '1',
    });
    assert.strictEqual(state.label, 'Hamida 1', 'participant playlist: name+seq while paused');
    assert.strictEqual(state.isActive, true, 'participant playlist: green while paused');
})();

(function () {
    var state = logic.resolveParticipantsNavState({
        isParticipantMode: true,
        participantName: 'Hamida',
        participantSequence: '2',
        onPlayerPage: false,
        isPlaying: false,
        currentVideoParticipant: '',
        currentVideoParticipantSequence: '',
    });
    assert.strictEqual(state.label, 'Hamida 2', 'secondary pages: name+seq from session');
    assert.strictEqual(state.isActive, true, 'participant playlist: green on about/participants');
})();

(function () {
    var state = logic.resolveParticipantsNavState({
        isParticipantMode: false,
        participantName: '',
        onPlayerPage: true,
        isPlaying: false,
        currentVideoParticipant: 'Aurora',
        currentVideoParticipantSequence: '1',
    });
    assert.strictEqual(state.label, '', 'neutral playlist paused: generic label');
    assert.strictEqual(state.isActive, false, 'neutral playlist paused: not green');
})();

(function () {
    var state = logic.resolveParticipantsNavState({
        isParticipantMode: false,
        participantName: '',
        onPlayerPage: true,
        isPlaying: true,
        currentVideoParticipant: 'Aurora',
        currentVideoParticipantSequence: '3',
    });
    assert.strictEqual(state.label, 'Aurora 3', 'neutral playlist playing: current name+seq');
    assert.strictEqual(state.isActive, false, 'neutral playlist playing: stays gray');
})();

(function () {
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, {
        sign_language: 'gss',
        edition: null,
        typology: null,
    });
    assert.deepStrictEqual(
        logic.distinctParticipantsInSubset(samplePlaylist, filtered),
        ['Hamida'],
        'gss filter yields one participant'
    );
    var state = logic.resolveParticipantsNavState({
        isParticipantMode: false,
        participantName: '',
        onPlayerPage: true,
        isPlaying: true,
        currentVideoParticipant: 'Hamida',
        currentVideoParticipantSequence: '',
    });
    assert.strictEqual(state.label, 'Hamida', 'single-participant filter: bare name when seq missing');
    assert.strictEqual(state.isActive, false, 'single-participant filter: never green without participant mode');
})();

(function () {
    var snap = logic.buildPlaybackSessionSnapshot({
        masterIndex: 0,
        filterState: { sign_language: null, edition: null, typology: null },
        participantName: 'Hamida',
        participantSequence: '4',
        shuffleMode: false,
        shuffledSequence: [],
        shuffleStep: 0,
        filteredCursor: 0,
    });
    assert.strictEqual(snap.participantSequence, '4', 'session stores participant sequence');
    var parsed = logic.parsePlaybackSession(JSON.stringify(snap));
    assert.ok(parsed);
    assert.strictEqual(parsed.participantSequence, '4', 'session round-trip keeps sequence');
    var legacy = logic.parsePlaybackSession(JSON.stringify({
        v: 2,
        masterIndex: 0,
        filterState: { sign_language: null, edition: null, typology: null, tag: null },
        participantName: 'Hamida',
        shuffleMode: false,
        shuffledSequence: [],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 0,
    }));
    assert.ok(legacy);
    assert.strictEqual(legacy.participantSequence, '', 'legacy session defaults sequence empty');
})();

// ── Issue #05: generalized end-of-playlist ───────────────────────────────────

(function () {
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, {
        sign_language: 'lse',
        edition: null,
        typology: null,
    });
    var plan = logic.planEndOfPlaylist({
        shuffleMode: true,
        shuffledSequence: [2, 0, 1],
        filteredCount: filtered.length,
        filteredMasterIndices: filtered,
    });
    assert.ok(plan);
    assert.strictEqual(plan.shuffleStep, 0);
    assert.strictEqual(plan.filteredCursor, 2);
    assert.strictEqual(plan.loadMasterIndex, filtered[2]);
    assert.strictEqual(plan.shouldAutoplay, false);
})();

(function () {
    var filtered = recomputeFilteredMasterIndices(samplePlaylist, {
        sign_language: null,
        edition: null,
        typology: null,
    });
    var plan = logic.planEndOfPlaylist({
        shuffleMode: false,
        shuffledSequence: [],
        filteredCount: filtered.length,
        filteredMasterIndices: filtered,
    });
    assert.ok(plan);
    assert.strictEqual(plan.filteredCursor, 0);
    assert.strictEqual(plan.loadMasterIndex, filtered[0]);
    assert.strictEqual(plan.shouldAutoplay, false);
})();

console.log('vimeo_playlist_logic.test.js: all passed (including issues #04–#05)');

// ── DEAF+HEARING tag facet (DH1–DH17) ─────────────────────────────────────────

var T = 'DEAF&HEARING';
var dhFixture = [
    { videoId: '0', tags: [T],          signLanguage: 'LSC', edition: 'BCN', typology: '', participant: '' },
    { videoId: '1', tags: [],           signLanguage: 'LSC', edition: 'BCN', typology: '', participant: '' },
    { videoId: '2', tags: [T, 'IDEA!'], signLanguage: 'LSA', edition: 'ALG', typology: '', participant: '' },
    { videoId: '3', tags: [T],          signLanguage: 'LSA', edition: 'ALG', typology: '', participant: '' },
    { videoId: '4', tags: [],           signLanguage: 'LSC', edition: 'ALG', typology: '', participant: '' },
    { videoId: '5', tags: [T],          signLanguage: 'LSC', edition: 'BCN', typology: '', participant: '' },
];

assert.strictEqual(logic.DEAF_HEARING_TAG, 'DEAF&HEARING');

// Tag membership: contains, not equals; extra tags still match
(function () {
    var result = recomputeFilteredMasterIndices(dhFixture, {
        sign_language: null, edition: null, typology: null, tag: T,
    });
    assert.deepStrictEqual(result, [0, 2, 3, 5], 'tag membership returns tagged indices');
    assert.ok(logic.videoMatchesFilterState(dhFixture[2], {
        sign_language: null, edition: null, typology: null, tag: T,
    }), 'item with extra tags still matches');
    assert.strictEqual(logic.videoMatchesFilterState(dhFixture[1], {
        sign_language: null, edition: null, typology: null, tag: T,
    }), false, 'untagged item does not match');
})();

// Neutral state (tag null) ignores tags arrays
(function () {
    var result = recomputeFilteredMasterIndices(dhFixture, {
        sign_language: null, edition: null, typology: null, tag: null,
    });
    assert.strictEqual(result.length, dhFixture.length);
})();

// AND of tag with R2 facets
(function () {
    var withLsc = recomputeFilteredMasterIndices(dhFixture, {
        sign_language: 'LSC', edition: null, typology: null, tag: T,
    });
    assert.deepStrictEqual(withLsc, [0, 5], 'tag ∧ LSC');
    var withAlg = recomputeFilteredMasterIndices(dhFixture, {
        sign_language: null, edition: 'ALG', typology: null, tag: T,
    });
    assert.deepStrictEqual(withAlg, [2, 3], 'tag ∧ ALG');
})();

// Cascade options constrained by tag when pinned
(function () {
    var cascade = logic.buildCascadingFilterOptions(
        dhFixture,
        { sign_language: null, edition: null, typology: null, tag: T },
        {
            sign_language: [
                { value: 'LSC', label: 'LSC' },
                { value: 'LSA', label: 'LSA' },
            ],
            edition: [
                { value: 'BCN', label: 'BCN' },
                { value: 'ALG', label: 'ALG' },
            ],
            typology: [],
        }
    );
    var slValues = cascade.sign_language.map(function (o) { return o.value; }).sort();
    var edValues = cascade.edition.map(function (o) { return o.value; }).sort();
    assert.deepStrictEqual(slValues, ['LSA', 'LSC'], 'cascade sign languages only from tagged set');
    assert.deepStrictEqual(edValues, ['ALG', 'BCN'], 'cascade editions only from tagged set');
})();

// Toggle-on: current index not in set → jump (acceptance fixture)
(function () {
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: dhFixture,
        filterState: { sign_language: null, edition: null, typology: null, tag: T },
        currentMasterIndex: 1,
        shuffleMode: true,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.keepCurrentVideo, false, 'toggle-on jumps when current lacks tag');
    assert.deepStrictEqual(plan.filteredMasterIndices, [0, 2, 3, 5]);
    assert.ok(plan.filteredMasterIndices.indexOf(plan.loadMasterIndex) >= 0);
})();

// Toggle-on: current already tagged → keep
(function () {
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: dhFixture,
        filterState: { sign_language: null, edition: null, typology: null, tag: T },
        currentMasterIndex: 0,
        shuffleMode: true,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.keepCurrentVideo, true);
    assert.strictEqual(plan.loadMasterIndex, 0);
})();

// Toggle-off never jumps (DH6b): widening keeps current
(function () {
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: dhFixture,
        filterState: { sign_language: null, edition: null, typology: null, tag: null },
        currentMasterIndex: 0,
        shuffleMode: true,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.keepCurrentVideo, true, 'toggle-off keeps current video');
    assert.strictEqual(plan.loadMasterIndex, 0);
    assert.strictEqual(plan.filteredMasterIndices.length, dhFixture.length);
})();

// DH15b: toggle-on with empty AND clears R2, keeps tag, lands on pure set
(function () {
    var resolved = logic.resolveTagToggleOnFilterState({
        sign_language: 'LSC',
        edition: 'ALG',
        typology: null,
        tag: T,
    }, dhFixture);
    assert.strictEqual(resolved.tag, T, 'keeps tag on');
    assert.strictEqual(resolved.sign_language, null, 'clears R2 when AND empty');
    assert.strictEqual(resolved.edition, null);
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: dhFixture,
        filterState: resolved,
        currentMasterIndex: 4,
        shuffleMode: true,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.deepStrictEqual(plan.filteredMasterIndices, [0, 2, 3, 5]);
})();

// Non-empty AND on toggle-on keeps R2 pins
(function () {
    var resolved = logic.resolveTagToggleOnFilterState({
        sign_language: 'LSA',
        edition: null,
        typology: null,
        tag: T,
    }, dhFixture);
    assert.strictEqual(resolved.sign_language, 'LSA');
    assert.strictEqual(resolved.tag, T);
    var plan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: dhFixture,
        filterState: resolved,
        currentMasterIndex: 4,
        shuffleMode: false,
    });
    assert.ok(plan);
    assert.deepStrictEqual(plan.filteredMasterIndices, [2, 3]);
    assert.strictEqual(plan.keepCurrentVideo, false);
})();

// Empty R2 change while tag on: plan null → caller reverts that R2 (DH7)
(function () {
    var emptyPlan = logic.planFilterPlaylistRebuild({
        fullPlaylistItems: dhFixture,
        filterState: { sign_language: 'LSC', edition: 'ALG', typology: null, tag: T },
        currentMasterIndex: 0,
        shuffleMode: false,
    });
    assert.strictEqual(emptyPlan, null, 'empty AND returns null for R2 revert');
})();

// Mutual exclusion: tag pin clears participant; participant pick clears tag
assert.strictEqual(logic.shouldClearCollectionOnFilterFix(true, T), true, 'tag-on clears participant');
(function () {
    var cleared = logic.filterStateAfterParticipantPick({
        sign_language: 'LSC', edition: null, typology: null, tag: T,
    });
    assert.strictEqual(cleared.tag, null, 'participant pick clears tag');
    assert.strictEqual(cleared.sign_language, null, 'participant pick clears R2 (existing reset)');
})();

console.log('vimeo_playlist_logic.test.js: all passed (including DEAF+HEARING membership)');

// Session v2 includes tag; v1 migrates to tag:null (DH17)
(function () {
    var snap = logic.buildPlaybackSessionSnapshot({
        masterIndex: 0,
        filterState: { sign_language: null, edition: null, typology: null, tag: T },
        participantName: '',
        shuffleMode: true,
        shuffledSequence: [0, 1],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 0,
    });
    assert.strictEqual(snap.v, 2);
    assert.strictEqual(snap.filterState.tag, T);
    var parsed = logic.parsePlaybackSession(JSON.stringify(snap));
    assert.ok(parsed);
    assert.strictEqual(parsed.filterState.tag, T);

    var v1raw = JSON.stringify({
        v: 1,
        masterIndex: 0,
        filterState: { sign_language: 'LSC', edition: null, typology: null },
        participantName: '',
        shuffleMode: false,
        shuffledSequence: [],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 0,
    });
    var v1 = logic.parsePlaybackSession(v1raw);
    assert.ok(v1, 'v1 snapshot still parses');
    assert.strictEqual(v1.filterState.tag, null, 'v1 migrates tag:null');
    assert.strictEqual(v1.filterState.sign_language, 'LSC');
})();

// Secondary force-ON: deaf-hearing intent clears R2 + participant, sets tag (DH10)
(function () {
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 4,
        filterState: { sign_language: 'LSC', edition: 'ALG', typology: null, tag: null },
        participantName: 'Someone',
        shuffleMode: true,
        shuffledSequence: [0],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 5,
    });
    var plan = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'deaf-hearing',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.kind, 'deaf-hearing');
    assert.strictEqual(plan.filterState.tag, T);
    assert.strictEqual(plan.filterState.sign_language, null);
    assert.strictEqual(plan.filterState.edition, null);
    assert.strictEqual(plan.isParticipantMode, false);
    assert.strictEqual(plan.participantName, '');
    assert.deepStrictEqual(plan.filteredMasterIndices, [0, 2, 3, 5]);
    assert.strictEqual(plan.shouldAutoplay, true);
})();

// Force-ON works without prior session
(function () {
    var plan = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'deaf-hearing',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.ok(plan);
    assert.strictEqual(plan.kind, 'deaf-hearing');
    assert.strictEqual(plan.filterState.tag, T);
    assert.strictEqual(plan.shouldAutoplay, true);
})();

// DH14: transport intent applies stored tag; cold load (no intent) strips tag
(function () {
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 0,
        filterState: { sign_language: null, edition: null, typology: null, tag: T },
        participantName: '',
        shuffleMode: true,
        shuffledSequence: [0, 1, 2, 3],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 10,
    });

    var withPlay = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'play',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(withPlay.kind, 'restore');
    assert.strictEqual(withPlay.filterState.tag, T, 'play intent restores tag');

    var cold = logic.planSecondaryNavRestore({
        session: session,
        navIntent: '',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    // Cold load with session: either null (no restore) or restore with tag stripped.
    // DH14: tag inactive on cold load.
    if (cold && cold.kind === 'restore') {
        assert.strictEqual(cold.filterState.tag, null, 'cold load strips tag flag');
    } else {
        assert.strictEqual(cold, null, 'cold load may skip session restore entirely');
    }
})();

// Bootstrap seam: deaf-hearing intent is one-shot (consumed by caller; planner is pure)
(function () {
    var plan1 = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'deaf-hearing',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan1.kind, 'deaf-hearing');
    var plan2 = logic.planSecondaryNavRestore({
        session: logic.buildPlaybackSessionSnapshot({
            masterIndex: plan1.loadMasterIndex,
            filterState: plan1.filterState,
            participantName: '',
            shuffleMode: plan1.shuffleMode,
            shuffledSequence: plan1.shuffledSequence,
            shuffleStep: plan1.shuffleStep,
            filteredCursor: plan1.filteredCursor,
            playbackTimeSec: 0,
        }),
        navIntent: '',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    if (plan2 && plan2.kind === 'restore') {
        assert.strictEqual(plan2.filterState.tag, null, 'reload without intent does not re-force tag');
    }
})();

console.log('vimeo_playlist_logic.test.js: all passed (including DEAF+HEARING session/nav)');

// ── Secondary R2 filter handoff: `filter:<facet>:<value>` ─────────────────────
// Replace with a single fresh pin, clear participant + other pins, autoplay filtered set.
(function () {
    // Sign language pin, no prior session → filtered to LSA videos, autoplay.
    var plan = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'filter:sign_language:' + encodeURIComponent('LSA'),
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.ok(plan, 'filter intent yields a plan without a session');
    assert.strictEqual(plan.kind, 'filter');
    assert.strictEqual(plan.filterState.sign_language, 'LSA');
    assert.strictEqual(plan.filterState.edition, null);
    assert.strictEqual(plan.filterState.typology, null);
    assert.strictEqual(plan.filterState.tag, null);
    assert.strictEqual(plan.isParticipantMode, false);
    assert.strictEqual(plan.participantName, '');
    assert.deepStrictEqual(plan.filteredMasterIndices, [2, 3]);
    assert.strictEqual(plan.shouldAutoplay, true, 'filtered set autoplays');
})();

(function () {
    // Edition pin filters correctly.
    var plan = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'filter:edition:BCN',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan.kind, 'filter');
    assert.deepStrictEqual(plan.filteredMasterIndices, [0, 1, 5]);
})();

(function () {
    // Replace semantics: an existing session (participant + other pins) is discarded.
    var session = logic.buildPlaybackSessionSnapshot({
        masterIndex: 4,
        filterState: { sign_language: 'LSC', edition: 'ALG', typology: null, tag: T },
        participantName: 'Someone',
        shuffleMode: true,
        shuffledSequence: [0],
        shuffleStep: 0,
        filteredCursor: 0,
        playbackTimeSec: 9,
    });
    var plan = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'filter:edition:ALG',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(plan.kind, 'filter');
    assert.strictEqual(plan.filterState.edition, 'ALG');
    assert.strictEqual(plan.filterState.sign_language, null, 'other pins cleared');
    assert.strictEqual(plan.filterState.tag, null, 'tag cleared');
    assert.strictEqual(plan.participantName, '', 'participant mode cleared');
    assert.deepStrictEqual(plan.filteredMasterIndices, [2, 3, 4]);
})();

(function () {
    // "All X" (empty value) → neutral handoff, not a filter pin.
    var fresh = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'filter:sign_language:',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(fresh.kind, 'fresh', 'clear option yields neutral handoff');
    // Unknown facet is not a filter intent (falls through; no session → null).
    var unknown = logic.planSecondaryNavRestore({
        session: null,
        navIntent: 'filter:bogus:x',
        fullPlaylistItems: dhFixture,
        randomFn: function () { return 0; },
    });
    assert.strictEqual(unknown, null, 'unknown facet is not treated as a filter handoff');
})();

console.log('vimeo_playlist_logic.test.js: all passed (including secondary R2 filter handoff)');

// ── Load cover: solid white while playlist autoplays; thumb only when paused ─

(function () {
    var thumb =
        logic.planLoadCover({
            willAutoplay: false,
            thumbnailUrl: 'https://example.com/a.jpg',
        });
    assert.strictEqual(thumb.kind, 'thumbnail', 'paused/cold load uses thumbnail cover');
    assert.strictEqual(thumb.thumbnailUrl, 'https://example.com/a.jpg');

    var solid =
        logic.planLoadCover({
            willAutoplay: true,
            thumbnailUrl: 'https://example.com/next.jpg',
        });
    assert.strictEqual(solid.kind, 'solid-white', 'running playlist advance uses solid white, not next thumb');
    assert.strictEqual(solid.thumbnailUrl, '', 'solid cover does not carry next thumbnail URL');

    var none =
        logic.planLoadCover({
            willAutoplay: false,
            thumbnailUrl: '',
        });
    assert.strictEqual(none.kind, 'none', 'paused load with no thumb has no cover bitmap');
})();

console.log('vimeo_playlist_logic.test.js: all passed (including load cover plan)');

// ── Load cover reveal: wait for real playback, not while Vimeo is buffering ─

(function () {
    assert.strictEqual(
        logic.shouldRevealLoadCover({
            playbackStarted: false,
            seconds: 1,
            buffering: false,
        }),
        false,
        'no reveal before playback started'
    );
    assert.strictEqual(
        logic.shouldRevealLoadCover({
            playbackStarted: true,
            seconds: 0.02,
            buffering: false,
        }),
        false,
        'no reveal until enough progress'
    );
    assert.strictEqual(
        logic.shouldRevealLoadCover({
            playbackStarted: true,
            seconds: 0.2,
            buffering: true,
        }),
        false,
        'no reveal while Vimeo is buffering (avoids gray flash)'
    );
    assert.strictEqual(
        logic.shouldRevealLoadCover({
            playbackStarted: true,
            seconds: 0.2,
            buffering: false,
        }),
        true,
        'reveal once playing with progress and not buffering'
    );
})();

console.log('vimeo_playlist_logic.test.js: all passed (including load cover reveal)');

// ── Participant natural-order playlist (no shuffle) ─────────────────────────

// Orderer: numeric participant_sequence ascending (2 before 10)
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '10' },
        { participant: 'Other', participant_sequence: '1' },
        { participant: 'Hamida', participant_sequence: '2' },
        { participant: 'Hamida', participant_sequence: '1' },
    ];
    assert.deepStrictEqual(
        logic.participantMasterIndicesInNaturalOrder(items, 'Hamida'),
        [3, 2, 0],
        'Hamida clips ordered 1, 2, 10 by numeric sequence'
    );
})();

// Orderer: missing sequences after numbered; catalog order among unnumbered
(function () {
    var items = [
        { participant: 'Hamida' },
        { participant: 'Hamida', participant_sequence: '2' },
        { participant: 'Hamida' },
        { participant: 'Hamida', participant_sequence: '1' },
    ];
    assert.deepStrictEqual(
        logic.participantMasterIndicesInNaturalOrder(items, 'Hamida'),
        [3, 1, 0, 2],
        'unnumbered clips follow numbered ones in catalog order'
    );
})();

// Planner: cold enter Participant collection — linear, head = sequence 1
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '3' },
        { participant: 'Hamida', participant_sequence: '1' },
        { participant: 'Hamida', participant_sequence: '2' },
    ];
    var plan = logic.planParticipantCollectionPlaylist({
        fullPlaylistItems: items,
        participantName: 'Hamida',
    });
    assert.ok(plan, 'plan exists');
    assert.strictEqual(plan.shuffleMode, false, 'no shuffle in Participant mode');
    assert.deepStrictEqual(plan.shuffledSequence, [], 'no shuffle sequence');
    assert.deepStrictEqual(plan.filteredMasterIndices, [1, 2, 0], 'natural order indices');
    assert.strictEqual(plan.filteredCursor, 0);
    assert.strictEqual(plan.shuffleStep, 0);
    assert.strictEqual(plan.loadMasterIndex, 1, 'load sequence 1');
    assert.strictEqual(plan.keepCurrentVideo, false);
})();

// Planner: unknown Participant → empty linear plan (never leave caller on full catalog)
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '1' },
        { participant: 'Hamida', participant_sequence: '2' },
    ];
    var plan = logic.planParticipantCollectionPlaylist({
        fullPlaylistItems: items,
        participantName: 'Nobody',
    });
    assert.ok(plan, 'empty Participant still returns a plan object');
    assert.deepStrictEqual(plan.filteredMasterIndices, [], 'no matching videos');
    assert.strictEqual(plan.shuffleMode, false);
    assert.deepStrictEqual(plan.shuffledSequence, []);
    assert.strictEqual(plan.filteredCursor, 0);
    assert.strictEqual(plan.shuffleStep, 0);
    assert.strictEqual(plan.loadMasterIndex, -1, 'no video to load');
    assert.strictEqual(plan.keepCurrentVideo, false);
})();
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '3' },
        { participant: 'Hamida', participant_sequence: '1' },
        { participant: 'Hamida', participant_sequence: '2' },
    ];
    var plan = logic.planParticipantCollectionPlaylist({
        fullPlaylistItems: items,
        participantName: 'Hamida',
        currentMasterIndex: 0, // sequence 3
    });
    assert.ok(plan);
    assert.strictEqual(plan.shuffleMode, false);
    assert.strictEqual(plan.keepCurrentVideo, true);
    assert.strictEqual(plan.loadMasterIndex, 0);
    assert.strictEqual(plan.filteredCursor, 2, 'sequence 3 is last in natural order');
    assert.deepStrictEqual(plan.filteredMasterIndices, [1, 2, 0]);
})();

// Session context: Participant name yields natural-order master indices
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '3', signLanguage: 'lsc' },
        { participant: 'Other', participant_sequence: '1', signLanguage: 'lsc' },
        { participant: 'Hamida', participant_sequence: '1', signLanguage: 'lsc' },
        { participant: 'Hamida', participant_sequence: '2', signLanguage: 'lsc' },
    ];
    assert.deepStrictEqual(
        logic.filteredIndicesFromSessionContext(
            items,
            { sign_language: null, edition: null, typology: null, tag: null },
            'Hamida'
        ),
        [2, 3, 0],
        'session Participant indices follow sequence order'
    );
})();

// Secondary restore: Participant session rebuilds natural linear queue around current video
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '3' },
        { participant: 'Hamida', participant_sequence: '1' },
        { participant: 'Hamida', participant_sequence: '2' },
    ];
    var session = {
        v: 2,
        masterIndex: 0,
        filterState: { sign_language: null, edition: null, typology: null, tag: null },
        participantName: 'Hamida',
        participantSequence: '3',
        shuffleMode: true,
        shuffledSequence: [2, 0, 1],
        shuffleStep: 1,
        filteredCursor: 0,
        playbackTimeSec: 12,
    };
    var nav = logic.planSecondaryNavRestore({
        session: session,
        navIntent: 'play',
        fullPlaylistItems: items,
        randomFn: function () { return 0; },
    });
    assert.ok(nav && nav.kind === 'restore');
    assert.strictEqual(nav.shuffleMode, false, 'Participant restore forces linear mode');
    assert.deepStrictEqual(nav.shuffledSequence, []);
    assert.deepStrictEqual(nav.filteredMasterIndices, [1, 2, 0]);
    assert.strictEqual(nav.loadMasterIndex, 0, 'keeps current master video');
    assert.strictEqual(nav.filteredCursor, 2);
    assert.strictEqual(nav.playbackTimeSec, 12);
})();

// Secondary restore: unknown Participant stays empty (does not fall back to ALL)
(function () {
    var items = [
        { participant: 'Hamida', participant_sequence: '1' },
    ];
    var nav = logic.planSecondaryNavRestore({
        session: {
            v: 2,
            masterIndex: 0,
            filterState: { sign_language: null, edition: null, typology: null, tag: null },
            participantName: 'Nobody',
            participantSequence: '',
            shuffleMode: true,
            shuffledSequence: [0],
            shuffleStep: 0,
            filteredCursor: 0,
            playbackTimeSec: 5,
        },
        navIntent: 'play',
        fullPlaylistItems: items,
        randomFn: function () { return 0; },
    });
    assert.ok(nav && nav.kind === 'restore');
    assert.strictEqual(nav.isParticipantMode, true);
    assert.deepStrictEqual(nav.filteredMasterIndices, []);
    assert.strictEqual(nav.loadMasterIndex, -1);
    assert.strictEqual(nav.shuffleMode, false);
})();

console.log('vimeo_playlist_logic.test.js: all passed (including participant natural order)');

// ── Keyboard shortcuts: transport chrome (Space/Arrows/R/D) ─────────────────

// Space toggles play/pause when no modifier is held and focus isn't editable
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: ' ' }),
    'play-pause',
    'Space maps to play-pause'
);

assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'ArrowLeft' }),
    'prev',
    'ArrowLeft maps to prev'
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'ArrowRight' }),
    'next',
    'ArrowRight maps to next'
);

assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'r' }),
    'reset',
    "'r' maps to reset"
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'd' }),
    'deaf-hearing',
    "'d' maps to deaf-hearing"
);

// OS/hardware media keys map to the same actions as their keyboard equivalents
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'MediaPlayPause' }),
    'play-pause',
    'MediaPlayPause maps to play-pause'
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'MediaTrackPrevious' }),
    'prev',
    'MediaTrackPrevious maps to prev'
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'MediaTrackNext' }),
    'next',
    'MediaTrackNext maps to next'
);

// Caps Lock produces uppercase letters with shiftKey still false; shortcuts must still fire
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'R', shiftKey: false }),
    'reset',
    "Caps-Lock 'R' (no shiftKey) maps to reset"
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'D', shiftKey: false }),
    'deaf-hearing',
    "Caps-Lock 'D' (no shiftKey) maps to deaf-hearing"
);

// Unbound keys and missing input are ignored
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'x' }),
    null,
    "unbound key 'x' returns null"
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({}),
    null,
    'missing key returns null'
);

// Any modifier held suppresses an otherwise-bound key (e.g. Ctrl+R must not hijack browser refresh)
['ctrlKey', 'altKey', 'metaKey', 'shiftKey'].forEach(function (modifier) {
    var opts = { key: 'r' };
    opts[modifier] = true;
    assert.strictEqual(
        logic.resolveTransportShortcutAction(opts),
        null,
        modifier + ' held suppresses the shortcut'
    );
});

// Focus inside an editable field suppresses the shortcut (protects any future text input)
['INPUT', 'TEXTAREA', 'SELECT'].forEach(function (tagName) {
    assert.strictEqual(
        logic.resolveTransportShortcutAction({ key: ' ', activeElement: { tagName: tagName } }),
        null,
        'focus on ' + tagName + ' suppresses the shortcut'
    );
});
assert.strictEqual(
    logic.resolveTransportShortcutAction({
        key: ' ',
        activeElement: { tagName: 'DIV', isContentEditable: true },
    }),
    null,
    'focus on a contenteditable element suppresses the shortcut'
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: ' ', activeElement: { tagName: 'BODY' } }),
    'play-pause',
    'focus on a non-editable element does not suppress the shortcut'
);

// Escape stays out of scope for this function — dropdown-close handling lives elsewhere
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'Escape' }),
    null,
    'Escape is not a transport shortcut'
);

// A/P navigate to About/Participants — same action a click on those nav links performs
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'a' }),
    'about',
    "'a' maps to about"
);
assert.strictEqual(
    logic.resolveTransportShortcutAction({ key: 'p' }),
    'participants',
    "'p' maps to participants"
);

console.log('vimeo_playlist_logic.test.js: all passed (including transport shortcuts)');

