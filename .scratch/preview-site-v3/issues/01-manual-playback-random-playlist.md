Status: ready-for-agent

# Manual playback and default random Playlist

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

Change the Preview player so a cold visit loads **all visible Catalog Videos** in a **fresh random order** with shuffle **on by default**, but **does not autoplay**. Remove the mute/unmute affordance entirely — the visitor presses Play to start, as on the legacy homepage.

When a Video ends, advance to the next entry in the current Playlist **only if** the visitor had already started playback during this visit. Do not auto-start the first Video on page load. Prev/Next and end-of-video advance must respect the shuffled sequence for the session. Reloading the page produces a new random order (not persisted across visits).

This supersedes the muted-autoplay behaviour described in the older preview-player-polish PRD for this iteration.

## Acceptance criteria

- [ ] Page load: first Video visible, paused, no sound, no autoplay, no mute/unmute control
- [ ] Default Playlist is all visible Catalog Videos, shuffled on each page load
- [ ] Shuffle is on by default for the full-catalog Playlist
- [ ] After visitor presses Play, end-of-video advances within the Playlist (respecting shuffle)
- [ ] Reset still restarts the current Video from t=0 only
- [ ] Reloading `/preview/` yields a different random order; over many reloads each Video has roughly equal chance of appearing first

## Blocked by

None — can start immediately
