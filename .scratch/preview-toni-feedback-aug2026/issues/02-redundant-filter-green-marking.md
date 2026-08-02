Status: done

# Redundant-filter green marking

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Today a filter picker only gets the "active" green styling (`data-active="true"` on `.vpc-picker`, driving the `rgb(0,120,0)` accent in `vimeo_caption_player.css`) when the Producer/visitor has explicitly picked a value for that facet (`resolveFilterPickerReadout`'s `fixed: true` case, `vimeo_playlist_logic.js:452`). When a facet is only showing a live/derived readout (e.g. the city button showing "Barcelona" because the current video happens to be from Barcelona), it stays unstyled even when that value is effectively forced by another active filter.

Generalize the rule: mark a non-fixed facet's live readout as active/green whenever the other currently-active filters have narrowed its set of possible values to a strict subset of the full unconstrained set of values for that facet. Use the existing `distinctFacetValuesInSubset` helper (`vimeo_playlist_logic.js:496`) to compute the narrowed set for the current filter state, and compare its size against the full set of values for that facet across the whole catalog. This must cover both the single-value case (LSC selected → only Barcelona possible) and the multi-value case (LSE selected → only Bilbao/València possible) — do not special-case "exactly one value"; any strict narrowing qualifies.

No change is needed to click handling: clicking the clear/"all" option on such a field calls `applyFilterChange(facet, null)`, and since `filterState[facet]` was already `null`, this is already a true no-op (recomputes an identical filtered set, keeps the current video, and the badge stays green because the narrowed-set condition still holds). Verify this remains the case; do not add new special-case logic for it.

## Acceptance criteria

- [x] Selecting LSC alone shows the city picker as active/green with "Barcelona", without the user explicitly picking a city
- [x] Selecting LSE alone shows the city picker as active/green, with the readout following whichever city the currently displayed video belongs to (Bilbao or València) as playback advances
- [x] A facet whose possible values are *not* narrowed by other active filters (e.g. city picker with no sign-language filter active) does not get the active/green styling
- [x] Clicking the clear/"all" option on a redundant-but-not-fixed field is confirmed to be a no-op: dropdown closes, filter state and displayed video are unchanged, badge remains green
- [x] Unit tests cover the narrowed-set computation and its interaction with `resolveFilterPickerReadout`'s existing `fixed`/`liveValue` logic

## Blocked by

None - can start immediately
