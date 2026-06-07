# Preview player: muted autoplay with a click-anywhere unmute affordance

The Preview site player autoplays every Video **muted**, and surfaces an **unmute affordance** so viewers turn sound on with one gesture. Clicking anywhere on the video toggles mute/unmute; play/pause lives on the transport button below. Once a viewer unmutes, sound stays on for the rest of the session.

## Why this came up

The audio of a Video matters: it carries the sounds Deaf people make when communicating, and the project wants hearing viewers to perceive that. So the player must end up playing **with sound** — there is no value in a persistent mute control, and the experience should not leave a hearing viewer watching silently without realising sound exists.

The tension is browser autoplay policy: on a cold page load with no prior user gesture, every modern browser blocks **unmuted** autoplay. Only **muted** autoplay is allowed before the first interaction.

## Decision

1. **Autoplay muted, always.** Every Video starts muted so it plays immediately on cold load without being blocked.
2. **Unmute on gesture.** Clicking anywhere on the video surface toggles mute/unmute. The first unmute is the user gesture that lets the browser keep audio on thereafter.
3. **Session-sticky sound.** Once unmuted, subsequent Videos (auto-advance, prev/next, sign-language change) play unmuted with no re-prompt. The viewer can still click the video to re-mute.
4. **Corner badge as the visible affordance and state indicator.**
   - While muted: badge always visible (invites unmuting).
   - While unmuted on a hover-capable device: badge hidden, revealed on hover.
   - While unmuted on a touch device: badge flashes ~2s after a tap, then fades (no real hover; avoids a silent re-mute with no feedback).
5. **Accessibility.** The badge is a real focusable `<button>`, labelled and state-reflecting (Mute/Unmute), so keyboard and screen-reader users can toggle sound. The full-surface video hitarea remains an unlabelled `aria-hidden` mouse/touch convenience layered on top.

Play/pause is **not** on the video surface — it is the transport button below the video. The video surface is dedicated to the mute/unmute toggle.

## Consequences

- A hearing viewer always sees a clear, honest signal that sound is available, and never has to discover hidden native controls (the Vimeo chrome is disabled, `controls=0`).
- The first Video on a cold visit plays silently until the viewer's first click — accepted, because muted autoplay is the only path that reliably starts on load, and the badge advertises the missing sound immediately.
- Reassigning the video-surface click from play/pause to mute/unmute is why play/pause must remain on the transport button.

**Considered options:**

- **Muted autoplay + click-anywhere unmute, session-sticky, with a corner badge** — chosen. Always starts; honest about sound; one gesture; no nagging.
- **Unmuted autoplay, tap-to-play overlay only when blocked** — rejected. On cold load the browser blocks unmuted autoplay almost every time, so the overlay would be the normal first-load path, not a rare fallback — effectively a play-gate on every visit.
- **Muted autoplay that silently unmutes on any click, no badge** — rejected. A hearing viewer could watch an entire Video silently without realising sound exists; no honest signal.
- **A dedicated mute/unmute button in the transport** — rejected. Sound is the default intent; a persistent mute control adds chrome for an action the project does not want to encourage.
