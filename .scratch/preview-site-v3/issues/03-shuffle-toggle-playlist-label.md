Status: ready-for-agent

# Shuffle toggle UX and active Playlist label

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

Make Playlist context visible in the player chrome. When no category filter is active, show that the visitor is watching the full-catalog Playlist (e.g. “All videos” or equivalent). When filters are added later, the label will show the selected category value — this slice establishes the label slot and wiring.

Improve the shuffle control so its **toggle state is obvious**: clear on/off random order within the current Playlist, with a visible active state when shuffle is enabled. When shuffle is off, Prev/Next and end-of-video advance follow catalog (or filter) order; when on, follow the session shuffled sequence.

## Acceptance criteria

- [ ] Active Playlist context is readable in the player chrome without opening a picker
- [ ] Shuffle control reads as a toggle with a clear active/inactive state
- [ ] Shuffle off: linear order within current Playlist; shuffle on: shuffled order (consistent with issue 01)
- [ ] Label updates when Playlist source changes (full catalog vs filtered — filtered behaviour completed in issue 04)

## Blocked by

- [01-manual-playback-random-playlist](../issues/01-manual-playback-random-playlist.md)
