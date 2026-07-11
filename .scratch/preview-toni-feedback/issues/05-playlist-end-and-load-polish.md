Status: done

# Playlist end behaviour and video load polish

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

**End of playlist (generalized):** When the current playlist is exhausted (participant collection, filtered subset, or full ALL), land **paused** on the **thumbnail of the first video in the current playlist** — first in the active playback sequence, not catalog order. Do not redirect to `/preview/` for participant end; do not reshuffle to a random new poster for ALL end.

**Load polish:** Eliminate brief thumb/aspect-ratio flash when a video ends or when advancing. Avoid exposing gray/white letterbox during transitions. Show a **loading indicator on the Play/Pause button** while `loadVideoMaster()` is in flight instead of resetting the iframe to a 16:9 placeholder prematurely.

## Acceptance criteria

- [x] Participant playlist end: paused poster = first video of that participant playlist; no full-page redirect
- [x] Filtered playlist end: paused poster = first video of filtered playlist
- [x] Full ALL playlist end: paused poster = first video of current playback sequence
- [x] No visible thumb flash on end-of-video advance
- [x] No gray frame flash on next/previous selection (or reduced to imperceptible; spinner on play button during load)
- [x] `vimeo_playlist_logic.test.js` covers end-of-playlist plan; manual check on HD test video

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md)
