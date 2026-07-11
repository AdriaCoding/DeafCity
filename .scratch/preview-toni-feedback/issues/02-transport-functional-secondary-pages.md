Status: ready-for-agent

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

- [ ] Fresh visit to About → any transport button → neutral `/preview/` paused poster
- [ ] Player (video N) → About → Play → same video N, same filter/participant context
- [ ] Player with filters → About → Reset → `/preview/` all filters cleared, neutral ALL
- [ ] Player → About → Prev → `/preview/` on video N−1; Next → video N+1 (same boundary rules as in-player)
- [ ] Centre button on About/Participants always shows Play icon, never Pause
- [ ] Session survives language param in URL where applicable
- [ ] Manual test script documented in issue Comments

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md)

## Comments

> **Prev/Next from secondary pages (confirmed):** Apply the step on arrival — Prev = N−1, Next = N+1 within saved playlist. Boundary/clamp rules match in-player Prev/Next.
