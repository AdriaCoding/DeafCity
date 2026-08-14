# Preview player: Website language switch happens in session, without navigation

On the Preview player page, changing Website language no longer loads a new document.
The Vimeo iframe stays mounted and playing; the page swaps its localized strings, text
direction and Subtitle track in place.

Supersedes ADR-0013 for the Website language path. ADR-0013 still governs navigation to
About and Participants, which remain page loads until the persistent player shell lands.

## Why this came up

The Preview site felt unresponsive, most sharply when changing Website language. The
cause was structural rather than cosmetic: `?lang=` was a server-resolved query
parameter, so every language change was a full document load. That destroyed, in cost
order, the Vimeo iframe (re-downloading the Player SDK, redoing the postMessage
handshake, refetching the manifest and rebuffering), the browser's user-activation
state, and all player state.

The Preview site had already paid heavily to survive those reloads: a playback session
snapshot/restore path, a `vpc-nav-intent` handshake, user-activation forgery in
sessionStorage, and a transport bar on secondary routes that mimics a player which is not
on the page. ADR-0013 is part of that same tax — it exists because a language switch
reloaded into a *paused restore at a saved timestamp*, which could render a Subtitle cue
over a thumbnail (the "ghost caption").

Two observations made the reload look vestigial rather than necessary:

1. The player is already client-rendered. The server ships a resolved chrome string map
   into JS, and the JS already rebuilds picker faces, dropdown options and nav labels
   from it. The server-rendered labels are only the initial paint.
2. The payload is small. 53 chrome keys across 8 Website languages measure ~4.0–4.6 KB
   per language uncompressed.

## Decision

1. **The Website language switch is an in-session update, not a navigation.**
   Nothing about the Video is touched, so there is no playback to save, no state to
   restore, and no resume intent to carry.
2. **The server remains the single source of truth for every localized label.**
   A read-only endpoint returns the resolved chrome strings, direction and localized
   filter option labels for a Website language, built by the same PHP functions that
   render them server-side. A switched page and a cold load at `?lang=<id>` cannot
   drift apart.
3. **Server-rendered strings that JS does not already own carry `data-i18n-*` markers.**
   One generic pass repaints text, accessible names and the picker face labels that the
   filter pickers read back.
4. **The address bar is kept in sync via `replaceState`**, preserving every other
   parameter — notably `participant`, so a refresh cannot drop the viewer out of a
   Participant's Playlist.
5. **Failure falls back to the previous behavior.** If the payload cannot be fetched the
   picker navigates to the `?lang=` URL as before, so the page can never be left
   half-translated.

## Consequences

- Changing Website language mid-playback is continuous: no flash, no rebuffer, no lost
  position, and no re-gating of playback with sound.
- ADR-0013's ghost caption becomes structurally impossible on this path. There is no
  restore, so there is no paused-thumbnail-with-active-cue state to guard against. The
  resume-intent code remains in place only for the routes that still navigate.
- Subtitle track selection after a switch routes through the same picker a cold load
  uses, so the two cannot disagree about which track is showing.
- Accessible-name composition (`"<label> (<hint>)"`) now exists in both PHP and JS. This
  duplication is deliberate and covered by a test that recomposes the JS rule and
  requires it to reproduce the server's output exactly.

**Considered options:**

- **Adopt a JS framework** — rejected. The value a framework offers here is declarative
  re-render, and those re-render functions already exist and are hand-tuned. The
  behavior around autoplay gating, shuffle seeding, poster/queue-head coherence and
  filter composition is heavily specified across several ADRs and a long tail of
  in-code decisions; a rewrite would force all of it to be re-derived in an unfamiliar
  execution model without removing any of the complexity. The reload was the defect,
  not the vanilla JS.
- **Ship all languages' string maps inline** — rejected for now. At ~35 KB raw it is
  affordable, but it puts eight languages on the critical path of every first paint to
  save one small request on an action most visitors take zero or one times.
- **Translate on the client from a single base language** — rejected. It would make the
  browser a second source of truth for copy that Producers own in Studio.
