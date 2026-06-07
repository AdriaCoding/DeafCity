Status: ready-for-agent

# PRD — Preview player polish (sound, autoplay, auto-advance, sticky captions, aspect ratio)

Relevant ADRs: [ADR-0001](../../docs/adr/0001-server-hosted-subtitles.md) (server-hosted caption files), [ADR-0008](../../docs/adr/0008-preview-player-per-video-aspect-ratio.md) (runtime per-Video aspect ratio), [ADR-0009](../../docs/adr/0009-preview-player-muted-autoplay-unmute-affordance.md) (muted autoplay + unmute affordance).

## Problem Statement

The Preview site player has richer Subtitle features than the legacy homepage (custom caption box above the video, multi-language caption picker, sign-language filter), but several core playback behaviours are unfinished or wrong for DEAF.city:

- The player forces muted playback and offers no way to hear audio through its custom controls. The audio matters — it carries the sounds Deaf people make when communicating, and hearing viewers must perceive it.
- When a Video ends, the player just stops. The legacy homepage auto-advances through the Playlist; the Preview player does not.
- The caption picker resets to the first track every time a Video loads, so a viewer's chosen Subtitle language does not persist across Videos.
- The frame is hardcoded to 16:9 and stretches the iframe to fill it. Sign-language monologues are often filmed in portrait or other ratios, so they are letterboxed or mis-framed.

## Solution

Finish and polish the Preview player so it plays with sound (muted autoplay plus a clear unmute affordance), advances through the Playlist on its own, remembers the viewer's Subtitle language, and frames each Video at its true aspect ratio — all without exposing native Vimeo chrome (`controls=0`) and without orphaning the out-of-iframe caption box.

This is player-only work. The thumbnail/metadata gallery, Playlist-selector dropdown, map, clock, and about/credits remain out of scope ("the other things" to remake later).

## User Stories

1. As a hearing viewer, I want each Video to play with sound, so that I can perceive the vocal sounds Deaf Participants make when they communicate.
2. As a viewer on a cold page load, I want the first Video to start playing immediately (muted), so that I am never blocked by browser autoplay policy.
3. As a viewer, I want a clear "unmute" badge over the video while it is muted, so that I know sound is available and how to turn it on.
4. As a viewer, I want to click anywhere on the video to toggle sound, so that turning audio on is effortless.
5. As a viewer, I want my unmute choice to persist for the rest of the session, so that subsequent Videos play with sound and I am not re-prompted on every Video.
6. As a viewer who has turned sound on, I want the badge to stay out of the way (revealed only on hover on desktop), so that it does not cover the performance.
7. As a viewer on a touch device who has turned sound on, I want the badge to flash briefly after I tap, so that I get feedback when I toggle sound and never silently re-mute without knowing why.
8. As a keyboard or screen-reader user, I want a real, labelled, focusable control to mute/unmute, so that I can toggle sound without a mouse.
9. As a viewer, I want play and pause on a dedicated transport button below the video, so that clicking the video itself is reserved for sound toggling.
10. As a viewer, I want the Video to automatically advance to the next Video in the Playlist when it ends, so that I can watch a sequence without interacting.
11. As a viewer in shuffle mode, I want auto-advance to follow the shuffled order, so that ended-driven advancing matches my prev/next navigation.
12. As a viewer on the last Video of the (filtered) Playlist, I want playback to stop at the end rather than loop, so that the sequence has a clear finish.
13. As a viewer who selected a Subtitle language (e.g. English), I want that language to stay selected as I move between Videos, so that I do not have to re-pick it on every Video.
14. As a viewer, I want the player to fall back to the first available track only when the next Video lacks my chosen language, so that captions are always shown when possible.
15. As a viewer watching a portrait performance, I want the frame to match the Video's real shape, so that the performer is not letterboxed or cropped.
16. As a viewer, I want the frame to stay at a stable 16:9 until the real ratio is known, so that the page does not jump on load.
17. As a viewer, I want the large green-on-white caption box to remain above the video, so that the Subtitle stays prominent and readable.
18. As a viewer, I do not want a fullscreen control, so that the caption box (rendered outside the iframe) is never hidden from me.

## Implementation Decisions

All work is contained in the Preview player stack: `preview/js/vimeo_caption_player.js`, `preview/components/vimeo_caption_player.php`, `preview/components/vimeo_caption_player.css`. Pure-logic units are extracted as named functions inside the existing IIFE (no module system / bundler is introduced); stateful behaviours are small controllers wired in `attachPlayer`. Decomposition confirmed with the developer.

### Sound: muted autoplay + unmute affordance (ADR-0009)

