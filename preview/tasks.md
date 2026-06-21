# DEAF.city Preview Site — Spec (3rd iteration)

Requirements from Antoni for the `/preview/` site before it replaces the live homepage.

**Stakeholder:** Antoni Abad  
**Audience:** Visitors browsing Videos, reading about the project  
**Source of truth:** `data/catalog.json` (see [CONTEXT.md](../CONTEXT.md))  
**Key components:** `preview/components/vimeo_caption_player.php`, `preview/lib/videos_catalog.php`

**North star:** the home page must read as an **intuitive playlist video player with no explanatory text on screen**. Minimalism is the priority, won through **restraint** (quiet, evenly-styled controls; brand green only on active state) — *not* by hiding controls behind menus. The "this is a playlist" cue comes from the **transport row affordances** (`⏮ ⏯ ⏭ 🔀`), not a caption.

---

## Design system

| Token | Value | Applies to |
|-------|-------|------------|
| Brand green | `rgb(0, 120, 0)` | Subtitles, accents, picker highlights, active state, links |
| Body font | Roboto | All UI text, subtitles, About page |
| Style | Minimalistic | Per project convention |

Subtitles match the **on-video subtitle scale** from the source Video — see §1.4 for the height-based sizing rule.

---

## Information architecture

The home page is **non-scrollable**: only the player and its three-row control chrome. All other content lives on dedicated routes.

| Route | Purpose |
|-------|---------|
| `/preview/` (home) | Full-viewport player + three-row chrome |
| `/preview/about` | Project text, realtime clock, gallery, credits |
| `/preview/participants` | Participant grid → loads participant Playlist on home |
| `/preview/tags` | Tag cloud → loads tag Playlist on home *(next sprint)* |
| `/preview/map` | Interactive Edition map *(next sprint)* |

Navigation sits in **row R3** of the player chrome (below transport and filters) — not a top nav bar. Only **other routes** are shown, and **only routes that have shipped** (no dead placeholders). Secondary pages use a **← go back to player** text link. Home never scrolls (PRD D9–D11).

---

## Player chrome — three control rows

| Row | Contents |
|-----|----------|
| **R1 — Transport** | Play/Pause · Prev · Next · Shuffle · Reset |
| **R2 — Filters** | **Sign language** · **Spoken Language** · **City/Edition** · **Typology** |
| **R3 — Navigation** | **About** · **Participants** (later **Tags**, **Map**) |

R2 holds three real playlist **filters** plus one **track selector** (Spoken Language). Spoken Language sits with the filters for tidiness but does **not** re-queue the Playlist (PRD D15–D16).

**Mobile (PRD D20):** everything-visible first — controls shrink, R2 may wrap to two lines, video gives up height, non-scroll preserved. Collapse R2 behind a single filter control **only on narrow viewports** as a last resort. Desktop always shows all three rows in full.

---

## 1. Player & Playlist

### 1.1 Playlist-first UX

The player is **Playlist-driven**. Playlist context is read from the **picker button faces** — each shows its selected value once chosen. There is **no separate Playlist-name label** (PRD D14); the unfiltered state shows nothing, since "All videos" is noise.

**Default Playlist (D2):** every visit loads **all visible Videos** in a **fresh random order**, equal probability per Video, re-shuffled per page load (not persisted).

**Cold-load poster (D12):** the server picks a **random** Video as the paused poster, and that Video **is the head of the shuffled queue**. The paused frame the visitor sees is exactly what plays when they press Play. Reload = a new random face.

**Requirements:**

- [ ] Picker button faces surface the active filter value; **no standalone Playlist-name label** (D14).
- [ ] Prev/Next advance within the **current** Playlist, respecting shuffle.
- [ ] Cold load: server-side random poster = shuffled queue head (D12).

**Acceptance criteria:**

- A visitor can tell what they are watching from the picker faces, without opening a picker.
- Reloading produces a new random poster + order; over many reloads every Video appears first with roughly equal frequency.
- Pressing Play continues the exact Video shown as the poster (no silent swap).

---

### 1.2 Category filter pickers (Sign language, City/Edition, Typology)

Three Playlist filters in R2, each a **button of the same visual class** opening a custom-styled dropdown/dropup (not a native `<select>`), brand green accents.

