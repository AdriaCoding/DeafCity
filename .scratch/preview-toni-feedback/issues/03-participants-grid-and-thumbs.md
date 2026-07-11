Status: done

# Participants grid: layout, thumbs, random order, alternation

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

Refine the Participants page content area (chrome handled in issue 01):

- Remove the **Participants** page heading.
- Show participant thumbnails **uncropped** (full frame visible; adjust CSS away from horizontal zoom/crop that cuts heads).
- **Shuffle participant card order** on each page load (not alphabetical).
- Each participant appears **once** in the grid. When a participant has multiple videos, **rotate which video's thumb** is shown on each visit (sessionStorage counter or similar per participant name).

## Acceptance criteria

- [x] No `<h1>` / heading above the participants grid
- [x] Thumbnails display without cropping heads on representative catalog entries
- [x] Grid order differs between two consecutive reloads (statistically — not hardcoded shuffle seed)
- [x] Multi-video participant alternates thumb across visits; single-video participant is stable
- [x] `participants_page_test.php` updated

## Blocked by

None — can start immediately (grid content is independent of chrome issue, but ship after 01 if touching the same page in one PR)
