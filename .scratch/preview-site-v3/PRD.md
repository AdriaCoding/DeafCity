Status: ready-for-agent

# PRD — Preview site v3 (3rd iteration)

Parent spec: [preview/tasks.md](../../preview/tasks.md)

## Problem Statement

The `/preview/` site validates the next DEAF.city homepage with a Playlist-driven player, separate About and Participants routes, and a minimal full-viewport home layout. The current prototype embeds About inline, uses muted autoplay with an unmute badge, and lacks category filter pickers, Participant browsing, and the legacy About experience (clock, gallery, credits).

## Solution

Implement the v3 spec as thin vertical slices: playback and Playlist behaviour first, then player chrome and filtering, then design-gated navigation and subtitle picker, then Participants and About. Design decisions (home navigation, subtitle picker placement, participant grid labels) are a separate HITL slice.

## Decisions (locked)

| # | Decision |
|---|----------|
| D1 | Reset restarts the **current Video** from t=0 only |
| D2 | Default Playlist: **all visible Videos**, **fresh random order on every visit**, equal probability per Video |
| D3 | Captions sit **above** the video (not below), flush against the video top edge |
| D4 | Caption font size scales from **rendered iframe height** (~4.8%, clamped 14–38px) — not stack/viewport width |
| D5 | Caption box reserves **two lines** fixed height; single-line cues align to the **bottom** row (closest to video) |
| D6 | Caption box width matches **visible video width** (iframe width, capped at shell width), centered in the stack |
| D7 | Sign language filter: **no default filter** on cold visit — Playlist includes all visible Videos until the visitor picks a language |
| D8 | Click on the video area toggles play/pause (transparent hit-area overlay over the cross-origin iframe) |
| D9 | **Home page is non-scrollable** — `html`/`body` and player root use `overflow: hidden`; the viewport never scrolls on `/preview/` |
| D10 | **Site navigation on home** — link buttons in the **player chrome**, below transport controls and language filters (not a top nav bar). Only **other routes** are shown (no active/current-page button). Same button style as secondary transport controls |

## Out of Scope (this batch)

- Tags cloud page (§4.1 — next sprint)
- Edition map page (§4.2 — next sprint)
- Replacing the live homepage (Preview remains at `/preview/` until Antoni approves)

## Issue index

| # | Issue | Type |
|---|-------|------|
| 01 | Manual playback and default random Playlist | AFK — **done** |
| 02 | Caption layout and brand typography | AFK — **done** |
| 03 | Shuffle toggle UX and active Playlist label | AFK |
| 04 | Category filter pickers | AFK |
| 05 | Design player chrome and navigation | HITL |
| 06 | Home layout and site navigation | AFK |
| 07 | Custom Subtitle language picker | AFK |
| 08 | Participant catalog, grid page, and Playlist handoff | AFK |
| 09 | About page with legacy clock, gallery, and credits | AFK |

## Issue 01 — implementation notes (2026-06-21)

Playback slice shipped. Corrections to the written spec:

| Topic | Original assumption | Shipped behaviour |
|-------|---------------------|-------------------|
| Full-catalog Playlist | All visible Videos on load | Sign language picker previously defaulted to the first language, filtering the Playlist; now **All sign languages** (D7) |
| First Video on load | Shuffled order from first paint | PHP iframe still renders catalog-order video 0; JS may swap on SDK attach |
| Broken Vimeo embeds | — | No player fallback; unembeddable Videos show Vimeo error — fix in catalog / Vimeo settings |
| Video click | Play via transport only | Hit-area overlay toggles play/pause (D8) |
| Shuffle UX | On by default | Toggle state set in markup/JS; label and obvious toggle UX deferred to issue 03 |
