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

    /** @typedef {{ sign_language: string|null, edition: string|null, typology: string|null }} VpcFilterState */

    /**
     * Playlist item field for an R2 facet key.
     * @param {string} facet
     * @returns {string}
     */
    function facetItemField(facet) {
        if (facet === 'sign_language') return 'signLanguage';
        if (facet === 'edition') return 'edition';
        if (facet === 'typology') return 'typology';
        return facet;
    }

    /**
     * @param {{ signLanguage?: string, edition?: string, typology?: string }} item
     * @param {string} facet
     * @returns {string}
     */
    function itemFacetValue(item, facet) {
        if (!item) return '';
        var field = facetItemField(facet);
        return typeof item[field] === 'string' ? item[field] : '';
    }

    /**
     * Recompute master-playlist indices matching filterState (AND composition).
     * @param {Array<{ signLanguage?: string, edition?: string, typology?: string }>} fullPlaylistItems
     * @param {VpcFilterState} filterState
     * @returns {number[]}
     */
    function recomputeFilteredMasterIndices(fullPlaylistItems, filterState) {
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var hasFilter = filterState.sign_language !== null
            || filterState.edition !== null
            || filterState.typology !== null;
        if (!hasFilter || items.length === 0) {
            return items.map(function (_, ix) { return ix; });
        }
        return items
            .map(function (item, ix) {
                if (filterState.sign_language !== null
                    && itemFacetValue(item, 'sign_language') !== filterState.sign_language) {
                    return -1;
                }
                if (filterState.edition !== null
                    && itemFacetValue(item, 'edition') !== filterState.edition) {
                    return -1;
                }
                if (filterState.typology !== null
                    && itemFacetValue(item, 'typology') !== filterState.typology) {
                    return -1;
                }
                return ix;
            })
            .filter(function (ix) { return ix >= 0; });
    }

    /**
     * Whether a video satisfies every fixed facet in filterState.
     * @param {{ signLanguage?: string, edition?: string, typology?: string }} item
     * @param {VpcFilterState} filterState
     * @returns {boolean}
     */
    function videoMatchesFilterState(item, filterState) {
        if (filterState.sign_language !== null
            && itemFacetValue(item, 'sign_language') !== filterState.sign_language) {
            return false;
        }
        if (filterState.edition !== null
            && itemFacetValue(item, 'edition') !== filterState.edition) {
            return false;
        }
        if (filterState.typology !== null
            && itemFacetValue(item, 'typology') !== filterState.typology) {
            return false;
        }
        return true;
    }

    /**
     * Studio label for a facet value from the full option catalog.
     * @param {string} facet
     * @param {string} value
     * @param {Array<{ value?: string, label?: string }>} options
     * @returns {string}
     */
    function labelForFacetValue(facet, value, options) {
        var list = Array.isArray(options) ? options : [];
        var i;
        for (i = 0; i < list.length; i++) {
            if (list[i] && list[i].value === value) {
                return list[i].label || value;
            }
        }
        return value;
    }

    /**
     * Picker button face: fixed filter label when pinned, else live readout (D14′).
     * @param {{ signLanguage?: string, edition?: string, typology?: string }} item
     * @param {string} facet
     * @param {VpcFilterState} filterState
     * @param {Array<{ value?: string, label?: string }>} options
     * @param {string} genericLabel
     * @returns {{ label: string, fixed: boolean }}
     */
    function resolveFilterPickerReadout(item, facet, filterState, options, genericLabel) {
        var fixedValue = filterState[facet];
        if (fixedValue !== null && fixedValue !== undefined && fixedValue !== '') {
            return {
                label: labelForFacetValue(facet, fixedValue, options),
                fixed: true,
            };
        }
        var liveValue = itemFacetValue(item, facet);
        if (liveValue !== '') {
            return {
                label: labelForFacetValue(facet, liveValue, options),
                fixed: false,
            };
        }
        return { label: genericLabel, fixed: false };
    }

    /**
     * Distinct facet values present in a subset of the master playlist.
     * @param {Array<{ signLanguage?: string, edition?: string, typology?: string }>} fullPlaylistItems
     * @param {number[]} masterIndices
     * @param {string} facet
     * @returns {string[]}
     */
    function distinctFacetValuesInSubset(fullPlaylistItems, masterIndices, facet) {
        var seen = {};
        var out = [];
        var i;
        for (i = 0; i < masterIndices.length; i++) {
            var ix = masterIndices[i];
            var item = fullPlaylistItems[ix];
            var val = itemFacetValue(item, facet);
            if (val !== '' && !seen[val]) {
                seen[val] = true;
                out.push(val);
            }
        }
        return out;
    }

    /**
     * Cascading dropup options (D17′): each facet lists only values in the
     * playlist filtered by the other fixed facets.
     * @param {Array<{ signLanguage?: string, edition?: string, typology?: string }>} fullPlaylistItems
     * @param {VpcFilterState} filterState
     * @param {{ sign_language?: Array<{ value?: string, label?: string }>, edition?: Array<{ value?: string, label?: string }>, typology?: Array<{ value?: string, label?: string }> }} allOptionsByFacet
     * @returns {{ sign_language: Array<{ value: string, label: string }>, edition: Array<{ value: string, label: string }>, typology: Array<{ value: string, label: string }> }}
     */
    function buildCascadingFilterOptions(fullPlaylistItems, filterState, allOptionsByFacet) {
        var facets = ['sign_language', 'edition', 'typology'];
        var result = {
            sign_language: [],
            edition: [],
            typology: [],
        };
        var f;
        for (f = 0; f < facets.length; f++) {
            var facet = facets[f];
            var relaxed = {
                sign_language: filterState.sign_language,
                edition: filterState.edition,
                typology: filterState.typology,
            };
            relaxed[facet] = null;
            var subset = recomputeFilteredMasterIndices(fullPlaylistItems, relaxed);
            var presentValues = distinctFacetValuesInSubset(fullPlaylistItems, subset, facet);
            var catalog = allOptionsByFacet && allOptionsByFacet[facet]
                ? allOptionsByFacet[facet]
                : [];
            var opts = [];
            var i;
            for (i = 0; i < presentValues.length; i++) {
                var val = presentValues[i];
                opts.push({
                    value: val,
                    label: labelForFacetValue(facet, val, catalog),
                });
            }
            opts.sort(function (a, b) {
                return String(a.label).localeCompare(String(b.label), undefined, { sensitivity: 'base' });
            });
            result[facet] = opts;
        }
        return result;
    }

    /**
     * Shuffle indices 0..count-1 excluding one fixed index (placed at step 0).
     * @param {number} count
     * @param {number} fixedIndex
     * @param {() => number} [randomFn]
     * @returns {number[]}
     */
    function buildShuffledSequenceWithHead(count, fixedIndex, randomFn) {
        if (count <= 0) return [];
        if (fixedIndex < 0 || fixedIndex >= count) {
            return buildShuffledSequence(count, randomFn);
        }
        var rest = [];
        var i;
        for (i = 0; i < count; i++) {
            if (i !== fixedIndex) rest.push(i);
        }
        var shuffledRest = buildShuffledSequence(rest.length, randomFn);
        var seq = [fixedIndex];
        for (i = 0; i < shuffledRest.length; i++) {
            seq.push(rest[shuffledRest[i]]);
        }
        return seq;
    }

    /**
     * Rebuild filtered playlist after fixing/clearing a filter (D22 keep-if-matches).
     * @param {{
     *   fullPlaylistItems: Array<{ signLanguage?: string, edition?: string, typology?: string }>,
     *   filterState: VpcFilterState,
     *   currentMasterIndex: number,
     *   shuffleMode: boolean,
     *   randomFn?: () => number
     * }} opts
     * @returns {{
     *   filteredMasterIndices: number[],
     *   filteredCursor: number,
     *   shuffleStep: number,
     *   shuffledSequence: number[],
     *   shuffleMode: boolean,
     *   loadMasterIndex: number,
     *   keepCurrentVideo: boolean
     * } | null}
     */
    function planFilterPlaylistRebuild(opts) {
        var items = opts.fullPlaylistItems;
        var filterState = opts.filterState;
        var currentIx = typeof opts.currentMasterIndex === 'number' ? opts.currentMasterIndex : 0;
        var shuffleMode = !!opts.shuffleMode;
        var randomFn = opts.randomFn || Math.random;

        var filteredMasterIndices = recomputeFilteredMasterIndices(items, filterState);
        var fc = filteredMasterIndices.length;
        if (fc === 0) return null;

        var currentItem = items[currentIx];
        var matches = videoMatchesFilterState(currentItem, filterState)
            && filteredMasterIndices.indexOf(currentIx) >= 0;

        if (matches) {
            var posInFiltered = filteredMasterIndices.indexOf(currentIx);
            if (shuffleMode) {
                return {
                    filteredMasterIndices: filteredMasterIndices,
                    filteredCursor: posInFiltered,
                    shuffleStep: 0,
                    shuffledSequence: buildShuffledSequenceWithHead(fc, posInFiltered, randomFn),
                    shuffleMode: true,
                    loadMasterIndex: currentIx,
                    keepCurrentVideo: true,
                };
            }
            return {
                filteredMasterIndices: filteredMasterIndices,
                filteredCursor: posInFiltered,
                shuffleStep: 0,
                shuffledSequence: [],
                shuffleMode: false,
                loadMasterIndex: currentIx,
                keepCurrentVideo: true,
            };
        }

        if (shuffleMode) {
            var seq = buildShuffledSequence(fc, randomFn);
            return {
                filteredMasterIndices: filteredMasterIndices,
                filteredCursor: seq[0],
                shuffleStep: 0,
                shuffledSequence: seq,
                shuffleMode: true,
                loadMasterIndex: filteredMasterIndices[seq[0]],
                keepCurrentVideo: false,
            };
        }
        return {
            filteredMasterIndices: filteredMasterIndices,
            filteredCursor: 0,
            shuffleStep: 0,
            shuffledSequence: [],
            shuffleMode: false,
            loadMasterIndex: filteredMasterIndices[0],
            keepCurrentVideo: false,
        };
    }

    /**
     * Whether fixing an R2 filter should clear participant/tag collection (D18′).
     * @param {boolean} isParticipantMode
     * @param {string|null} newFacetValue  null when clearing via "All"
     * @returns {boolean}
     */
    function shouldClearCollectionOnFilterFix(isParticipantMode, newFacetValue) {
        return !!isParticipantMode && newFacetValue !== null && newFacetValue !== '';
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
        facetItemField: facetItemField,
        itemFacetValue: itemFacetValue,
        recomputeFilteredMasterIndices: recomputeFilteredMasterIndices,
        videoMatchesFilterState: videoMatchesFilterState,
        labelForFacetValue: labelForFacetValue,
        resolveFilterPickerReadout: resolveFilterPickerReadout,
        distinctFacetValuesInSubset: distinctFacetValuesInSubset,
        buildCascadingFilterOptions: buildCascadingFilterOptions,
        buildShuffledSequenceWithHead: buildShuffledSequenceWithHead,
        planFilterPlaylistRebuild: planFilterPlaylistRebuild,
        shouldClearCollectionOnFilterFix: shouldClearCollectionOnFilterFix,
    };
}));
