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

    /** Canonical Catalog Tag for the DEAF+HEARING chrome control (DH1). */
    var DEAF_HEARING_TAG = 'DEAF&HEARING';

    /**
     * Whether filterState.tag is pinned (array-membership facet).
     * @param {VpcFilterState} filterState
     * @returns {boolean}
     */
    function isTagPinned(filterState) {
        return !!(filterState && typeof filterState.tag === 'string' && filterState.tag !== '');
    }

    /**
     * Tag facet match: item.tags array membership (DH16).
     * @param {{ tags?: string[] }} item
     * @param {string} tag
     * @returns {boolean}
     */
    function itemHasTag(item, tag) {
        if (!item || !Array.isArray(item.tags) || typeof tag !== 'string' || tag === '') {
            return false;
        }
        return item.tags.indexOf(tag) >= 0;
    }

    /**
     * Recompute master-playlist indices matching filterState (AND composition).
     * @param {Array<{ signLanguage?: string, edition?: string, typology?: string, tags?: string[] }>} fullPlaylistItems
     * @param {VpcFilterState} filterState
     * @returns {number[]}
     */
    function recomputeFilteredMasterIndices(fullPlaylistItems, filterState) {
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var hasFilter = filterState.sign_language !== null
            || filterState.edition !== null
            || filterState.typology !== null
            || isTagPinned(filterState);
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
                if (isTagPinned(filterState) && !itemHasTag(item, filterState.tag)) {
                    return -1;
                }
                return ix;
            })
            .filter(function (ix) { return ix >= 0; });
    }

    /**
     * Whether a video satisfies every fixed facet in filterState.
     * @param {{ signLanguage?: string, edition?: string, typology?: string, tags?: string[] }} item
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
        if (isTagPinned(filterState) && !itemHasTag(item, filterState.tag)) {
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
     * Title-case a label for compact typology faces (D14″).
     * @param {string} label
     * @returns {string}
     */
    function titleCaseFacetLabel(label) {
        var s = String(label || '');
        if (s === '') {
            return '';
        }
        return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
    }

    /**
     * Compact face label for a facet value (D14″).
     * Uses short_label when present; otherwise facet-specific fallback.
     * @param {string} facet
     * @param {string} value
     * @param {Array<{ value?: string, label?: string, short_label?: string }>} options
     * @returns {string}
     */
    function compactFacetLabel(facet, value, options) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        var list = Array.isArray(options) ? options : [];
        var fullLabel = labelForFacetValue(facet, value, options);
        var i;
        for (i = 0; i < list.length; i++) {
            if (list[i] && list[i].value === value) {
                if (list[i].short_label) {
                    return list[i].short_label;
                }
                break;
            }
        }
        if (facet === 'sign_language') {
            var firstToken = fullLabel.split(/\s+/)[0];
            return firstToken || value;
        }
        if (facet === 'edition') {
            var stripped = fullLabel.replace(/^\d{4}\s+/, '').replace(/\s+\d{4}$/, '');
            return stripped || value;
        }
        if (facet === 'typology') {
            return titleCaseFacetLabel(fullLabel) || titleCaseFacetLabel(value) || value;
        }
        return fullLabel || value;
    }

    /**
     * Picker button face: fixed filter label when pinned, else live readout (D14′).
     * Face text uses compact labels (D14″); dropup options keep full studio labels.
     * @param {{ signLanguage?: string, edition?: string, typology?: string }} item
     * @param {string} facet
     * @param {VpcFilterState} filterState
     * @param {Array<{ value?: string, label?: string, short_label?: string }>} options
     * @param {string} genericLabel
     * @returns {{ label: string, fixed: boolean }}
     */
    function resolveFilterPickerReadout(item, facet, filterState, options, genericLabel) {
        var fixedValue = filterState[facet];
        if (fixedValue !== null && fixedValue !== undefined && fixedValue !== '') {
            return {
                label: compactFacetLabel(facet, fixedValue, options),
                fixed: true,
            };
        }
        var liveValue = itemFacetValue(item, facet);
        if (liveValue !== '') {
            return {
                label: compactFacetLabel(facet, liveValue, options),
                fixed: false,
            };
        }
        return { label: genericLabel, fixed: false };
    }

    /**
     * Index of a facet value in the studio-config catalog (preserves config order).
     * @param {string} value
     * @param {Array<{ value?: string, label?: string }>} catalog
     * @returns {number}
     */
    function catalogOptionIndex(value, catalog) {
        var list = Array.isArray(catalog) ? catalog : [];
        var i;
        for (i = 0; i < list.length; i++) {
            if (list[i] && list[i].value === value) {
                return i;
            }
        }
        return 999;
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
                tag: isTagPinned(filterState) ? filterState.tag : null,
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
                if (facet === 'edition') {
                    var orderA = catalogOptionIndex(a.value, catalog);
                    var orderB = catalogOptionIndex(b.value, catalog);
                    if (orderA !== orderB) {
                        return orderA - orderB;
                    }
                }
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

    /**
     * Empty R2 filter state (all facets unfixed).
     * @returns {VpcFilterState}
     */
    function emptyFilterState() {
        return {
            sign_language: null,
            edition: null,
            typology: null,
            tag: null,
        };
    }

    /**
     * Resolve filter state when turning the tag facet on (DH15b).
     * If tag ∧ current R2 is empty, clear all R2 pins and keep the tag.
     * @param {VpcFilterState} filterState  proposed state with tag already pinned
     * @param {Array<{ tags?: string[] }>} fullPlaylistItems
     * @returns {VpcFilterState}
     */
    function resolveTagToggleOnFilterState(filterState, fullPlaylistItems) {
        var proposed = {
            sign_language: filterState.sign_language !== undefined ? filterState.sign_language : null,
            edition: filterState.edition !== undefined ? filterState.edition : null,
            typology: filterState.typology !== undefined ? filterState.typology : null,
            tag: filterState.tag !== undefined ? filterState.tag : null,
        };
        if (!isTagPinned(proposed)) {
            return proposed;
        }
        var indices = recomputeFilteredMasterIndices(fullPlaylistItems, proposed);
        if (indices.length > 0) {
            return proposed;
        }
        return {
            sign_language: null,
            edition: null,
            typology: null,
            tag: proposed.tag,
        };
    }

    /**
     * Filter state after entering participant collection mode (clears R2 + tag).
     * @param {VpcFilterState} [_filterState]
     * @returns {VpcFilterState}
     */
    function filterStateAfterParticipantPick(_filterState) {
        return emptyFilterState();
    }

    /**
     * Reset control (D1′): clear all filters and collections, reshuffle unfiltered ALL,
     * land paused on a fresh random poster — never restart current video from t=0.
     * @param {{
     *   fullPlaylistItems: Array<{ signLanguage?: string, edition?: string, typology?: string }>,
     *   randomFn?: () => number
     * }} opts
     * @returns {{
     *   filterState: VpcFilterState,
     *   isParticipantMode: boolean,
     *   participantName: string,
     *   filteredMasterIndices: number[],
     *   filteredCursor: number,
     *   shuffleStep: number,
     *   shuffledSequence: number[],
     *   shuffleMode: boolean,
     *   loadMasterIndex: number,
     *   shouldAutoplay: boolean
     * } | null}
     */
    function planResetToNeutralAll(opts) {
        var items = opts.fullPlaylistItems;
        var randomFn = opts.randomFn || Math.random;
        var filterState = emptyFilterState();

        var filteredMasterIndices = recomputeFilteredMasterIndices(items, filterState);
        var fc = filteredMasterIndices.length;
        if (fc === 0) return null;

        var shuffledSequence = buildShuffledSequence(fc, randomFn);
        var filteredCursor = shuffledSequence[0];
        var loadMasterIndex = filteredMasterIndices[filteredCursor];

        return {
            filterState: filterState,
            isParticipantMode: false,
            participantName: '',
            filteredMasterIndices: filteredMasterIndices,
            filteredCursor: filteredCursor,
            shuffleStep: 0,
            shuffledSequence: shuffledSequence,
            shuffleMode: true,
            loadMasterIndex: loadMasterIndex,
            shouldAutoplay: false,
        };
    }

    /** sessionStorage key for cross-page playback context (issue #02). */
    var PLAYBACK_SESSION_KEY = 'vpc-playback-session';
    /** One-shot nav intent from About/Participants transport (issue #02). */
    var NAV_INTENT_KEY = 'vpc-nav-intent';

    /**
     * Build a serializable playback session snapshot.
     * @param {{
     *   masterIndex: number,
     *   filterState: VpcFilterState,
     *   participantName: string,
     *   shuffleMode: boolean,
     *   shuffledSequence: number[],
     *   shuffleStep: number,
     *   filteredCursor: number,
     *   playbackTimeSec?: number
     * }} opts
     * @returns {object}
     */
    function buildPlaybackSessionSnapshot(opts) {
        return {
            v: 2,
            masterIndex: typeof opts.masterIndex === 'number' ? opts.masterIndex : 0,
            filterState: {
                sign_language: opts.filterState.sign_language,
                edition: opts.filterState.edition,
                typology: opts.filterState.typology,
                tag: isTagPinned(opts.filterState) ? opts.filterState.tag : null,
            },
            participantName: typeof opts.participantName === 'string' ? opts.participantName : '',
            shuffleMode: !!opts.shuffleMode,
            shuffledSequence: Array.isArray(opts.shuffledSequence)
                ? opts.shuffledSequence.slice()
                : [],
            shuffleStep: typeof opts.shuffleStep === 'number' ? opts.shuffleStep : 0,
            filteredCursor: typeof opts.filteredCursor === 'number' ? opts.filteredCursor : 0,
            playbackTimeSec: typeof opts.playbackTimeSec === 'number' ? opts.playbackTimeSec : 0,
        };
    }

    /**
     * Normalize filterState from a session snapshot (v1→v2 migration for tag).
     * @param {object} rawFilter
     * @param {boolean} allowTag  DH14: only apply stored tag when authorized by nav-intent
     * @returns {VpcFilterState}
     */
    function filterStateFromSession(rawFilter, allowTag) {
        var fs = rawFilter && typeof rawFilter === 'object' ? rawFilter : {};
        return {
            sign_language: fs.sign_language !== undefined ? fs.sign_language : null,
            edition: fs.edition !== undefined ? fs.edition : null,
            typology: fs.typology !== undefined ? fs.typology : null,
            tag: allowTag && typeof fs.tag === 'string' && fs.tag !== '' ? fs.tag : null,
        };
    }

    /**
     * Parse a playback session JSON string, or null when invalid.
     * Accepts v:1 (migrates tag:null) and v:2 (DH17).
     * @param {string} raw
     * @returns {object|null}
     */
    function parsePlaybackSession(raw) {
        if (!raw || typeof raw !== 'string') return null;
        try {
            var data = JSON.parse(raw);
            if (!data || typeof data !== 'object') return null;
            if (data.v !== 1 && data.v !== 2) return null;
            if (typeof data.masterIndex !== 'number') return null;
            if (!data.filterState || typeof data.filterState !== 'object') return null;
            if (data.v === 1 || data.filterState.tag === undefined) {
                data.filterState.tag = null;
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    /**
     * Rebuild filtered master indices from session filter + optional participant mode.
     * @param {Array<{ participant?: string }>} fullPlaylistItems
     * @param {VpcFilterState} filterState
     * @param {string} participantName
     * @returns {number[]}
     */
    function filteredIndicesFromSessionContext(fullPlaylistItems, filterState, participantName) {
        var indices = recomputeFilteredMasterIndices(fullPlaylistItems, filterState);
        var name = typeof participantName === 'string' ? participantName.trim() : '';
        if (name === '') return indices;
        return indices.filter(function (ix) {
            var item = fullPlaylistItems[ix];
            return item && (item.participant || '') === name;
        });
    }

    /**
     * Apply a transport step (−1 / +1) within restored shuffle or linear state.
     * @param {{
     *   shuffleMode: boolean,
     *   shuffledSequence: number[],
     *   shuffleStep: number,
     *   filteredCursor: number,
     *   filteredCount: number,
     *   delta: number
     * }} opts
     * @returns {{ shuffleStep: number, filteredCursor: number } | null}
     */
    function applyTransportStep(opts) {
        var fc = opts.filteredCount;
        if (fc <= 0) return null;
        var delta = opts.delta;

        if (opts.shuffleMode) {
            var nextStep = opts.shuffleStep + delta;
            if (nextStep < 0 || nextStep >= fc) return null;
            var shuffledCursor = filteredCursorFromShuffleStep(
                opts.shuffledSequence,
                nextStep,
                fc
            );
            if (shuffledCursor === null) return null;
            return { shuffleStep: nextStep, filteredCursor: shuffledCursor };
        }

        var nextCursor = opts.filteredCursor + delta;
        if (nextCursor < 0 || nextCursor >= fc) return null;
        return { shuffleStep: opts.shuffleStep, filteredCursor: nextCursor };
    }

    /**
     * Plan player restore after navigating from About/Participants (issue #02).
     * @param {{
     *   session: object|null,
     *   navIntent: string|null|undefined,
     *   fullPlaylistItems: Array<{ signLanguage?: string, edition?: string, typology?: string, participant?: string }>,
     *   randomFn?: () => number
     * }} opts
     * @returns {{
     *   kind: 'fresh'|'restore'|'reset',
     *   plan?: object,
     *   filterState?: VpcFilterState,
     *   participantName?: string,
     *   isParticipantMode?: boolean,
     *   filteredMasterIndices?: number[],
     *   filteredCursor?: number,
     *   shuffleStep?: number,
     *   shuffledSequence?: number[],
     *   shuffleMode?: boolean,
     *   loadMasterIndex?: number,
     *   shouldAutoplay?: boolean,
     *   playbackTimeSec?: number
     * } | null}
     */
    function planSecondaryNavRestore(opts) {
        var explicitParticipantName = typeof opts.explicitParticipantName === 'string'
            ? opts.explicitParticipantName.trim()
            : '';
        if (explicitParticipantName !== '') {
            return null;
        }

        var session = opts.session;
        var navIntent = opts.navIntent ? String(opts.navIntent) : '';
        var items = opts.fullPlaylistItems;
        var randomFn = opts.randomFn || Math.random;

        if (navIntent === 'reset') {
            var resetPlan = planResetToNeutralAll({
                fullPlaylistItems: items,
                randomFn: randomFn,
            });
            if (!resetPlan) return { kind: 'fresh' };
            return { kind: 'reset', plan: resetPlan };
        }

        // DH10 / DH11: secondary force-ON — pure tagged Playlist, clear R2 + participant.
        if (navIntent === 'deaf-hearing') {
            var dhState = {
                sign_language: null,
                edition: null,
                typology: null,
                tag: DEAF_HEARING_TAG,
            };
            var dhRebuild = planFilterPlaylistRebuild({
                fullPlaylistItems: items,
                filterState: dhState,
                currentMasterIndex: session && typeof session.masterIndex === 'number'
                    ? session.masterIndex
                    : 0,
                shuffleMode: true,
                randomFn: randomFn,
            });
            if (!dhRebuild) {
                return { kind: 'fresh' };
            }
            return {
                kind: 'deaf-hearing',
                filterState: dhState,
                participantName: '',
                isParticipantMode: false,
                filteredMasterIndices: dhRebuild.filteredMasterIndices,
                filteredCursor: dhRebuild.filteredCursor,
                shuffleStep: dhRebuild.shuffleStep,
                shuffledSequence: dhRebuild.shuffledSequence,
                shuffleMode: dhRebuild.shuffleMode,
                loadMasterIndex: dhRebuild.loadMasterIndex,
                shouldAutoplay: true,
                playbackTimeSec: 0,
            };
        }

        if (!session) {
            if (navIntent === 'play' || navIntent === 'prev' || navIntent === 'next') {
                return { kind: 'fresh' };
            }
            return null;
        }

        // DH14: stored tag applies only when booting via a nav-intent handoff.
        var allowTag = navIntent === 'play' || navIntent === 'prev' || navIntent === 'next';
        var filterState = filterStateFromSession(session.filterState, allowTag);
        var participantName = typeof session.participantName === 'string'
            ? session.participantName.trim()
            : '';
        var isParticipantMode = participantName !== '';
        var filteredMasterIndices = filteredIndicesFromSessionContext(
            items,
            filterState,
            participantName
        );
        var fc = filteredMasterIndices.length;
        if (fc === 0) return { kind: 'fresh' };

        var shuffleMode = !!session.shuffleMode;
        var shuffledSequence = Array.isArray(session.shuffledSequence)
            ? session.shuffledSequence.slice()
            : [];
        var shuffleStep = typeof session.shuffleStep === 'number' ? session.shuffleStep : 0;
        var filteredCursor = typeof session.filteredCursor === 'number'
            ? session.filteredCursor
            : 0;
        var playbackTimeSec = typeof session.playbackTimeSec === 'number'
            ? session.playbackTimeSec
            : 0;
        var shouldAutoplay = false;

        if (navIntent === 'prev') {
            var prevStep = applyTransportStep({
                shuffleMode: shuffleMode,
                shuffledSequence: shuffledSequence,
                shuffleStep: shuffleStep,
                filteredCursor: filteredCursor,
                filteredCount: fc,
                delta: -1,
            });
            if (prevStep) {
                shuffleStep = prevStep.shuffleStep;
                filteredCursor = prevStep.filteredCursor;
            }
            playbackTimeSec = 0;
        } else if (navIntent === 'next') {
            var nextStep = applyTransportStep({
                shuffleMode: shuffleMode,
                shuffledSequence: shuffledSequence,
                shuffleStep: shuffleStep,
                filteredCursor: filteredCursor,
                filteredCount: fc,
                delta: 1,
            });
            if (nextStep) {
                shuffleStep = nextStep.shuffleStep;
                filteredCursor = nextStep.filteredCursor;
            }
            playbackTimeSec = 0;
        } else if (navIntent === 'play') {
            shouldAutoplay = playbackTimeSec > 0;
        }

        filteredCursor = Math.min(Math.max(filteredCursor, 0), fc - 1);
        var loadMasterIndex = filteredMasterIndices[filteredCursor];
        if (loadMasterIndex === undefined) {
            loadMasterIndex = typeof session.masterIndex === 'number' ? session.masterIndex : 0;
        }

        return {
            kind: 'restore',
            filterState: filterState,
            participantName: participantName,
            isParticipantMode: isParticipantMode,
            filteredMasterIndices: filteredMasterIndices,
            filteredCursor: filteredCursor,
            shuffleStep: shuffleStep,
            shuffledSequence: shuffledSequence,
            shuffleMode: shuffleMode,
            loadMasterIndex: loadMasterIndex,
            shouldAutoplay: shouldAutoplay,
            playbackTimeSec: playbackTimeSec,
        };
    }

    /**
     * Distinct participant names in a filtered playlist subset.
     * @param {Array<{ participant?: string }>} fullPlaylistItems
     * @param {number[]} masterIndices
     * @returns {string[]}
     */
    function distinctParticipantsInSubset(fullPlaylistItems, masterIndices) {
        var seen = {};
        var out = [];
        var i;
        for (i = 0; i < masterIndices.length; i++) {
            var ix = masterIndices[i];
            var item = fullPlaylistItems[ix];
            var name = item && typeof item.participant === 'string' ? item.participant.trim() : '';
            if (name !== '' && !seen[name]) {
                seen[name] = true;
                out.push(name);
            }
        }
        return out;
    }

    /**
     * Participants nav button: green + name only in participant-playlist mode; otherwise gray
     * with name shown on player page while video is playing (issue #04).
     * @param {{
     *   isParticipantMode: boolean,
     *   participantName: string,
     *   onPlayerPage: boolean,
     *   isPlaying: boolean,
     *   currentVideoParticipant: string
     * }} opts
     * @returns {{ label: string, isActive: boolean }}
     */
    function resolveParticipantsNavState(opts) {
        var participantName = typeof opts.participantName === 'string'
            ? opts.participantName.trim()
            : '';
        if (opts.isParticipantMode && participantName) {
            return { label: participantName, isActive: true };
        }
        var currentVideoParticipant = typeof opts.currentVideoParticipant === 'string'
            ? opts.currentVideoParticipant.trim()
            : '';
        if (opts.onPlayerPage && opts.isPlaying && currentVideoParticipant) {
            return { label: currentVideoParticipant, isActive: false };
        }
        return { label: '', isActive: false };
    }

    /**
     * End of playlist: pause on first video of current playback sequence (issue #05).
     * @param {{
     *   shuffleMode: boolean,
     *   shuffledSequence: number[],
     *   filteredCount: number,
     *   filteredMasterIndices: number[]
     * }} opts
     * @returns {{
     *   filteredCursor: number,
     *   shuffleStep: number,
     *   loadMasterIndex: number,
     *   shouldAutoplay: boolean
     * } | null}
     */
    function planEndOfPlaylist(opts) {
        var fc = opts.filteredCount;
        if (fc <= 0) return null;
        var filteredMasterIndices = Array.isArray(opts.filteredMasterIndices)
            ? opts.filteredMasterIndices
            : [];
        var filteredCursor = 0;
        var shuffleStep = 0;

        if (opts.shuffleMode) {
            var head = filteredCursorFromShuffleStep(opts.shuffledSequence, 0, fc);
            if (head === null) {
                filteredCursor = 0;
            } else {
                filteredCursor = head;
            }
            shuffleStep = 0;
        }

        var loadMasterIndex = filteredMasterIndices[filteredCursor];
        if (loadMasterIndex === undefined) return null;

        return {
            filteredCursor: filteredCursor,
            shuffleStep: shuffleStep,
            loadMasterIndex: loadMasterIndex,
            shouldAutoplay: false,
        };
    }

    /** Editorial target for one-line cues; preview still renders longer tracks smaller. */
    var CAPTION_TARGET_MAX_CHARS = 60;
    var CAPTION_MAX_CHARS_TOLERANCE = 5;
    var CAPTION_MIN_FONT_SIZE_PX = 6;

    /**
     * Preferred display length before shrink-to-fit (target + tolerance).
     * @returns {number}
     */
    function captionDisplayMaxLength() {
        return CAPTION_TARGET_MAX_CHARS + CAPTION_MAX_CHARS_TOLERANCE;
    }

    /**
     * Collapse cue text to a single line (no truncation).
     * @param {unknown} text
     * @returns {string}
     */
    function normalizeCaptionText(text) {
        if (text == null || text === '') return '';
        return String(text).replace(/[\r\n]+/g, ' ').replace(/\s+/g, ' ').trim();
    }

    /**
     * Scale base font down so measured text width fits the caption box.
     * @param {number} textWidthPx
     * @param {number} baseFontSizePx
     * @param {number} boxWidthPx
     * @returns {number}
     */
    function captionFitFontSizeFromWidths(textWidthPx, baseFontSizePx, boxWidthPx) {
        if (!textWidthPx || textWidthPx <= 0 || !boxWidthPx || boxWidthPx <= 0) {
            return baseFontSizePx;
        }
        if (textWidthPx <= boxWidthPx) {
            return baseFontSizePx;
        }
        var fitted = baseFontSizePx * (boxWidthPx / textWidthPx);
        return Math.round(fitted * 10) / 10;
    }

    return {
        CAPTION_TARGET_MAX_CHARS: CAPTION_TARGET_MAX_CHARS,
        CAPTION_MAX_CHARS_TOLERANCE: CAPTION_MAX_CHARS_TOLERANCE,
        CAPTION_MIN_FONT_SIZE_PX: CAPTION_MIN_FONT_SIZE_PX,
        captionDisplayMaxLength: captionDisplayMaxLength,
        normalizeCaptionText: normalizeCaptionText,
        captionFitFontSizeFromWidths: captionFitFontSizeFromWidths,
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
        DEAF_HEARING_TAG: DEAF_HEARING_TAG,
        isTagPinned: isTagPinned,
        itemHasTag: itemHasTag,
        recomputeFilteredMasterIndices: recomputeFilteredMasterIndices,
        videoMatchesFilterState: videoMatchesFilterState,
        labelForFacetValue: labelForFacetValue,
        compactFacetLabel: compactFacetLabel,
        resolveFilterPickerReadout: resolveFilterPickerReadout,
        distinctFacetValuesInSubset: distinctFacetValuesInSubset,
        buildCascadingFilterOptions: buildCascadingFilterOptions,
        buildShuffledSequenceWithHead: buildShuffledSequenceWithHead,
        planFilterPlaylistRebuild: planFilterPlaylistRebuild,
        shouldClearCollectionOnFilterFix: shouldClearCollectionOnFilterFix,
        emptyFilterState: emptyFilterState,
        resolveTagToggleOnFilterState: resolveTagToggleOnFilterState,
        filterStateAfterParticipantPick: filterStateAfterParticipantPick,
        planResetToNeutralAll: planResetToNeutralAll,
        PLAYBACK_SESSION_KEY: PLAYBACK_SESSION_KEY,
        NAV_INTENT_KEY: NAV_INTENT_KEY,
        buildPlaybackSessionSnapshot: buildPlaybackSessionSnapshot,
        parsePlaybackSession: parsePlaybackSession,
        filteredIndicesFromSessionContext: filteredIndicesFromSessionContext,
        applyTransportStep: applyTransportStep,
        planSecondaryNavRestore: planSecondaryNavRestore,
        distinctParticipantsInSubset: distinctParticipantsInSubset,
        resolveParticipantsNavState: resolveParticipantsNavState,
        planEndOfPlaylist: planEndOfPlaylist,
    };
}));