| Picker | Catalog field | Label source |
|--------|---------------|--------------|
| Sign language | `sign_language` | `data/studio-config.json` → `sign_languages[].label` |
| City/Edition | `edition` | `data/studio-config.json` → `editions[].label` |
| Typology | `typology` | `data/studio-config.json` → `typologies[].label` |

**Requirements:**

- [ ] Each picker shows the **currently selected value** on the button face once chosen; the generic label before selection.
- [ ] Dropdown is **custom-styled**, brand green.
- [ ] Pickers **populate only from values present in the visible Catalog** — empty facets are hidden (PRD D17). Sign language shows the used languages, not all 9 config labels; City shows the real Editions, not all 10.
- [ ] Pickers **compose (AND)** — Sign + City + Typology narrows to Videos matching all active filters.
- [ ] Changing a filter replaces the Playlist and starts from the first Video in the new list.

**Acceptance criteria:**

- Pickers are visually consistent with each other and with the R3 nav buttons.
- A visitor can never select a facet value that yields an empty Playlist.
- Selected value persists on the button face after the menu closes.

---

### 1.3 Spoken Language track selector

Renamed from "Captions/Subtitle language". A **track selector**, not a filter — it swaps the subtitle track of the **current** Video and does not re-queue (PRD D16).

**Requirements:**

- [ ] Placement: **R2**, alongside the category pickers (PRD D15–D16) — design question resolved.
- [ ] Same green-accent picker style as the category pickers (issue 07).
- [ ] Choice **sticks across Videos** when the next Video supports that language; falls back when not (existing sticky-track behaviour in `vimeo_caption_player.js`).

---

### 1.4 Captions layout & typography

**Implemented (issue 02).**

- [x] Caption box **above** the video, flush against the top edge — zero gap (D3).
- [x] Wrapper reserves **two lines** (fixed height); single-line cues on the **bottom** row (D5).
- [x] Roboto, `rgb(0, 120, 0)`.
- [x] Font size from **iframe height** (~4.8%, 14–38px); box width = visible video width, centered (D4, D6).

**Implementation:** `preview/components/vimeo_caption_player.{php,css}`, `preview/js/vimeo_caption_player.js`.

---

### 1.5 Transport controls (R1)

| Control | Behaviour |
|---------|-----------|
| Play/Pause | Manual + click hit-area (D8); **no autoplay** (§1.6) |
| Prev / Next | Playlist navigation within current Playlist |
| Shuffle | **On by default.** Off → catalog order from current Video onward; not persisted (D13) |
| Reset | Restarts **current Video** from t=0 (D1) |

**Shuffle UX (issue 03):**

- [ ] Shuffle reads clearly as a **toggle** with a visible active/inactive state (not an opaque icon).
- [ ] On: shuffled session order. Off: catalog (or filter) order from the current Video onward (D13).
- [ ] State **not persisted** — every visit is fresh shuffle-on.

---

### 1.6 Playback model (no autoplay)

**Requirements:**

- [ ] Remove the mute/unmute button.
- [ ] **No autoplay** on load — paused poster (D12); visitor presses Play.
- [ ] When a Video ends, advance to the next in the Playlist **only if** the visitor had started playback.

**End-of-playlist behaviour (PRD D19):**

- [ ] A **collection** (Participant/Tag) finishing → clear to a **fresh reshuffled unfiltered ALL Playlist, paused**.
- [ ] The **ALL** Playlist finishing → same (fresh reshuffle, paused).
- [ ] A **facet-filtered** Playlist finishing → **loop within the filter** (reshuffle, stay filtered), **paused on the new first Video**.

**Acceptance criteria:**

- Page load: paused poster, no sound, no autoplay.
- End-of-Video advances within the Playlist (respecting shuffle) once playback has started.
- No mute/unmute control visible.

---

### 1.7 Home page layout

**Implemented (issues 06, 09); R3 grows as routes ship.**

- [x] Home has **no scrollable content** (D9); chrome below the video.
- [x] R3 shows only **[About]** today — no redundant current-page button.
- [ ] **Participants** joins R3 when §2.2 ships (issue 08).

---

## 2. Participants

### 2.1 Catalog: Participant field

