Status: ready-for-agent

# Mobile top-only height crop

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Today's mobile width-crop (`vimeo_caption_player.css:91-95`) narrows `.video-shell`'s `aspect-ratio` from `1.77/1` to `1.2/1` at `≤650px`, while the iframe/poster stay scaled to `height:100%` and centered via `transform: translate(-50%,-50%)` (`:44-72`) — width overflow gets clipped symmetrically by the shell's `overflow:hidden`.

Add a top-only height crop at the same `≤650px` breakpoint: scale/position the video so it also overflows vertically, with the vertical anchor biased so any cropping only ever removes from the **top** edge, never the bottom. Implement the amount as a single global, easily-tunable CSS custom property (e.g. `--vpc-top-crop`), not a per-video or per-participant setting.

**This issue has a human-in-the-loop gate**: ship the mechanism with a conservative default (e.g. `0` or a small placeholder value), but the feature is not complete until Toni has visually reviewed the crop amount against Mahieddine's and Mustapha's videos (the tallest-framed participants, i.e. worst case for excess headroom) and confirmed it doesn't cut off hands/face. Do not treat this issue as done purely on the mechanism landing — flag the final value as pending Toni's review.

## Acceptance criteria

- [ ] A single global CSS custom property controls the top-crop amount at the `≤650px` breakpoint, independent of the existing width-crop aspect-ratio change
- [ ] The crop only ever removes content from the top of the frame; the bottom edge of the video is never clipped by this change
- [ ] Default value ships conservatively (visibly present but modest) so the mechanism can be demonstrated before final tuning
- [ ] Existing width-crop behavior at the same breakpoint is unaffected
- [ ] Follow-up noted/tracked for Toni to review and adjust the value against Mahieddine's and Mustapha's videos before considering this fully resolved

## Blocked by

None - can start immediately
