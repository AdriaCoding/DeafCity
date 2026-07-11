Status: done

# Participant name on Participants nav button

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

Two distinct behaviours for the Participants nav button:

### Participant playlist selected (`?participant=` or equivalent session)

- Button is **green** (`is-active`) whenever a participant-specific playlist is active.
- Button label is always the **participant name** — playing or paused, on player / about / participants pages.

### Neutral playlist (not participant-specific)

- Button is always **gray** (default).
- Default label: **"Participants"**.
- On the **player page only**, while the video is **playing**, show the current video's participant name instead of "Participants" (still gray, not green).

Filter narrowing to a single participant (e.g. via Cities or Sign Language) does **not** enter participant-playlist mode; it follows the neutral rules above.

## Acceptance criteria

- [x] Enter via Participants grid → green + name on button (all pages)
- [x] Participant playlist: name visible while playing and while paused
- [x] Participant playlist: name + green on About / Participants pages (from session)
- [x] Neutral playlist paused on player → gray "Participants"
- [x] Neutral playlist playing on player → gray + current participant name
- [x] Single-participant filter without participant mode → gray; name only while playing on player
- [x] Multiple participants in playlist → generic "Participants" label (neutral rules)
- [x] Reset clears green + name back to generic
- [x] Tests in `site_nav_test.php` and `vimeo_playlist_logic.test.js`

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md)
