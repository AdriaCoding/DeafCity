# Preview mobile video framing uses a global top-only crop parameter

## Context

At mobile widths, the Preview site uses a narrower `.video-shell` aspect ratio
(`1.2 / 1`) to reduce horizontal framing excess. The Video iframe and poster
are fit to the shell's height and horizontally centered. A further height crop
is useful for tall-framed Participants, but must not remove content from the
bottom of a Video, where hands may be visible.

The tuning value needs to be easy to find and consistent across all Videos.

## Decision

At the `≤650px` breakpoint, `.video-shell` defines the single global CSS
custom property `--vpc-top-crop`. It currently defaults to `8%`.

The iframe and poster use:

- `height: calc(100% + var(--vpc-top-crop))` to create the excess height;
- `bottom: 0` and `top: auto` to anchor the Video's bottom edge;
- the shell's existing `overflow: hidden` to clip only the overflowing top.

The parameter is global rather than per-Video or per-Participant. The
implementation is complete, but the final percentage remains subject to
visual approval by Toni against Mahieddine and Mustapha.

The authoritative implementation location is:
`preview/components/vimeo_caption_player.css`, inside the
`@media screen and (max-width: 650px)` block.

## Consequences

- Changing one value adjusts mobile top cropping for every Video.
- Bottom framing is preserved by construction.
- The existing mobile width crop remains controlled independently by the
  `.video-shell` aspect ratio.
- Increasing the value can remove a Participant's head or face, so the value
  must not be finalized without the requested visual review.
