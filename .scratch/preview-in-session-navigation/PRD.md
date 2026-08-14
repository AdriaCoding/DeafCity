# Preview site: in-session navigation (no full page reloads)

## Problem

Every transition in the Preview site is a full document load: Website language switch
(`?lang=`), and navigation between the player, About, and Participants. Each reload
destroys three things, in cost order:

1. **The Vimeo iframe.** New document means re-downloading the Player SDK, redoing the
   postMessage handshake, refetching the manifest and rebuffering — roughly 1–2s of dead
   air on every Website language change.
2. **Browser user-activation state.** A reload wipes the "visitor has interacted" bit that
   authorizes playback with sound.
3. **All player state** — filter facets, shuffle sequence, cursor, playback position.

The reported symptom is that the app "does not feel fluid", most acutely when changing
Website language.

## What already exists (and what it costs)

The Preview site has already paid heavily to work around these reloads. A whole subsystem
exists only to survive them: the playback session snapshot/restore path, the
`vpc-nav-intent` handshake, the `vpc-playback-activated` / `vpc-gesture-activated`
activation forgery, and a fake transport bar on About/Participants that mimics a player
which is not on the page. That is roughly 500+ lines whose only purpose is reload tax.

ADR-0013 exists for the same reason: changing Website language mid-playback produced a
"ghost caption" (paused thumbnail with an in-cue Subtitle drawn over it), fixed by
carrying a resume intent across the navigation.

## Key insight

The player is **already client-rendered**. The server ships a complete resolved chrome
string map into JS, and the JS already rebuilds every picker face, dropdown option and
nav label from it. The server-rendered labels are only the initial paint — JS overwrites
them moments later regardless.

Measured payload: 53 chrome keys across 8 Website languages (`ar ca en es eu fr it pt`),
~4.0–4.6 KB per language uncompressed; all eight together are ~35 KB raw, under 10 KB
gzipped.

So a Website language switch does not need a page load, or even necessarily a network
round trip. It needs to swap a ~4 KB object and re-run functions that already exist.
**The reload is vestigial.**

## Approach

Four vertical slices, each demoable on its own, ordered so each one's output feeds the
next. Slice 1 removes the clunkiness that was actually reported; slices 3 and 4 remove
the cause of the workaround code, then the workaround code itself.

## Non-goals

- Adopting a JS framework. The behavior here is heavily specified (ADRs 0008, 0009, 0012,
  0013, 0015 plus a long tail of D-numbered decisions in code comments) and hard-won
  around autoplay gating, shuffle seeding, poster/queue-head coherence and filter
  composition. A rewrite would not remove that complexity, only force it to be
  re-derived in an unfamiliar execution model. The reload is the defect, not the
  vanilla JS.
- Changing what any Website language *says*. Localized copy is owned by the Studio and by
  Antoni; this work changes only *when and how* strings are delivered to the page.
- Changing playback, shuffle, filter or Playlist semantics anywhere.

## Testing posture

This repo has no DOM test harness, by an earlier explicit decision. Automated coverage
therefore targets pure functions (`node preview/tests/*.test.js`) and PHP functions and
page output (`php8.4 preview/tests/*_test.php`). Decisions belong in pure functions; DOM
mutation stays a thin shim verified manually in the browser. Each slice states its own
manual verification steps.

## Slices

1. `issues/01-website-language-switch-player-page.md` — no-reload Website language switch
   on the player page (tracer bullet)
2. `issues/02-website-language-switch-secondary-pages.md` — same for About & Participants,
   introducing the route fragment endpoint
3. `issues/03-persistent-player-shell.md` — About & Participants stop being page
   navigations; the Video stays mounted
4. `issues/04-retire-reload-tax-machinery.md` — delete the session/intent/activation
   workarounds that no longer have a reason to exist
