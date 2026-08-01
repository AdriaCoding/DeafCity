Status: needs-triage

# Investigate: infinite loading spinner after a city playlist ends

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Root cause is not confirmed — this issue needs a live-reproduction investigation (e.g. a `/grill-me` or debugging session with the agent in charge, using an actual browser) before a fix can be scoped with confidence. Recommend starting with reproduction, not a blind fix.

**Symptom (Toni's report):** the Play/Pause button's loading indicator (the "hourglass" spinner, `.vpc-play-pause-btn__hourglass`, toggled by `setTransportLoading()`/`data-loading` in `vimeo_caption_player.js`) spins forever specifically when finishing an entire city's playlist. Reported in every city tested, in Chrome. A full browser refresh clears it. The Reset button silently clears the *visual* spinner but the app can then become unresponsive to further filter selection and PLAY presses (observed: stopped México's spinner via Reset, then selecting São Paulo and pressing PLAY did nothing). Navigating to the About page successfully unstuck it once (observed after São Paulo finished).

**Suspected mechanism, unverified:** `setTransportLoading(true)` is set at the start of `loadVideoMaster()` (`vimeo_caption_player.js:1099`) and only cleared in that function's success (`:1142`) or failure (`:1155`) callbacks. At end-of-playlist, `advanceOnEnded()` finds no next step and calls `pauseAtPlaylistHead()` (`:1182`), which calls `L.planEndOfPlaylist(...)` and, if it returns a plan, calls `loadVideoMaster()` again to reload and pause on the playlist's first video. If that specific reload's promise chain never settles — plausibly a race between the Vimeo `ended` event and immediately reissuing a seek/reload on the same player instance — the spinner would stay stuck. This is a hypothesis to verify, not a confirmed cause.

## Acceptance criteria

- [ ] Reproduce the infinite spinner reliably by playing a city's filtered playlist through to its end in Chrome
- [ ] Identify the actual point where `setTransportLoading(false)` fails to run (or confirm/refute the `pauseAtPlaylistHead`/`loadVideoMaster` hypothesis above)
- [ ] Reproduce and explain the secondary freeze (Reset silently clearing the visual spinner but leaving filter selection and PLAY unresponsive)
- [ ] Fix the root cause so the spinner always clears when `loadVideoMaster()`'s promise settles, in every code path that calls it, including end-of-playlist
- [ ] Regression test covering end-of-playlist reload completing (not hanging) added to the appropriate test suite

## Blocked by

None - can start immediately, but recommend a reproduction/investigation pass before implementation
