Status: ready-for-agent

# Category filter pickers (Sign language, Edition, Typology)

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

Add three Playlist filter controls to the Preview player, each a **button of the same visual class** opening a **custom-styled** dropdown or dropup (not a native `<select>`), using brand green accents:

| Picker | Catalog field | Display label from |
|--------|---------------|-------------------|
| Sign language | `sign_language` | `studio-config.json` → `sign_languages` |
| Edition (ciutat) | `edition` | `studio-config.json` → `editions` |
| Typology | `typology` | `studio-config.json` → `typologies` |

Pickers **compose**: active selections narrow the Playlist to Videos matching all chosen filters. Each button shows the **selected value** once chosen; before selection, show the generic picker label. Changing filters replaces the Playlist and starts from the first Video in the new list. The Playlist label (issue 03) shows the relevant category value(s) when filtered.

Invisible Catalog entries stay excluded.

## Acceptance criteria

- [ ] Three pickers render with consistent styling and brand green accents
- [ ] Selecting Sign language, Edition, and/or Typology filters the playing Playlist end-to-end
- [ ] Selected value persists on the button face after the menu closes
- [ ] With no picker selected, default remains the full-catalog random Playlist (issue 01)
- [ ] Playlist label reflects active filter context

## Blocked by

- [03-shuffle-toggle-playlist-label](../issues/03-shuffle-toggle-playlist-label.md)
