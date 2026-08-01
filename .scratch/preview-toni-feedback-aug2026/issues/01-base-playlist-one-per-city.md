Status: done

# Base playlist: one random participant per city

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Retire the full-catalog shuffle for the unfiltered/neutral ("ALL") state everywhere it currently appears: cold entry, the Reset button, and clearing all filters back to neutral. Replace it with a playlist built as:

1. For each city (Edition) in the Catalog, pick one random Participant, then one random Video of theirs.
2. Shuffle the resulting per-city entries into random order (same Fisher–Yates approach already used elsewhere in `vimeo_playlist_logic.js`, applied to this reduced pool instead of the full catalog).

This selection is **not** persisted for the browsing session. It regenerates only on a true cold start — reuse the existing distinction already in the codebase between "no prior session" (the `PLAYBACK_SESSION_KEY` sessionStorage key is absent — new tab, or after an explicit Reset clears it) and "prior session exists" (`vimeo_caption_player.js` around lines 443-481). A same-tab browser refresh mid-video must continue to restore exactly as it does today via the existing session-restore path — do not add any new logic that clears or bypasses that path. This is the same branch that already decides cold-vs-restore; only what the cold branch produces should change.

Thumbnail display/fallback logic is explicitly **out of scope** — that requirement was withdrawn.

## Acceptance criteria

- [x] Cold entry (empty sessionStorage / new tab) builds a playlist with exactly one video per Edition, from a randomly chosen Participant of that Edition
- [x] Pressing Reset produces a fresh such playlist (new random participant/video per city, new shuffle order)
- [x] Manually clearing all active filters back to neutral produces the same fresh playlist behavior as Reset
- [x] A same-tab browser refresh while a video is loaded/playing restores the exact same video and position as before (no regression to existing session-restore behavior)
- [x] Unit tests cover the pool-construction function (1 per city) and the cold-vs-restore branch selection, following the existing pure-function testing pattern in `vimeo_playlist_logic.test.js`

## Implementation notes

- `buildOneVideoPerCityPool` / `planBaseCityPlaylist` (`preview/js/vimeo_playlist_logic.js`) build the pool + shuffle; reused by `planResetToNeutralAll` and by `applyFilterChange` (via the new `isFilterStateNeutral` check) so cold entry, Reset, and clearing all filters share one implementation.
- Mirrored server-side as `vpc_base_city_playlist_pool` (`preview/lib/videos_catalog.php`) for the cold-entry SSR playlist; `catalog_playlist` stays the full catalog for client-side filtering.
- The base pool is a random pick, not a pure function of filter state, so a same-tab refresh can't simply recompute it. The playback session snapshot was bumped to v3 to carry `filteredMasterIndices`; `planSecondaryNavRestore` reuses the stored pool on a neutral-state restore instead of recomputing (old v1/v2 sessions still parse and fall back to prior behavior).
- Tests: `preview/tests/vimeo_playlist_logic.test.js` (pool construction, reset, neutral-filter routing, session v3, cold-vs-restore branch selection) and `preview/tests/base_city_playlist_test.php` (PHP pool function + integration check against the real catalog).

## Blocked by

None - can start immediately
