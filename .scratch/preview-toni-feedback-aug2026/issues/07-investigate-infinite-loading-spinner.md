Status: done

# Investigate: infinite loading spinner after a city playlist ends

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

**Symptom (Toni's report):** the Play/Pause button's loading indicator (the "hourglass" spinner, `.vpc-play-pause-btn__hourglass`, toggled by `setTransportLoading()`/`data-loading` in `vimeo_caption_player.js`) spins forever specifically when finishing an entire city's playlist. Reported in every city tested, in Chrome. A full browser refresh clears it. The Reset button silently clears the *visual* spinner but the app can then become unresponsive to further filter selection and PLAY presses (observed: stopped México's spinner via Reset, then selecting São Paulo and pressing PLAY did nothing). Navigating to the About page successfully unstuck it once (observed after São Paulo finished).

**Root cause, confirmed in Chrome:** `pauseAtPlaylistHead()` entered `loadVideoMaster()` for the first Video with `seekToStart: true`. Its `Vimeo.Player.loadVideo()` promise settled normally, but the immediately following `p.setCurrentTime(0)` never settled. Consequently the chain never reached `p.pause()`, aspect-ratio application, or `setTransportLoading(false)`.

The same terminal path applies to a Participant Playlist, which reproduced the failure more quickly. Reset launched a second load and cleared the shared visual loading state, but the stalled first load had already incremented `forcedPauseLoads`; the counter remained non-zero after Reset, so later `play` events were immediately paused.

**Fix:** `planEndOfPlaylist()` now returns `forceReload: true`. The terminal path forces `Vimeo.Player.loadVideo()` even when the head is already the current Video, and does not call `setCurrentTime(0)` for that forced reload. Vimeo loads the selected Video from its start, then the existing pause flow completes and releases `forcedPauseLoads`.

## Acceptance criteria

- [x] Reproduced the infinite spinner by completing a filtered Participant Playlist in Chrome (same terminal path as city Playlists)
- [x] Identified `p.setCurrentTime(0)` as the non-settling promise that prevented `setTransportLoading(false)`
- [x] Reproduced and explained the secondary freeze: a stalled terminal load leaked `forcedPauseLoads`, while Reset only cleared the shared visual loading state
- [x] Fixed the terminal reload by forcing `loadVideo()` and skipping the problematic seek; confirmed fixed in browser
- [x] Added a `vimeo_playlist_logic.test.js` regression assertion that every end-of-Playlist plan requests a forced reload

## Blocked by

None
