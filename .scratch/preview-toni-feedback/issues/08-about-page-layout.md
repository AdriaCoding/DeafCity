Status: done

# About page: remove map, full-width layout, logo alignment

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

Restore About page content layout closer to the legacy site:

- Remove the **city map** section and its JS/CSS includes.
- Make content **full-width responsive** (remove or relax the narrow max-width constraint).
- Align **credits logos** as in the previous web version.

Player chrome unification is handled in issue 01; this issue covers only the scrollable About content above the bottom bar.

## Acceptance criteria

- [x] `#city-map-section`, map.php include, about-map.js, and map-specific CSS removed
- [x] About content uses full viewport width responsively (clock/gallery row, text blocks, credits)
- [x] Credits logos visually aligned per legacy reference (side-by-side, consistent spacing)
- [x] `about_page_test.php` updated; no broken script references
- [x] Page remains usable with unified player chrome from issue 01

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md) (recommended same PR/page touch; can start content work in parallel)
