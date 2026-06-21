Status: ready-for-agent

# Participant catalog, grid page, and Playlist handoff

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

End-to-end Participant browsing:

1. Add a **`participant`** string field to Catalog entries; backfill from existing Video titles (e.g. `LIBRAS_São Paulo_Edinho_1 #HEARING CROWD` → `Edinho`). New Videos should get the field at Publication when practical.
2. Add `/preview/participants` with a **new grid UI** (do not reuse the legacy/Mateo thumb grid): one thumbnail per distinct Participant, no hover overlay. Label treatment per issue 05 (names under thumb or image-only).
3. Add a **Participants** button in the home player chrome nav row (PRD D10 — same button class as **[About]**; only shown when not on home). `/preview/participants` uses **← go back to player** (PRD D11).
4. Clicking a thumbnail returns to `/preview/` and loads a Playlist of **all Videos by that Participant**. While active, the Participants control shows the **Participant name** instead of the generic label.

## Acceptance criteria

- [ ] Catalog entries include `participant`; existing Videos backfilled
- [ ] Grid lists every visible Participant exactly once
- [ ] Grid has no hover metadata overlay
- [ ] Selecting a Participant plays their Videos and labels the Participants control with their name
- [ ] Visitor can reach Participants from home chrome nav (issue 06 follow-up)
- [ ] Grid label treatment matches approved design (issue 05)

## Blocked by

- [04-category-filter-pickers](../issues/04-category-filter-pickers.md) — picker button pattern for in-player filters
- [05-design-player-chrome-navigation](../issues/05-design-player-chrome-navigation.md) — grid labels only; home nav pattern already locked (PRD D10–D11)
