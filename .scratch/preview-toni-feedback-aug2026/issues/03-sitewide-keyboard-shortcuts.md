Status: done

# Site-wide consistent keyboard shortcuts

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

The Space/`<`/`>` key behavior Toni noticed on the transport buttons is not app code — no keydown handling for these keys exists anywhere in the preview JS today (the only keydown handling in the codebase is `Escape` for closing picker dropdowns). It's Vimeo Player's own native embed keyboard shortcuts firing on the iframe (`attachPlayer()`, `vimeo_caption_player.js:556-559`, wraps an existing iframe without disabling them), running as a second, uncoordinated control path alongside our own Prev/Play/Next buttons.

Disable Vimeo's native keyboard shortcuts on the embed (check Vimeo Player's embed API for the relevant parameter, commonly `keyboard`). Implement one consistent app-level keydown mapping instead:

- Space → the app's Play/Pause button action (the actual playlist-aware toggle, not a raw `player.pause()`/`.play()` call)
- Left arrow → Prev button action
- Right arrow → Next button action

This mapping must be active site-wide, including on About and Participants pages, consistent with the transport cluster being always visible and functional there (per the Jul 2026 PRD). Drop `<`/`>` entirely — no shortcut should be bound to them.

Add a native `title` attribute to each of the three transport buttons (Prev, Play/Pause, Next) stating its keyboard shortcut, so it's discoverable via the browser's built-in hover tooltip (no custom tooltip component — matches the site's minimalistic style). These buttons currently only have `aria-label` (`bottom_bar.php:418-435`), no `title`.

## Acceptance criteria

- [x] Space, Left, and Right keys trigger Play/Pause, Prev, and Next respectively from anywhere on a preview page, including About and Participants
- [x] Vimeo's native keyboard shortcuts no longer fire on the embedded player (the embed uses `keyboard=0`; app-level handling owns transport shortcuts)
- [x] `<` and `>` do nothing
- [x] Prev, Play/Pause, and Next buttons each have a `title` attribute naming their shortcut, localized consistently with existing `aria-label` strings
- [x] Keydown handling does not fire while focus is inside a text input or the picker dropdowns (editable/select focus is suppressed, while Escape remains handled by the dropdown)

## Blocked by

None - can start immediately
