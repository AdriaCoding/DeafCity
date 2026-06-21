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

## Out of Scope (this batch)

- Tags cloud page (§4.1 — next sprint)
- Edition map page (§4.2 — next sprint)
- Replacing the live homepage (Preview remains at `/preview/` until Antoni approves)

## Issue index

| # | Issue | Type |
|---|-------|------|
| 01 | Manual playback and default random Playlist | AFK |
| 02 | Caption layout and brand typography | AFK — **done** |
| 03 | Shuffle toggle UX and active Playlist label | AFK |
| 04 | Category filter pickers | AFK |
| 05 | Design player chrome and navigation | HITL |
| 06 | Home layout and site navigation | AFK |
| 07 | Custom Subtitle language picker | AFK |
| 08 | Participant catalog, grid page, and Playlist handoff | AFK |
| 09 | About page with legacy clock, gallery, and credits | AFK |
