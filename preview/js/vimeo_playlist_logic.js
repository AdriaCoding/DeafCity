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
     * Fisher–Yates shuffle of an arbitrary array (copy, does not mutate input).
     * @param {Array<*>} list
     * @param {() => number} randomFn
     * @returns {Array<*>}
     */
    function shuffleCopy(list, randomFn) {
        var arr = list.slice();
        for (var i = arr.length - 1; i > 0; i--) {
            var j = Math.floor(randomFn() * (i + 1));
            var t = arr[i];
            arr[i] = arr[j];
            arr[j] = t;
        }
        return arr;
    }

    /**
     * Order one city's positions with a round-robin-by-participant pass (issue #08):
     * repeatedly pick a random participant different from the one picked immediately
     * before, take a random unplayed video of theirs, until the bucket is exhausted.
     * Falls back to repeating the same participant only when no other remains.
     * @param {Array<{ pos: number, participant: string }>} entries  one city's candidates
     * @param {() => number} randomFn
     * @returns {number[]} `pos` values in play order
     */
    function buildWithinCityParticipantOrder(entries, randomFn) {
        var byParticipant = {};
        var participants = [];
        var i;
        for (i = 0; i < entries.length; i++) {
            var key = entries[i].participant;
            if (!byParticipant[key]) {
                byParticipant[key] = [];
                participants.push(key);
            }
            byParticipant[key].push(entries[i].pos);
        }

        var order = [];
        var lastParticipant = null;
        while (participants.length > 0) {
            var candidates = participants.filter(function (p) { return p !== lastParticipant; });
            if (candidates.length === 0) candidates = participants;

            // Among eligible participants, prefer whoever has the most videos left.
            // This is what keeps alternation possible for as long as the data allows
            // it instead of exhausting minority participants first and painting a
            // forced repeat run into the final stretch; it degrades to the "plain
            // shuffle fallback" the design accepts only when one participant truly
            // dominates the bucket (more than half its videos).
            var maxRemaining = 0;
            var ci;
            for (ci = 0; ci < candidates.length; ci++) {
                maxRemaining = Math.max(maxRemaining, byParticipant[candidates[ci]].length);
            }
            var topCandidates = candidates.filter(function (p) {
                return byParticipant[p].length === maxRemaining;
            });

            var chosen = topCandidates[Math.floor(randomFn() * topCandidates.length)];
            var bucket = byParticipant[chosen];
            var pickIx = Math.floor(randomFn() * bucket.length);
            order.push(bucket[pickIx]);
            bucket.splice(pickIx, 1);
            if (bucket.length === 0) {
                participants = participants.filter(function (p) { return p !== chosen; });
            }
            lastParticipant = chosen;
        }
        return order;
    }

    /**
     * Two-phase anti-clustering shuffle (issue #08): group candidates by city, order
     * each city's videos to avoid same-participant adjacency, then round-robin
     * interleave the city buckets (reshuffling bucket order each round) so runs from
     * a single city break up whenever more than one bucket still has videos.
     * A Participant belongs to exactly one city, so this cannot reintroduce
     * same-participant adjacency across a city boundary.
     * @param {Array<{ pos: number, city: string, participant: string }>} entries
     * @param {() => number} randomFn
     * @returns {number[]} `pos` values in play order (permutation of the input positions)
     */
    function buildCityInterleavedOrder(entries, randomFn) {
        var byCity = {};
        var cityOrder = [];
        var i;
        for (i = 0; i < entries.length; i++) {
            var city = entries[i].city;
            if (!byCity[city]) {
                byCity[city] = [];
                cityOrder.push(city);
            }
            byCity[city].push(entries[i]);
        }

        var queues = {};
        for (i = 0; i < cityOrder.length; i++) {
            var city2 = cityOrder[i];
            queues[city2] = buildWithinCityParticipantOrder(byCity[city2], randomFn);
        }

        var result = [];
        var activeCities = cityOrder.filter(function (c) { return queues[c].length > 0; });
        while (activeCities.length > 0) {
            var round = shuffleCopy(activeCities, randomFn);
            for (i = 0; i < round.length; i++) {
                var queue = queues[round[i]];
                if (queue.length > 0) result.push(queue.shift());
            }
            activeCities = cityOrder.filter(function (c) { return queues[c].length > 0; });
        }
        return result;
    }

    /**
     * Anti-clustering shuffle over a filtered-playlist pool (issue #08): a permutation
     * of positions 0..filteredMasterIndices.length-1, grouped by city/participant from
     * the underlying catalog items. Replaces plain Fisher–Yates for every shuffled
     * playlist (base one-per-city and every filtered facet combination).
     * @param {Array<{ edition?: string, participant?: string }>} fullPlaylistItems
     * @param {number[]} filteredMasterIndices
     * @param {() => number} [randomFn]
     * @returns {number[]}
     */
    function buildAntiClusterShuffledSequence(fullPlaylistItems, filteredMasterIndices, randomFn) {
        var random = randomFn || Math.random;
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var indices = Array.isArray(filteredMasterIndices) ? filteredMasterIndices : [];
        var entries = indices.map(function (masterIx, pos) {
            var item = items[masterIx];
            return {
                pos: pos,
                city: itemFacetValue(item, 'edition'),
                participant: item && typeof item.participant === 'string' ? item.participant.trim() : '',
            };
        });
        return buildCityInterleavedOrder(entries, random);
    }

    /**
     * Anti-clustering shuffle (issue #08) with one position fixed at step 0 (the
     * currently-playing video, kept in place by D22 keep-if-matches); the remaining
     * positions are ordered by the same two-phase algorithm, excluding the fixed one.
     * @param {Array<{ edition?: string, participant?: string }>} fullPlaylistItems
     * @param {number[]} filteredMasterIndices
     * @param {number} fixedIndex
     * @param {() => number} [randomFn]
     * @returns {number[]}
     */
    function buildAntiClusterSequenceWithHead(fullPlaylistItems, filteredMasterIndices, fixedIndex, randomFn) {
        var random = randomFn || Math.random;
        var indices = Array.isArray(filteredMasterIndices) ? filteredMasterIndices : [];
        var count = indices.length;
        if (count <= 0) return [];
        if (fixedIndex < 0 || fixedIndex >= count) {
            return buildAntiClusterShuffledSequence(fullPlaylistItems, indices, random);
        }
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var entries = [];
        for (var pos = 0; pos < count; pos++) {
            if (pos === fixedIndex) continue;
            var item = items[indices[pos]];
            entries.push({
                pos: pos,
                city: itemFacetValue(item, 'edition'),
                participant: item && typeof item.participant === 'string' ? item.participant.trim() : '',
            });
        }
        var rest = buildCityInterleavedOrder(entries, random);
        return [fixedIndex].concat(rest);
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
     * Caption files to fetch for one catalog Video. Never the full catalog.
     * @param {Array<{ tracks?: Array<{ file?: string }> }>} fullPlaylistItems
     * @param {number} masterIndex
     * @returns {Array<{ file: string, masterIndex: number, cueTrackIndex: number }>}
     */
    function planCaptionFetchesForMasterIndex(fullPlaylistItems, masterIndex) {
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var item = items[masterIndex];
        if (!item) return [];
        var tracks = Array.isArray(item.tracks) ? item.tracks : [];
        var out = [];
        var i;
        for (i = 0; i < tracks.length; i++) {
            var t = tracks[i];
            if (t && t.file) {
                out.push({
                    file: t.file,
                    masterIndex: masterIndex,
                    cueTrackIndex: i,
                });
            }
        }
        return out;
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

    /**
     * Same URL with ?lang= set to langId, preserving every other parameter.
     *
     * Deliberately string-based rather than URLSearchParams: re-serializing would
     * recode existing values (a Participant name's %20 becoming +), changing URLs the
     * server already round-trips correctly.
     *
     * @param {string} currentUrl
     * @param {string} langId
     * @returns {string}
     */
    function urlWithWebsiteLanguage(currentUrl, langId) {
        var url = String(currentUrl || '');

        var hashAt = url.indexOf('#');
        var hash = hashAt >= 0 ? url.slice(hashAt) : '';
        if (hashAt >= 0) {
            url = url.slice(0, hashAt);
        }

        var queryAt = url.indexOf('?');
        var path = queryAt >= 0 ? url.slice(0, queryAt) : url;
        var query = queryAt >= 0 ? url.slice(queryAt + 1) : '';

        var parts = query === '' ? [] : query.split('&');
        var kept = [];
        var replaced = false;
        for (var i = 0; i < parts.length; i++) {
            if (parts[i] === '') continue;
            if (parts[i].indexOf('lang=') === 0) {
                kept.push('lang=' + encodeURIComponent(langId));
                replaced = true;
            } else {
                kept.push(parts[i]);
            }
        }
        if (!replaced) {
            kept.push('lang=' + encodeURIComponent(langId));
        }

        return path + (kept.length > 0 ? '?' + kept.join('&') : '') + hash;
    }

    /**
     * Plan an in-session Website language switch (no page reload).
     *
     * Pure: decides whether the switch happens at all. Playback is deliberately absent
     * from both the inputs and the result — the Video is never touched, so there is
     * nothing to save, restore or resume (this is what retires ADR-0013's ghost caption
     * on this path).
     *
     * @param {{ currentLang?: string, targetLang?: string, cueTracks?: Array<{ lang?: string }>, subtitleLanguages?: Array<{ id?: string }> }} input
     * @returns {{ changed: boolean, lang?: string, subtitleLangId?: string, captionTrackIndex?: number }}
     */
    function planWebsiteLanguageSwitch(input) {
        var opts = input || {};
        var currentLang = String(opts.currentLang || '');
        var targetLang = String(opts.targetLang || '');

        if (targetLang === '' || targetLang === currentLang) {
            return { changed: false };
        }

        var cueTracks = Array.isArray(opts.cueTracks) ? opts.cueTracks : [];
        var subtitleLanguages = Array.isArray(opts.subtitleLanguages) ? opts.subtitleLanguages : [];

        // Website language drives Subtitle language (issue #19). Reuse the cold-load
        // picker rather than reimplementing it, so a switched page and a fresh load at
        // ?lang=<id> can never disagree about which track is showing.
        return {
            changed: true,
            lang: targetLang,
            subtitleLangId: targetLang,
            captionTrackIndex: resolveActiveCaptionTrackIndex(cueTracks, targetLang, subtitleLanguages),
            url: urlWithWebsiteLanguage(opts.currentUrl, targetLang),
        };
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
     * Returns both studio `label` (desktop face) and compact `short_label` (maketa ≤1024).
     * Dropup options keep full studio labels.
     * @param {{ signLanguage?: string, edition?: string, typology?: string }} item
     * @param {string} facet
     * @param {VpcFilterState} filterState
     * @param {Array<{ value?: string, label?: string, short_label?: string }>} options
     * @param {string} genericLabel
     * @param {boolean} [derivedActive] true when other filters strictly narrow this facet
     * @returns {{ label: string, short_label: string, fixed: boolean, active: boolean }}
     */
    function resolveFilterPickerReadout(item, facet, filterState, options, genericLabel, derivedActive) {
        var fixedValue = filterState[facet];
        if (fixedValue !== null && fixedValue !== undefined && fixedValue !== '') {
            return {
                label: labelForFacetValue(facet, fixedValue, options),
                short_label: compactFacetLabel(facet, fixedValue, options),
                fixed: true,
                active: true,
            };
        }
        var liveValue = itemFacetValue(item, facet);
        if (liveValue !== '') {
            return {
                label: labelForFacetValue(facet, liveValue, options),
                short_label: compactFacetLabel(facet, liveValue, options),
                fixed: false,
                active: !!derivedActive,
            };
        }
        return {
            label: genericLabel,
            short_label: genericLabel,
            fixed: false,
            active: false,
        };
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
     * Whether other active filters leave exactly one possible value for a facet.
     * @param {Array<{ signLanguage?: string, edition?: string, typology?: string, tags?: string[] }>} fullPlaylistItems
     * @param {VpcFilterState} filterState
     * @param {string} facet
     * @returns {boolean}
     */
    function isFacetNarrowedByOtherFilters(fullPlaylistItems, filterState, facet) {
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var state = filterState || emptyFilterState();
        var relaxed = {
            sign_language: state.sign_language !== undefined ? state.sign_language : null,
            edition: state.edition !== undefined ? state.edition : null,
            typology: state.typology !== undefined ? state.typology : null,
            tag: state.tag !== undefined ? state.tag : null,
        };
        relaxed[facet] = null;

        var allIndices = items.map(function (_, ix) { return ix; });
        var fullValues = distinctFacetValuesInSubset(items, allIndices, facet);
        if (fullValues.length === 0) return false;

        var narrowedIndices = recomputeFilteredMasterIndices(items, relaxed);
        var narrowedValues = distinctFacetValuesInSubset(items, narrowedIndices, facet);
        return narrowedValues.length === 1;
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
     * Base playlist pool (issue #01): one master index per distinct city (edition),
     * each a random Participant's random Video. A Participant belongs to exactly one
     * city, so grouping by participant within the city gives each Participant equal
     * odds regardless of how many Videos they have.
     * @param {Array<{ edition?: string, participant?: string }>} fullPlaylistItems
     * @param {() => number} [randomFn]
     * @returns {number[]} master indices, one per distinct city, in first-seen order
     */
    function buildOneVideoPerCityPool(fullPlaylistItems, randomFn) {
        var random = randomFn || Math.random;
        var items = Array.isArray(fullPlaylistItems) ? fullPlaylistItems : [];
        var cityOrder = [];
        var byCity = {};
        var i;
        for (i = 0; i < items.length; i++) {
            var edition = itemFacetValue(items[i], 'edition');
            if (edition === '') continue;
            if (!byCity[edition]) {
                byCity[edition] = [];
                cityOrder.push(edition);
            }
            byCity[edition].push(i);
        }

        var pool = [];
        for (var c = 0; c < cityOrder.length; c++) {
            var cityIndices = byCity[cityOrder[c]];
            var byParticipant = {};
            var participantOrder = [];
            for (i = 0; i < cityIndices.length; i++) {
                var ix = cityIndices[i];
                var item = items[ix];
                var name = item && typeof item.participant === 'string' ? item.participant.trim() : '';
                var key = name !== '' ? 'n:' + name : 'ix:' + ix;
                if (!byParticipant[key]) {
                    byParticipant[key] = [];
                    participantOrder.push(key);
                }
                byParticipant[key].push(ix);
            }
            var chosenParticipant = participantOrder[Math.floor(random() * participantOrder.length)];
            var candidates = byParticipant[chosenParticipant];
            pool.push(candidates[Math.floor(random() * candidates.length)]);
        }
        return pool;
    }

    /**
     * Base playlist plan (issue #01): buildOneVideoPerCityPool + a fresh shuffle
     * over the reduced pool (same Fisher–Yates approach used elsewhere, just applied
     * to this smaller set instead of the full catalog).
     * @param {{
     *   fullPlaylistItems: Array<{ edition?: string, participant?: string }>,
     *   randomFn?: () => number
     * }} opts
     * @returns {{
     *   filteredMasterIndices: number[],
     *   filteredCursor: number,
     *   shuffleStep: number,
     *   shuffledSequence: number[],
     *   shuffleMode: boolean,
     *   loadMasterIndex: number
     * } | null}
     */
    function planBaseCityPlaylist(opts) {
        var randomFn = (opts && opts.randomFn) || Math.random;
        var items = opts && opts.fullPlaylistItems;
        var pool = buildOneVideoPerCityPool(items, randomFn);
        var fc = pool.length;
        if (fc === 0) return null;

        var shuffledSequence = buildAntiClusterShuffledSequence(items, pool, randomFn);
        var filteredCursor = shuffledSequence[0];
        return {
            filteredMasterIndices: pool,
            filteredCursor: filteredCursor,
            shuffleStep: 0,
            shuffledSequence: shuffledSequence,
            shuffleMode: true,
            loadMasterIndex: pool[filteredCursor],
        };
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
                    shuffledSequence: buildAntiClusterSequenceWithHead(
                        items,
                        filteredMasterIndices,
                        posInFiltered,
                        randomFn
                    ),
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
            var seq = buildAntiClusterShuffledSequence(items, filteredMasterIndices, randomFn);
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
     * Whether filterState has no facet pinned (issue #01: routes filter clears back
     * to the base-city playlist, same as Reset).
     * @param {VpcFilterState} filterState
     * @returns {boolean}
     */
    function isFilterStateNeutral(filterState) {
        var fs = filterState || {};
        if (fs.sign_language !== null && fs.sign_language !== undefined) return false;
        if (fs.edition !== null && fs.edition !== undefined) return false;
        if (fs.typology !== null && fs.typology !== undefined) return false;
        return !isTagPinned(fs);
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
     * Reset control (D1′): clear all filters and collections, rebuild the base
     * playlist (issue #01: one random Participant's random Video per city, shuffled),
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

        var basePlan = planBaseCityPlaylist({ fullPlaylistItems: items, randomFn: randomFn });
        if (!basePlan) return null;

        return {
            filterState: filterState,
            isParticipantMode: false,
            participantName: '',
            filteredMasterIndices: basePlan.filteredMasterIndices,
            filteredCursor: basePlan.filteredCursor,
            shuffleStep: basePlan.shuffleStep,
            shuffledSequence: basePlan.shuffledSequence,
            shuffleMode: basePlan.shuffleMode,
            loadMasterIndex: basePlan.loadMasterIndex,
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
     *   filteredMasterIndices?: number[]
     *   playbackTimeSec?: number
     * }} opts
     * @returns {object}
     */
    function buildPlaybackSessionSnapshot(opts) {
        return {
            v: 3,
            masterIndex: typeof opts.masterIndex === 'number' ? opts.masterIndex : 0,
            filterState: {
                sign_language: opts.filterState.sign_language,
                edition: opts.filterState.edition,
                typology: opts.filterState.typology,
                tag: isTagPinned(opts.filterState) ? opts.filterState.tag : null,
            },
            participantName: typeof opts.participantName === 'string' ? opts.participantName : '',
            participantSequence: typeof opts.participantSequence === 'string'
                ? opts.participantSequence
                : '',
            shuffleMode: !!opts.shuffleMode,
            shuffledSequence: Array.isArray(opts.shuffledSequence)
                ? opts.shuffledSequence.slice()
                : [],
            shuffleStep: typeof opts.shuffleStep === 'number' ? opts.shuffleStep : 0,
            filteredCursor: typeof opts.filteredCursor === 'number' ? opts.filteredCursor : 0,
            // Issue #01: the base-city pool is a random pick, not a pure function of
            // filterState — persisted so a same-tab refresh restores the exact pool
            // instead of recomputing a different one.
            filteredMasterIndices: Array.isArray(opts.filteredMasterIndices)
                ? opts.filteredMasterIndices.slice()
                : [],
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
     * Accepts v:1 (migrates tag:null), v:2 (DH17), and v:3 (issue #01, adds
     * filteredMasterIndices — absent/older sessions migrate to []).
     * @param {string} raw
     * @returns {object|null}
     */
    function parsePlaybackSession(raw) {
        if (!raw || typeof raw !== 'string') return null;
        try {
            var data = JSON.parse(raw);
            if (!data || typeof data !== 'object') return null;
            if (data.v !== 1 && data.v !== 2 && data.v !== 3) return null;
            if (typeof data.masterIndex !== 'number') return null;
            if (!data.filterState || typeof data.filterState !== 'object') return null;
            if (data.v === 1 || data.filterState.tag === undefined) {
                data.filterState.tag = null;
            }
            if (typeof data.participantSequence !== 'string') {
                data.participantSequence = '';
            }
            if (!Array.isArray(data.filteredMasterIndices)) {
                data.filteredMasterIndices = [];
            }
            return data;
        } catch (e) {
            return null;
        }
    }

    /**
     * Rebuild filtered master indices from session filter + optional participant mode.
     * Pure Participant collections use natural sequence order (no catalog order).
     * @param {Array<{ participant?: string, participant_sequence?: string|number }>} fullPlaylistItems
     * @param {VpcFilterState} filterState
     * @param {string} participantName
     * @returns {number[]}
     */
    function filteredIndicesFromSessionContext(fullPlaylistItems, filterState, participantName) {
        var name = typeof participantName === 'string' ? participantName.trim() : '';
        if (name !== '') {
            var natural = participantMasterIndicesInNaturalOrder(fullPlaylistItems, name);
            var hasFilter = !!(filterState && (
                filterState.sign_language !== null
                || filterState.edition !== null
                || filterState.typology !== null
                || isTagPinned(filterState)
            ));
            if (!hasFilter) return natural;
            return natural.filter(function (ix) {
                return videoMatchesFilterState(fullPlaylistItems[ix], filterState);
            });
        }
        return recomputeFilteredMasterIndices(fullPlaylistItems, filterState);
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

        // Secondary R2 filter handoff: `filter:<facet>:<value>` — replace with a single
        // fresh pin, clear participant + other pins, rebuild and autoplay the filtered set.
        var filterMatch = /^filter:(sign_language|edition|typology):(.*)$/.exec(navIntent);
        if (filterMatch) {
            var fFacet = filterMatch[1];
            var fValue = '';
            try {
                fValue = decodeURIComponent(filterMatch[2]);
            } catch (e) {
                fValue = filterMatch[2];
            }
            if (fValue === '') {
                return { kind: 'fresh' };
            }
            var fState = {
                sign_language: null,
                edition: null,
                typology: null,
                tag: null,
            };
            fState[fFacet] = fValue;
            var fRebuild = planFilterPlaylistRebuild({
                fullPlaylistItems: items,
                filterState: fState,
                currentMasterIndex: session && typeof session.masterIndex === 'number'
                    ? session.masterIndex
                    : 0,
                shuffleMode: true,
                randomFn: randomFn,
            });
            if (!fRebuild) {
                return { kind: 'fresh' };
            }
            return {
                kind: 'filter',
                filterState: fState,
                participantName: '',
                isParticipantMode: false,
                filteredMasterIndices: fRebuild.filteredMasterIndices,
                filteredCursor: fRebuild.filteredCursor,
                shuffleStep: fRebuild.shuffleStep,
                shuffledSequence: fRebuild.shuffledSequence,
                shuffleMode: fRebuild.shuffleMode,
                loadMasterIndex: fRebuild.loadMasterIndex,
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

        // Issue #01: the base-city pool is a random pick, not a pure function of
        // filterState — a neutral restore must reuse the exact pool that was saved
        // (session v3+) rather than recompute, which would widen back to the full
        // catalog and desync filteredCursor/shuffledSequence from the saved video.
        if (
            participantName === ''
            && isFilterStateNeutral(filterState)
            && Array.isArray(session.filteredMasterIndices)
            && session.filteredMasterIndices.length > 0
        ) {
            var storedPool = session.filteredMasterIndices.filter(function (ix) {
                return typeof ix === 'number' && ix >= 0 && ix < items.length;
            });
            if (storedPool.length > 0) {
                filteredMasterIndices = storedPool;
            }
        }

        var fc = filteredMasterIndices.length;

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

        // Pure Participant collections always rebuild natural linear order (no shuffle).
        if (isParticipantMode) {
            var pPlan = planParticipantCollectionPlaylist({
                fullPlaylistItems: items,
                participantName: participantName,
                currentMasterIndex: typeof session.masterIndex === 'number'
                    ? session.masterIndex
                    : -1,
            });
            filteredMasterIndices = pPlan.filteredMasterIndices;
            fc = filteredMasterIndices.length;
            shuffleMode = false;
            shuffledSequence = [];
            shuffleStep = 0;
            filteredCursor = pPlan.filteredCursor;
            if (fc === 0) {
                return {
                    kind: 'restore',
                    filterState: filterState,
                    participantName: participantName,
                    isParticipantMode: true,
                    filteredMasterIndices: [],
                    filteredCursor: 0,
                    shuffleStep: 0,
                    shuffledSequence: [],
                    shuffleMode: false,
                    loadMasterIndex: -1,
                    shouldAutoplay: false,
                    playbackTimeSec: 0,
                };
            }
        } else if (fc === 0) {
            return { kind: 'fresh' };
        }

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
     * Sequence index from a Vimeo/catalog title (`…_Name_N_HD|4K`).
     * @param {unknown} title
     * @returns {string} Decimal string without leading zeros, or '' when unparseable
     */
    function participantSequenceFromTitle(title) {
        var m = String(title || '').match(/_(\d+)_(?:HD|4K)\b/i);
        if (!m) {
            return '';
        }
        return String(parseInt(m[1], 10));
    }

    /**
     * Master-playlist indices for one Participant, in natural clip order.
     * Numeric participant_sequence ascending; missing sequences last; master
     * index as tie-breaker (stable catalog order among equals).
     * @param {Array<{ participant?: string, participant_sequence?: string|number }>} fullPlaylistItems
     * @param {string} participantName
     * @returns {number[]}
     */
    function participantMasterIndicesInNaturalOrder(fullPlaylistItems, participantName) {
        var name = typeof participantName === 'string' ? participantName.trim() : '';
        if (name === '' || !Array.isArray(fullPlaylistItems)) {
            return [];
        }
        var pairs = [];
        var i;
        for (i = 0; i < fullPlaylistItems.length; i++) {
            var item = fullPlaylistItems[i];
            var p = item && typeof item.participant === 'string' ? item.participant.trim() : '';
            if (p !== name) continue;
            var raw = item && item.participant_sequence !== undefined && item.participant_sequence !== null
                ? String(item.participant_sequence).trim()
                : '';
            var hasSeq = raw !== '' && !isNaN(Number(raw));
            pairs.push({
                ix: i,
                hasSeq: hasSeq,
                seq: hasSeq ? Number(raw) : 0,
            });
        }
        pairs.sort(function (a, b) {
            if (a.hasSeq !== b.hasSeq) {
                return a.hasSeq ? -1 : 1;
            }
            if (a.hasSeq && a.seq !== b.seq) {
                return a.seq - b.seq;
            }
            return a.ix - b.ix;
        });
        return pairs.map(function (row) { return row.ix; });
    }

    /**
     * Plan a pure Participant collection Playlist: natural sequence order, no shuffle.
     * Cold load (no usable currentMasterIndex): start at sort head.
     * Restore (current video still in set): keep it and remap cursor.
     * @param {{
     *   fullPlaylistItems: Array<{ participant?: string, participant_sequence?: string|number }>,
     *   participantName: string,
     *   currentMasterIndex?: number
     * }} opts
     * @returns {{
     *   filteredMasterIndices: number[],
     *   filteredCursor: number,
     *   shuffleStep: number,
     *   shuffledSequence: number[],
     *   shuffleMode: boolean,
     *   loadMasterIndex: number,
     *   keepCurrentVideo: boolean
     * }}
     * Always returns a plan (empty indices + loadMasterIndex -1 when no matches).
     */
    function planParticipantCollectionPlaylist(opts) {
        var items = opts && opts.fullPlaylistItems;
        var name = opts && typeof opts.participantName === 'string' ? opts.participantName.trim() : '';
        var filteredMasterIndices = participantMasterIndicesInNaturalOrder(items, name);
        if (filteredMasterIndices.length === 0) {
            return {
                filteredMasterIndices: [],
                filteredCursor: 0,
                shuffleStep: 0,
                shuffledSequence: [],
                shuffleMode: false,
                loadMasterIndex: -1,
                keepCurrentVideo: false,
            };
        }

        var currentIx = opts && typeof opts.currentMasterIndex === 'number'
            ? opts.currentMasterIndex
            : -1;
        var pos = filteredMasterIndices.indexOf(currentIx);
        if (pos >= 0) {
            return {
                filteredMasterIndices: filteredMasterIndices,
                filteredCursor: pos,
                shuffleStep: 0,
                shuffledSequence: [],
                shuffleMode: false,
                loadMasterIndex: currentIx,
                keepCurrentVideo: true,
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
     * Participants chrome label: bare name, or "Name N" when sequence is known.
     * @param {unknown} name
     * @param {unknown} sequence
     * @returns {string}
     */
    function formatParticipantNavLabel(name, sequence) {
        var n = typeof name === 'string' ? name.trim() : '';
        if (n === '') {
            return '';
        }
        var seq = sequence === null || sequence === undefined ? '' : String(sequence).trim();
        if (seq === '') {
            return n;
        }
        return n + ' ' + seq;
    }

    /**
     * Participants nav button: green + name only in participant-playlist mode; otherwise gray
     * with name shown on player page while video is playing (issue #04).
     * When a person name is shown, append title sequence when available.
     * @param {{
     *   isParticipantMode: boolean,
     *   participantName: string,
     *   participantSequence?: string,
     *   onPlayerPage: boolean,
     *   isPlaying: boolean,
     *   currentVideoParticipant: string,
     *   currentVideoParticipantSequence?: string
     * }} opts
     * @returns {{ label: string, isActive: boolean }}
     */
    function resolveParticipantsNavState(opts) {
        var participantName = typeof opts.participantName === 'string'
            ? opts.participantName.trim()
            : '';
        var participantSequence = typeof opts.participantSequence === 'string'
            ? opts.participantSequence.trim()
            : '';
        var currentVideoParticipant = typeof opts.currentVideoParticipant === 'string'
            ? opts.currentVideoParticipant.trim()
            : '';
        var currentSeq = typeof opts.currentVideoParticipantSequence === 'string'
            ? opts.currentVideoParticipantSequence.trim()
            : '';
        if (opts.isParticipantMode && participantName) {
            var modeName = currentVideoParticipant || participantName;
            var modeSeq = currentSeq !== '' ? currentSeq : participantSequence;
            return {
                label: formatParticipantNavLabel(modeName, modeSeq),
                isActive: true,
            };
        }
        if (opts.onPlayerPage && opts.isPlaying && currentVideoParticipant) {
            return {
                label: formatParticipantNavLabel(currentVideoParticipant, currentSeq),
                isActive: false,
            };
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
     *   shouldAutoplay: boolean,
     *   forceReload: boolean
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
            // A terminal reset must reload even for a one-video Playlist:
            // seeking an ended Vimeo player can leave its promise unresolved.
            forceReload: true,
        };
    }

    /** Editorial target for one-line cues; preview still renders longer tracks smaller. */
    var CAPTION_TARGET_MAX_CHARS = 60;
    var CAPTION_MAX_CHARS_TOLERANCE = 5;
    var CAPTION_MIN_FONT_SIZE_PX = 6;
    /** Match preview/components/vimeo_caption_player.css narrow video breakpoint. */
    var CAPTION_TWO_LINE_MAX_WIDTH_PX = 650;
    var CAPTION_LINE_HEIGHT_RATIO = 1.28;
    var CAPTION_PADDING_TOP_EM = 0.15;

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

    /**
     * Whether the caption box is narrow enough to wrap cues on two lines.
     * @param {number} boxWidthPx
     * @returns {boolean}
     */
    function captionUsesTwoLineWrap(boxWidthPx) {
        if (!boxWidthPx || boxWidthPx <= 0) return false;
        return boxWidthPx <= CAPTION_TWO_LINE_MAX_WIDTH_PX;
    }

    /**
     * Fixed caption block height so font sizing never feeds back into layout.
     * @param {number} baseFontSizePx
     * @param {1|2} maxLines
     * @returns {number}
     */
    function captionBlockHeightPx(baseFontSizePx, maxLines) {
        var size = typeof baseFontSizePx === 'number' ? baseFontSizePx : parseFloat(baseFontSizePx);
        if (!size || size <= 0 || !isFinite(size)) size = 18;
        var lines = maxLines === 2 ? 2 : 1;
        return Math.ceil(
            size * CAPTION_LINE_HEIGHT_RATIO * lines + size * CAPTION_PADDING_TOP_EM
        );
    }

    /**
     * Shrink-to-fit with optional two-line budget on narrow viewports.
     * @param {number} textWidthPx
     * @param {number} baseFontSizePx
     * @param {number} boxWidthPx
     * @param {boolean} [twoLineMode]
     * @returns {number}
     */
    function captionFitFontSizeForDisplay(textWidthPx, baseFontSizePx, boxWidthPx, twoLineMode) {
        var budget = twoLineMode ? boxWidthPx * 2 : boxWidthPx;
        return captionFitFontSizeFromWidths(textWidthPx, baseFontSizePx, budget);
    }

    /**
     * Choose the load-time cover over the Vimeo iframe.
     * Running-playlist autoplay advances use a solid white scrim (no next thumb).
     * Paused/cold loads may show the target Video thumbnail.
     *
     * @param {{ willAutoplay?: boolean, thumbnailUrl?: string }} opts
     * @returns {{ kind: 'thumbnail'|'solid-white'|'none', thumbnailUrl: string }}
     */
    function planLoadCover(opts) {
        var willAutoplay = !!(opts && opts.willAutoplay);
        var thumbnailUrl =
            opts && opts.thumbnailUrl != null ? String(opts.thumbnailUrl) : '';
        if (willAutoplay) {
            return { kind: 'solid-white', thumbnailUrl: '' };
        }
        if (thumbnailUrl !== '') {
            return { kind: 'thumbnail', thumbnailUrl: thumbnailUrl };
        }
        return { kind: 'none', thumbnailUrl: '' };
    }

    /**
     * Whether the load cover (thumb or solid white) may be removed.
     * Keep covering while Vimeo is still buffering so its gray loader never flashes.
     *
     * @param {{ playbackStarted?: boolean, seconds?: number, buffering?: boolean, minSeconds?: number }} opts
     * @returns {boolean}
     */
    function shouldRevealLoadCover(opts) {
        var playbackStarted = !!(opts && opts.playbackStarted);
        var buffering = !!(opts && opts.buffering);
        var seconds = opts && opts.seconds != null ? Number(opts.seconds) : 0;
        var minSeconds =
            opts && opts.minSeconds != null ? Number(opts.minSeconds) : 0.05;
        if (!playbackStarted || buffering) return false;
        if (!(seconds > minSeconds)) return false;
        return true;
    }

    var TRANSPORT_SHORTCUT_KEYS = {
        ' ': 'play-pause',
        MediaPlayPause: 'play-pause',
        ArrowLeft: 'prev',
        MediaTrackPrevious: 'prev',
        ArrowRight: 'next',
        MediaTrackNext: 'next',
        r: 'reset',
        d: 'deaf-hearing',
        a: 'about',
        p: 'participants',
    };

    /**
     * Whether a focused element should suppress transport shortcuts (typing target).
     * @param {{ tagName?: string, isContentEditable?: boolean } | null | undefined} el
     * @returns {boolean}
     */
    function isEditableFocusTarget(el) {
        if (!el) return false;
        if (el.isContentEditable) return true;
        var tag = typeof el.tagName === 'string' ? el.tagName.toUpperCase() : '';
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
    }

    /**
     * Map a keydown event to a transport action, or null when it should be ignored
     * (modifier held, or focus is on an editable element).
     * @param {{ key?: string, ctrlKey?: boolean, altKey?: boolean, metaKey?: boolean, shiftKey?: boolean, activeElement?: unknown }} opts
     * @returns {'play-pause'|'prev'|'next'|'reset'|'deaf-hearing'|'about'|'participants'|null}
     */
    function resolveTransportShortcutAction(opts) {
        var o = opts || {};
        if (o.ctrlKey || o.altKey || o.metaKey || o.shiftKey) return null;
        if (isEditableFocusTarget(o.activeElement)) return null;
        var key = typeof o.key === 'string' ? o.key : '';
        var lookupKey = key.length === 1 ? key.toLowerCase() : key;
        return TRANSPORT_SHORTCUT_KEYS[lookupKey] || null;
    }

    return {
        resolveTransportShortcutAction: resolveTransportShortcutAction,
        isEditableFocusTarget: isEditableFocusTarget,
        CAPTION_TARGET_MAX_CHARS: CAPTION_TARGET_MAX_CHARS,
        CAPTION_MAX_CHARS_TOLERANCE: CAPTION_MAX_CHARS_TOLERANCE,
        CAPTION_MIN_FONT_SIZE_PX: CAPTION_MIN_FONT_SIZE_PX,
        CAPTION_TWO_LINE_MAX_WIDTH_PX: CAPTION_TWO_LINE_MAX_WIDTH_PX,
        captionDisplayMaxLength: captionDisplayMaxLength,
        normalizeCaptionText: normalizeCaptionText,
        captionFitFontSizeFromWidths: captionFitFontSizeFromWidths,
        captionUsesTwoLineWrap: captionUsesTwoLineWrap,
        captionBlockHeightPx: captionBlockHeightPx,
        captionFitFontSizeForDisplay: captionFitFontSizeForDisplay,
        filteredCursorFromShuffleStep: filteredCursorFromShuffleStep,
        buildShuffledSequence: buildShuffledSequence,
        buildWithinCityParticipantOrder: buildWithinCityParticipantOrder,
        buildCityInterleavedOrder: buildCityInterleavedOrder,
        buildAntiClusterShuffledSequence: buildAntiClusterShuffledSequence,
        buildAntiClusterSequenceWithHead: buildAntiClusterSequenceWithHead,
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
        planCaptionFetchesForMasterIndex: planCaptionFetchesForMasterIndex,
        pickTrackIndexForSpokenLang: pickTrackIndexForSpokenLang,
        resolveActiveCaptionTrackIndex: resolveActiveCaptionTrackIndex,
        planWebsiteLanguageSwitch: planWebsiteLanguageSwitch,
        urlWithWebsiteLanguage: urlWithWebsiteLanguage,
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
        isFacetNarrowedByOtherFilters: isFacetNarrowedByOtherFilters,
        buildCascadingFilterOptions: buildCascadingFilterOptions,
        buildShuffledSequenceWithHead: buildShuffledSequenceWithHead,
        buildOneVideoPerCityPool: buildOneVideoPerCityPool,
        planBaseCityPlaylist: planBaseCityPlaylist,
        planFilterPlaylistRebuild: planFilterPlaylistRebuild,
        shouldClearCollectionOnFilterFix: shouldClearCollectionOnFilterFix,
        emptyFilterState: emptyFilterState,
        isFilterStateNeutral: isFilterStateNeutral,
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
        participantSequenceFromTitle: participantSequenceFromTitle,
        participantMasterIndicesInNaturalOrder: participantMasterIndicesInNaturalOrder,
        planParticipantCollectionPlaylist: planParticipantCollectionPlaylist,
        formatParticipantNavLabel: formatParticipantNavLabel,
        resolveParticipantsNavState: resolveParticipantsNavState,
        planEndOfPlaylist: planEndOfPlaylist,
        planLoadCover: planLoadCover,
        shouldRevealLoadCover: shouldRevealLoadCover,
    };
}));
