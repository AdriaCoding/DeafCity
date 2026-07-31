# Preview player: language switch carries resume intent after playback progress

The Preview site language picker in player chrome (`?lang=...`) must preserve playback intent when the current Video has already started. Language switch is a page navigation, but for in-play viewing it must behave like an in-session continuation, not a cold paused load.

## Why this came up

On `Hugo 3`, changing language after the first cue appeared could show a Subtitle over the thumbnail ("ghost caption"). Runtime logs showed this sequence:

1. playback session saved at a non-zero time;
2. language switch navigated without `vpc-nav-intent=play`;
3. restore plan loaded with `navPlanAutoplay=false` at saved time;
4. paused thumbnail state rendered while subtitle cue at that time was active.

The `.vtt` files were not malformed; first cues started at `00:00:03.200`. The issue was restore intent, not cue timing data.

## Decision

1. **Language switch sets resume intent when viewing has progressed.**
   - Set `vpc-nav-intent=play` when transport is playing, or when transport is not loading and `currentTimeSec > 0.25`.
2. **Transport state for this decision is explicit.**
   - Player publishes shared transport state (`playing`, `loading`, `currentTimeSec`) for language picker logic.
3. **Restore honors play intent across language navigation.**
   - With `navIntentRaw="play"`, restore keeps playback continuity (`shouldAutoplay=true`) instead of paused restore at saved timestamp.

## Consequences

- Language changes no longer produce paused-thumbnail + in-cue subtitle overlays.
- Subtitle text remains synchronized to playback continuity after language switch.
- This behavior applies broadly; `Hugo 3` exposed it because early cues make the paused-overlay artifact obvious.

**Considered options:**

- **Keep language switch as non-play navigation** — rejected. It reproduces the ghost-caption failure when session time is mid-cue.
- **Clear saved playback time on language switch** — rejected. It breaks continuity and causes avoidable restarts.