**Current state:** Participant is embedded in the Video `title` — two formats: `LIBRAS_São Paulo_Edinho_1 #HEARING CROWD` (bulk; participant = field 3) and `#SHEEP by Hamida` (new Studio; participant after `by`). No `participant` field yet (0/24).

**Requirements:**

- [ ] Add `participant` (string) to Catalog entries.
- [ ] Backfill via one-off script: field 3 of `SIGN_City_Name_N` for the bulk format, `by (Name)` fallback for the new format, then a **manual spot-check** for oddballs (casing like `sony`, surnames like `Riutort`/`Pegolino`). Review output before commit.
- [ ] New Videos get the field at Publication going forward.

---

### 2.2 Participants page

**Requirements:**

- [ ] **Participants** button in R3 (same class as **[About]**), navigates to `/preview/participants`.
- [ ] Page shows a **grid of thumbnails**: one representative Video per distinct Participant. **Built from scratch** — do not reuse the Mateo/legacy thumb grid.
- [ ] No hover overlay / metadata on thumbnails.
- [ ] Participant name under each thumbnail vs image-only — see §5 (only open design item).
- [ ] `/preview/participants` uses **← go back to player** (D11).

**Interaction (PRD D18):**

- [ ] Clicking a thumbnail returns to `/preview/` and loads a Playlist of **all Videos by that Participant** (a page pick is a **RESET** — clears any active R2 facets).
- [ ] While that Playlist is active, the **R3 Participants button shows the Participant name**; picking any R2 facet clears it back to facet mode.
- [ ] When the Participant Playlist finishes, clear to a fresh ALL Playlist, paused (D19).

**Acceptance criteria:**

- Grid lists every visible Participant exactly once (note Edinho ×2, Fabio ×2 → one thumb, two-Video Playlist).
- Selecting a Participant plays their Videos and labels the R3 control with their name.

---

## 3. About

**Implemented (issue 09).** About lives at `/preview/about`; home is player-only. Clock + gallery + about text + credits, Roboto + brand green, **← go back to player** back link (D11).

---

## 4. Next sprint

### 4.1 Tags page

Playlist selection by Tag — same **page-pick RESET** model as Participants (PRD D18–D19).

- [ ] Route `/preview/tags`; tag **cloud** (font size scales with frequency, à la blind.wiki).
- [ ] Clicking a tag returns to home with that Tag's Playlist; Tag name shows on the R3 Tags button while active.

### 4.2 Map page

- [ ] Route `/preview/map`; interactive map of Edition locations (reuse/adapt legacy `leaflet/`).
- [ ] Selecting a location loads the Edition Playlist.

---

## 5. Design tasks *(remaining)*

Most chrome/navigation design is now decided. **Only one item is still open.**

- [x] **Home navigation** — R3 buttons below transport/filters; non-scroll; **← go back to player** on secondary pages (D9–D11). *Shipped.*
- [x] **Spoken Language picker placement** — **R2**, with the category pickers (D15–D16). *Resolved.*
- [x] **Three-row chrome + mobile behaviour** — R1/R2/R3, everything-visible first, R2 collapse only on narrow viewports (D15, D20). *Resolved.*
- [ ] **Participants grid labels** — name under each thumbnail vs image-only. Blocks §2.2 polish only.

---

## Dependencies & notes

- **Catalog:** All Playlist logic reads visible entries from `data/catalog.json` — **all 24 now visible**.
- **Config labels:** Edition, Sign language, and Typology display names come from `data/studio-config.json`. **Pickers list only values present in the visible Catalog** (D17).
- **Player component:** Extend `vimeo_caption_player.php` / `.js` / `.css` — avoid forking playback logic.
- **Legacy reference:** `views/index.php`.

---

## Suggested implementation order

1. ~~Design system (Roboto, green, caption layout)~~ — **done** (02); About **done** (09)
2. ~~Playback model~~ — **done** (01), but **reopen** for server-side random poster = queue head (D12)
3. Shuffle toggle UX (03) + category pickers & filtering (04)
4. Spoken Language R2 styling (07)
5. ~~Home navigation + non-scroll + three-row chrome~~ — home/About **done** (06, 09); filter/nav rows land with 04/08
6. Participant catalog field + Participants page (08) *(grid labels after §5)*
7. ~~About page~~ — **done** (09)
8. *(Next sprint)* Tags page, Map page
