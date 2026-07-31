# Preview player: intent-gated playback with sound and no mute control

The Preview site player begins each new session paused, with sound available and no possibility to mute a Video. A visitor's first interaction that reasonably expresses willingness to watch authorizes playback with sound; subsequent Videos play with sound for the rest of the session.

## Why this came up

The audio of a Video matters: it carries the sounds Deaf people make when communicating, and the project wants hearing viewers to perceive that. A Video must therefore play **with sound** — there is no value in a mute control or a silent first playback.

The tension is browser autoplay policy: on a cold page load with no qualifying visitor interaction, browsers can block unmuted autoplay. Starting paused avoids a silent playback state and makes the visitor's intent the authorization for audio.

## Decision

1. **Begin paused with sound.** A cold load does not request autoplay or muted playback. The first Video remains paused until a qualifying visitor interaction.
2. **Intent-gated playback.** The following interactions authorize the selected Video to begin immediately with sound:
   - the dedicated Play control;
   - clicking or tapping the Video surface;
   - selecting a Participant;
   - previous/next Video navigation; and
   - a filter or collection change that selects a Video.
3. **Non-qualifying interaction.** Opening or closing UI and changing Caption language do not authorize playback.
4. **Session-sticky playback intent.** Once a qualifying interaction has started playback, automatic Video transitions and later selected Videos play with sound for the rest of the session. If unmuted autoplay is rejected, the Video remains paused; it must not fall back to muted playback.
5. **No mute state or control.** The player exposes no mute/unmute control, does not store a muted or sound-preference state, and never assigns mute behaviour to the Video surface. Vimeo chrome remains disabled.
6. **Accessibility.** The existing transport Play control remains the focusable playback control. The Video surface is a pointer shortcut for play/pause, not a sound control.
## Consequences

- A hearing visitor never encounters a silently playing Video.
- The first Video on a cold visit waits for a visitor interaction that signals willingness to watch.
- A Participant selection or other Video-selection interaction can start the first Video with sound without forcing a preliminary Play click.
- The player has no mute affordance to discover or operate.

**Considered options:**

- **Paused cold load + intent-gated unmuted playback, session-sticky** — chosen. The first interaction that expresses intent starts sound; no silent playback or mute state.
- **Muted autoplay + click-anywhere unmute, session-sticky, with a corner badge** — superseded. It created an unwanted mute/unmute control and allowed a silent first playback.
- **Unmuted autoplay, tap-to-play only when blocked** — rejected. It depends on a browser block as normal interaction design rather than intentionally waiting for visitor intent.
- **A dedicated mute/unmute control** — rejected. The project does not want a possibility to mute Videos.
