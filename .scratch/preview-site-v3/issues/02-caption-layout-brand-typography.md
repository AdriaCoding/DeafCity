Status: ready-for-agent

# Caption layout and brand typography

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

Apply the v3 design tokens to the Preview player caption box: **Roboto** typeface and brand green **`rgb(0, 120, 0)`**. Fix caption layout so the box sits **flush against the video** with zero gap (especially on mobile). Reserve space for **two lines** of subtitle text without shifting the video or transport controls when a second line appears. Match subtitle size to the on-video subtitle rendering where feasible.

## Acceptance criteria

- [ ] Caption text uses Roboto and `rgb(0, 120, 0)`
- [ ] No visible gap between caption box and video on narrow viewports
- [ ] A two-line cue does not cause player chrome to jump
- [ ] Layout remains minimalistic and readable

## Blocked by

None — can start immediately
