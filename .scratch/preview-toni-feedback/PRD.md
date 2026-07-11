# Preview — Toni feedback (Jul 2026)

Consolidated decisions from review of preview site and Studio, following grill session.

## Scope

Phase 0 implementation only for layout unification. SPA migration (Phases 1–3) documented as a separate roadmap issue — not implemented now.

## Decisions

### Layout & navigation (#1, #7, #22)

- Deprecate secondary `nav` layout on About and Participants pages.
- Single bottom chrome: always the **player layout** (`bottom_bar.php` mode `player`).
- Remove **Reproductor** nav button.
- Remove **Random** (shuffle) button; **Reset** clears all filters and playlist.
- Reset shows visible label **"Reset"**; button group width keyed to longest label in the group.
- All non-transport buttons share uniform width; **Prev / Play / Next** remain distinct.
- From About (`?`), Reset returns to neutral player (`/preview/`).
- Horizontal rule above nav on secondary pages disappears automatically with layout unification (#22).

### Transport on About / Participants (Option C)

Transport cluster **always visible and functional**, even when no video is on screen. On About/Participants the centre button shows **Play** (not Pause) because nothing is playing on that page.

Persist playback context in **`sessionStorage`** while the user browses preview (playlist position, filters, participant mode, queue shape).

#### No prior player session (`sessionStorage` empty)

All transport buttons behave the same: open `/preview/` in **neutral ALL** state (paused poster, server shuffle — same as a fresh visit). This includes Play, Prev, Next, and Reset.

#### Prior player session exists

| Button | Behaviour |
|--------|-----------|
| **Reset** | Open `/preview/`; **clear all filters and participant context**; land on neutral ALL playlist (paused). |
| **Play** | Open `/preview/` on the **last video** the user was on (same playlist context, same position). |
| **Prev** | Open `/preview/` and load the **previous video** in the saved playlist (−1 step; same boundary rules as in-player). |
| **Next** | Open `/preview/` and load the **next video** in the saved playlist (+1 step; same boundary rules as in-player). |

Language query params must survive these navigations.

### Participants (#8–11, #5+#13)

- Remove **Participants** heading above the grid.
- Show **full thumbs** (no head crop).
- **Randomize grid order** on each visit to `/preview/participants`.
- One card per participant; if multiple videos, **alternate which thumb** is shown per visit (sessionStorage rotation).
- **Participant name always** on the Participants nav button whenever the current context is a single participant — including entry via Cities or Sign Language filters, not only `?participant=` URL.

### Playlist end behaviour (#12)

- Generalized rule: when **any** playlist finishes, pause on the **thumbnail of the first video in the current playlist** (filtered, participant, or ALL — first in the active playback sequence, not catalog order).
- Replaces participant redirect to `/preview/` and ALL reshuffle-on-end.

### Player polish (#17, #18, #21)

- No brief thumb flash when a video ends or when advancing.
- Replace gray letterbox flash with **loading indicator on Play/Pause** during video load.
- **Pointer cursor** over video kept — good UX signal for pause; explain to Toni.

### Language (#14–16, #19)

- **#14 resolved:** Remove Darija (`arq`) and Tunisian (`aeb`); use **international Arabic only** (`ar`). See issue 11.
- **RTL layout fix:** button order stays LTR-fixed even when UI language is RTL (`ar`).
- Remind Toni: web language and subtitle language share one control; one Arabic replaces the two dialects.
- **#15**: fix English persistence (`?lang=en` or equivalent) **and** localize gallery captions.
- **#16**: language change must not jump to a different video (interim: preserve playlist index in sessionStorage across reload).
- **#19**: oral/site languages sorted alphabetically in preview UI **and** Studio display order.

### About page (#20)

- Remove map section.
- Full-width responsive layout (as legacy site).
- Credits logos aligned as before.

### No code change — explain to Toni

| # | Topic | Explanation |
|---|-------|-------------|
| 6 | Tipologia vs Tipologies | Two intentional keys: `player.filter.typology` = picker label; `player.filter.all_typologies` = clear-filter dropdown row. Same pattern as Cities / Signs. |
| 21 | Hand cursor | Indicates video is clickable to pause. |

## Future work (not Phase 0)

See issue `10-spa-roadmap-phases-1-3.md`.