- Every Video autoplays **muted** (embed already sets `muted=1`, `autoplay=1`), so playback always starts on cold load.
- A **corner badge** over the video is the canonical sound control:
  - While muted: always visible, inviting unmute.
  - While unmuted on hover-capable devices: hidden, revealed on hover of the video area.
  - While unmuted on touch devices: flashes ~2s after a tap, then fades.
- **Clicking anywhere on the video surface toggles mute/unmute.** The existing `.vpc-video-hitarea` is repurposed from play/pause to the mute/unmute toggle.
- Play/pause moves entirely to the existing transport button below the video (`.vpc-play-pause-btn`); it no longer shares the video surface.
- **Session-sticky:** once unmuted, the player remembers the choice and loads subsequent Videos unmuted (after the first user gesture the browser permits unmuted playback). The viewer can still click to re-mute.
- **Accessibility:** the badge is a real focusable `<button>` with a state-reflecting label (Mute/Unmute, e.g. via `aria-pressed`). The full-surface hitarea stays as an unlabelled `aria-hidden` mouse/touch convenience layered on top.

### Playlist: auto-advance (pure unit `nextPlaylogicStep`)

- On the Vimeo `ended` event, advance to the next Video.
- Respect shuffle: in shuffle mode follow `shuffledSequence[shuffleStep + 1]`; otherwise `filteredCursor + 1`.
- Stop (no loop) when already on the last entry of the filtered list.
- Reuse the existing `seekFiltered` / `seekShuffle` paths so auto-advance and manual prev/next share one code path.

### Captions: sticky Subtitle language (pure unit `pickStickyTrackIndex`)

- Replace the hard reset to track 0 in `applyLoadedVideoUi`.
- On Video load, remember the label of the currently active track; in the new Video, pick the track whose label matches; fall back to index 0 when no match exists.
- Caption box position (above video) and visual treatment (green-on-white, large clamp font) are unchanged.

### Framing: runtime per-Video aspect ratio (ADR-0008; pure unit `aspectRatioFrom`)

- Keep `.video-shell` at `aspect-ratio: 16/9` as the initial placeholder for layout stability.
- After each Video loads, read `player.getVideoWidth()` / `getVideoHeight()` and set the shell's aspect ratio to the true value (16:9 fallback when dimensions are unavailable).
- No Catalog/data schema changes — dimensions come from the SDK at runtime, not from `catalog.json`.

### Fullscreen

- No fullscreen control is added. Native iframe fullscreen stays disabled because it would orphan the out-of-iframe caption box.

## Testing Decisions

Confirmed with the developer: **manual/browser verification only.** The repo has no JavaScript test runner (no `package.json`, no jest/vitest/mocha); all existing automated tests are PHPUnit on the Studio side, and the existing player ships without automated tests. No JS harness will be stood up for this work, and no new PHP tests are required (the PHP component changes are limited to markup for the focusable badge button).

Pure-logic units (`nextPlaylogicStep`, `pickStickyTrackIndex`, `aspectRatioFrom`) are factored out as small, side-effect-free functions so they *could* be unit-tested later if a harness is introduced, but that is not part of this PRD.

Manual verification checklist (browser):

- Cold load: first Video autoplays muted; badge visible; clicking video unmutes; sound persists to next Video; badge hover-reveals on desktop, flashes after tap on touch.
- Keyboard: badge is tabbable and toggles sound; label reflects state.
- Auto-advance: Video advances on end in both linear and shuffle modes; stops on the last entry.
- Sticky captions: pick English, advance — English stays selected when present, falls back to first track when absent.
- Aspect ratio: a portrait Video frames correctly; layout starts at 16:9 and snaps without large jumps.

## Out of Scope

- The thumbnail/metadata gallery, Playlist-selector dropdown, map, clock, and about/credits sections (the "other things" to remake after the player).
- A custom progress/scrub bar (explicitly declined — the restart button is sufficient).
- A dedicated mute button in the transport row (sound is the default intent; the video surface + badge cover toggling).
- Fullscreen (declined — would hide the caption box).
- Any Catalog (`catalog.json`) schema change to store Video dimensions (ADR-0008 chose runtime SDK over baking dimensions).
- Standing up a JavaScript test harness or writing automated player tests.
- Changes to caption fetching / server-hosted caption files (already covered by ADR-0001).

## Further Notes

- The legacy homepage player (`leaflet/js/deafcity.js`, `views/index.php`) is the reference for auto-advance and per-Video aspect ratio behaviour; it sources width/height from a live `VideoCache` Vimeo lookup that the Preview pipeline never recorded — hence the runtime-SDK approach in ADR-0008.
- Grilling session decisions (2026-06-07) confirmed interactively: unmuted experience via muted-autoplay + unmute badge; click-video = mute/unmute; play/pause on transport button; auto-advance respecting shuffle, stop at end; no progress bar; sticky caption language by label; no fullscreen; runtime aspect ratio. No open design branches remain for implementation.
