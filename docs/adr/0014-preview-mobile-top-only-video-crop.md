# Preview mobile video framing uses a global top-only crop parameter

## Context

At mobile widths, the Preview site uses a narrower `.video-shell` aspect ratio
(`1.2 / 1`) to reduce horizontal framing excess. The Video iframe and poster
are fit to the shell's height and horizontally centered. The additional
vertical crop is intended for desktop framing only; mobile must preserve the
full vertical frame so hands remain visible.

The tuning value needs to be easy to find and consistent across all Videos.

## Decision

At the `≥651px` breakpoint, the desktop `.video-shell` defines the single
global CSS custom property `--vpc-top-crop`. It currently defaults to `12%`.

The iframe and poster use:

- `height: calc(100% + var(--vpc-top-crop))` to create the excess height;
- `bottom: 0` and `top: auto` to anchor the Video's bottom edge;
- the shell's existing `overflow: hidden` to clip only the overflowing top.

The parameter is global rather than per-Video or per-Participant. The
implementation is complete, but the final percentage remains subject to
visual approval by Toni against Mahieddine and Mustapha.

The authoritative implementation location is:
`preview/components/vimeo_caption_player.css`, inside the
`@media screen and (min-width: 651px)` block.

## Consequences

- Changing one value adjusts desktop top cropping for every Video.
- Mobile keeps the existing width crop but does not apply this vertical crop.
- Bottom framing is preserved by construction.
- The existing mobile width crop remains controlled independently by the
  `.video-shell` aspect ratio.
- Increasing the value can remove a Participant's head or face, so the value
  must not be finalized without the requested visual review.
