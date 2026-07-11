Status: ready-for-human

# Roadmap: single-route player shell (Phases 1–3)

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

**Documentation only — do not implement in this issue.**

Capture the agreed migration path from separate routes (`/preview/`, `/preview/about`, `/preview/participants`) to a single-route player shell with dynamic views. Phase 0 (issue 01) ships first.

### Phase 1 (short term)

Same URLs, identical chrome everywhere. Full-page navigation between views. Transport on secondary pages via sessionStorage (issue 02).

### Phase 2 (medium term)

Shared shell at `/preview/`: player JS always loaded; central content swaps via `?view=player|about|participants` or hash routing. Playlist and filters live client-side; view changes without full reload. Fixes language-change video jump cleanly.

### Phase 3 (long term, if product confirms)

Full SPA: History API, prefetch, animated transitions. About and Participants as in-player panels. Evaluate after Toni validates Phase 0 UX.

## Acceptance criteria

- [ ] This issue documents phases with enough detail for a future agent or human to pick up
- [ ] Dependencies on Phase 0 listed
- [ ] Open questions noted (e.g. URL scheme, back button behaviour, SEO for About content)
- [ ] No production code changes in the PR that closes this issue

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md) — Phase 0 must ship before Phase 1 work

## Comments

> Created from grill session Jul 2026. User requested GitHub-style tracking; lives in `.scratch/` per project convention. Mirror to GitHub if/when remote issues are enabled.
