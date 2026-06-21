Status: ready-for-agent

# Custom Subtitle language picker

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

Replace the native Subtitle language `<select>` with a **custom picker** matching the category filter button style (issue 04), placed per the approved design (issue 05). Subtitle language choice must **stick across Videos** in the Playlist when the next Video supports that language (existing sticky-track behaviour).

## Acceptance criteria

- [ ] Subtitle language picker uses custom green-accent styling consistent with category pickers
- [ ] Picker placement matches approved design
- [ ] Selected Subtitle language persists across Videos when available; falls back when not
- [ ] Picker works with filtered and unfiltered Playlists

## Blocked by

- [04-category-filter-pickers](../issues/04-category-filter-pickers.md)
- [05-design-player-chrome-navigation](../issues/05-design-player-chrome-navigation.md)
