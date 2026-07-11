Status: done

# Functional transport on About and Participants (Option C)

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

On About and Participants, the transport cluster (Prev / Play / Next / Reset) is **always visible and functional**. The centre button shows **Play** (not Pause) because no video plays on these pages.

Persist playback context in **`sessionStorage`** while the user uses preview: playlist position, active filters, participant mode, and data needed to rebuild the queue.

### Branch A — no prior player session (`sessionStorage` empty)

**All four buttons behave identically:** navigate to `/preview/` in neutral ALL state (paused poster, fresh server shuffle — same as option A / first visit). Simplicity: user cannot tell which button they pressed; any transport control enters the player the same way.

### Branch B — prior player session exists

| Button | Action |
|--------|--------|
| **Reset** | `/preview/` — clear all filters and participant context; neutral ALL playlist; paused. |
| **Play** | `/preview/` — resume **last video** at **last position** within the saved playlist context (filters/participant preserved). |
| **Prev** | `/preview/` — load **previous video** in the saved playlist (apply −1 step on arrival; same boundary/clamp rules as in-player Prev). |
| **Next** | `/preview/` — load **next video** in the saved playlist (apply +1 step on arrival; same boundary/clamp rules as in-player Next). |

Language query params must survive navigations. Implementation may pass intent via query/hash fragment or write a one-shot flag into `sessionStorage` before navigation (e.g. `vpc-nav-intent: play | prev | next | reset`).

## Acceptance criteria

- [x] Fresh visit to About → any transport button → neutral `/preview/` paused poster
- [x] Player (video N) → About → Play → same video N, same filter/participant context
- [x] Player with filters → About → Reset → `/preview/` all filters cleared, neutral ALL
- [x] Player → About → Prev → `/preview/` on video N−1; Next → video N+1 (same boundary rules as in-player)
- [x] Centre button on About/Participants always shows Play icon, never Pause
- [x] Session survives language param in URL where applicable
- [x] Manual test script documented in issue Comments

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md)

## Comments

> **Prev/Next from secondary pages (confirmed):** Apply the step on arrival — Prev = N−1, Next = N+1 within saved playlist. Boundary/clamp rules match in-player Prev/Next.

### Manual test steps (Jul 2026)

1. **Branch A — fresh session:** Open `/preview/about` in a private window (or clear `sessionStorage`). Click Play, Prev, Next, and Reset — each should land on `/preview/` with a paused poster and neutral ALL filters (no green filter buttons, generic Participants label).
2. **Branch B — Play:** On `/preview/`, start video N (note vimeo id / participant filters). Navigate to About. Click **Play** — should return to same video N, same filters; if playback had started, should seek to last position and resume.
3. **Branch B — Prev/Next:** From playing video N in a filtered or shuffled queue, go to About. **Prev** → video N−1; **Next** → video N+1. At playlist ends, button should clamp (same as in-player).
4. **Branch B — Reset:** Apply filters and/or participant mode. About → **Reset** → `/preview/` with all filters cleared, generic Participants, paused ALL poster.
5. **Language:** Open `/preview/?lang=es`, play a video, go to About, click Play — URL should stay `?lang=es` and UI remain Spanish. Repeat with `?lang=en` (explicit `lang=en` must persist).
6. **Centre icon:** On About and Participants, transport centre button always shows **play_arrow**, never pause.

Implementation: `vpc-playback-session` + one-shot `vpc-nav-intent`; logic in `vimeo_playlist_logic.js` (`planSecondaryNavRestore`); wired in `vimeo_caption_player.js` + `secondary_player_chrome.js`.
