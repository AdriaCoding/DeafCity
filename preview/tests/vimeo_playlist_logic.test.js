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

// Last video in shuffle: no next step
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

console.log('vimeo_playlist_logic.test.js: all passed');
