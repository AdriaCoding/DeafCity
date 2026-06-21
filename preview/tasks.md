# DEAF.city Preview Site — Spec (3rd iteration)

Requirements from Antoni for the `/preview/` site before it replaces the live homepage.

**Stakeholder:** Antoni Abad  
**Audience:** Visitors browsing Videos, reading about the project  
**Source of truth:** `data/catalog.json` (see [CONTEXT.md](../CONTEXT.md))  
**Key components:** `preview/components/vimeo_caption_player.php`, `preview/lib/videos_catalog.php`

---

## Design system

| Token | Value | Applies to |
|-------|-------|------------|
| Brand green | `rgb(0, 120, 0)` | Subtitles, accents, picker highlights, links |
| Body font | Roboto | All UI text, subtitles, About page |
| Style | Minimalistic | Per project convention |

Subtitles should match the **on-video subtitle scale** from the source Video — see §1.4 for the height-based sizing rule (not viewport width or stack width).

---

## Information architecture

The home page is **non-scrollable**: only the player and site navigation. All other content lives on dedicated routes.

| Route | Purpose |
|-------|---------|
| `/preview/` (home) | Full-viewport player + navigation |
| `/preview/about` | Project text, realtime clock, gallery, credits |
| `/preview/participants` | Participant grid → loads participant Playlist on home |
| `/preview/tags` | Tag cloud → loads tag Playlist on home *(next sprint)* |
| `/preview/map` | Interactive Edition map *(next sprint)* |

Navigation sits **below the video controls** as text buttons (Player, About, …) — not a top nav bar. The home viewport does not scroll; see PRD D9–D10.

---

## 1. Player & Playlist

### 1.1 Playlist-first UX

The player is **Playlist-driven**, not single-video-first. The UI must make the active Playlist obvious at all times.

**Current state:** The player loads the full catalog as one flat Playlist (`vpc_vimeo_playlist_all_from_catalog`). Transport controls (prev/next/shuffle) exist but Playlist context is implicit.

**Default Playlist (decided):** On every visit, load **all visible Videos** from the Catalog, in a **fresh random order**. Each Video has equal probability of appearing at any position (including first). Re-shuffle on each page load — not persisted across visits.

**Requirements:**

- [ ] Surface the active Playlist name in the player chrome (not just transport icons).
- [ ] When a category filter is applied, the displayed name is the **selected category value** (e.g. `LSE Spanish Sign Language`, `2023 Bilbao`, `Joke`) — not a generic picker label like "Sign language".
- [ ] Prev/Next advance within the **current filtered Playlist**, respecting shuffle state.
- [ ] On first load with no filters active, show the full-catalog Playlist in random order (see above).

**Acceptance criteria:**

- A visitor can tell what they are watching (which Playlist / filter) without opening a picker.
- Changing category replaces the Playlist and resets playback to the first Video in the new list (unless specified otherwise for participant/tag selection — see §2).
- Reloading the home page produces a new random order; over many reloads every Video appears first with roughly equal frequency.

---

### 1.2 Category pickers (Sign language, Edition, Typology)

Three Playlist filters, each rendered as a **button of the same visual class** that opens a dropdown or dropup.

| Picker | Catalog field | Label source |
|--------|---------------|--------------|
| Sign language | `sign_language` | `data/studio-config.json` → `sign_languages[].label` |
| Edition (ciutat) | `edition` | `data/studio-config.json` → `editions[].label` |
| Typology | `typology` | `data/studio-config.json` → `typologies[].label` |

**Requirements:**

- [ ] Each picker shows the **currently selected value** on the button face once chosen.
- [ ] Dropdown/dropup is **custom-styled** (not a native `<select>`), visually distinct, using brand green.
- [ ] Pickers compose: selecting Sign language + Edition + Typology narrows the Playlist to Videos matching all active filters.
- [ ] With no picker active, default is the full-catalog Playlist (§1.1) — pickers show their generic label until the visitor selects a value.

**Acceptance criteria:**

- Pickers are visually consistent with each other and with the Participants button (§2).
- Selected category persists in the button label after the menu closes.

---

### 1.3 Subtitle language picker

**Requirements:**

- [ ] Placement per §5 Design tasks.
- [ ] Implement chosen placement using the same green-accent picker style as category pickers.
- [ ] Subtitle language choice sticks across Videos in the Playlist when the next Video supports that language (existing sticky-track behaviour in `vimeo_caption_player.js`).

---

### 1.4 Captions layout & typography

**Implemented (issue 02).** Corrections applied during implementation — the draft spec below had several misalignments with the desired UX.

**Spec corrections (was wrong in draft):**

| Draft said | Actual requirement |
|------------|-------------------|
| Captions attached to the video **bottom** edge on narrow viewports | Captions sit **above** the video, flush against its **top** edge |
| Size matched via viewport/`clamp()` or stack width | Font size scales from **rendered iframe height** (~4.8%, 14–38px) — stack width oversizes on wide screens where the mobile aspect-ratio crop (`1.2 / 1`) is not applied |
| Single-line text centered in the reserved box | Single-line cues use the **bottom row** of the two-line reserve (`flex-end`) so text sits closest to the video |

**Requirements:**

- [x] Caption box **above** the video, flush against the video top edge — zero gap, especially on mobile.
- [x] Caption wrapper reserves space for **two lines** without shifting video or controls (fixed height).
- [x] Single-line cues render on the **bottom line** of the reserved area.
- [x] Caption text: Roboto (loaded on `/preview/`), `rgb(0, 120, 0)`.
- [x] Font size from **iframe height** after layout; caption box width matches visible video width (iframe, capped at shell), centered in `.video-stack`.

**Acceptance criteria:**

- [x] Captions appear directly above the video with no visible gap.
- [x] A two-line cue does not cause the player chrome to jump.
- [x] Wide-screen captions are not oversized relative to the visible video frame.

**Implementation:** `preview/components/vimeo_caption_player.{php,css}`, `preview/js/vimeo_caption_player.js` (`syncCaptionTypography`, `--vpc-caption-font-size`, `--vpc-caption-width`).

---

### 1.5 Transport controls

| Control | Current behaviour | Required change |
|---------|-------------------|-----------------|
| Play/Pause | Manual + click hit-area | Keep; **remove autoplay-on-load** (see §1.6) |
| Prev / Next | Playlist navigation | Keep |
| Shuffle | Toggles random order within filtered Playlist | Improve UX so toggle state is obvious |
| Reset | Restarts **current Video** from t=0 | **Decided** — keep current-Video-only behaviour for now |

**Shuffle UX:**

- [ ] Default on visit: shuffle **on** for the full-catalog Playlist (§1.1).
- [ ] Make shuffle clearly a **toggle** (on/off random order within the current Playlist), not an opaque icon.
- [ ] Visual active state when shuffle is enabled (e.g. pressed styling, label, or tooltip).
- [ ] When shuffle is off, Prev/Next follow catalog order (or filter order); when on, follow a shuffled sequence for the session.

---

### 1.6 Playback model (no autoplay)

**Current state:** Vimeo embed defaults to `autoplay=1`, `muted=1`; unmute badge (`.vpc-sound-badge`) overlays the video.

**Requirements:**

- [ ] Remove the mute/unmute button.
- [ ] Disable autoplay on initial load — visitor presses Play, as on the legacy homepage.
- [ ] Define Playlist autoplay behaviour: when a Video ends, advance to the next Video in the Playlist **only if** the visitor had started playback (do not auto-start the first Video on page load).

**Acceptance criteria:**

- Page load: video paused, no sound, no autoplay.
- After visitor presses Play: end-of-video advances within Playlist (respecting shuffle).
- No mute/unmute control visible.

---

### 1.7 Home page layout

- [ ] Home page has **no scrollable content** — player fills the viewport; site nav buttons sit below transport and filters (PRD D9–D10).
- [ ] ~~Implement navigation pattern once designed~~ — home nav pattern locked (PRD D10); Participants button added when §2.2 ships.

---

## 2. Participants

### 2.1 Catalog: Participant field

**Current state:** Participant name is embedded in the Video `title` (e.g. `LIBRAS_São Paulo_Edinho_1 #HEARING CROWD` → participant ≈ `Edinho`). No dedicated catalog field yet.

**Requirements:**

- [ ] Add `participant` (string) to catalog entries.
- [ ] Backfill from existing titles via a one-off script or Studio migration; new Videos get the field at Publication.
- [ ] Studio: expose Participant on the video edit form (optional for this sprint if backfill covers existing data).

---

### 2.2 Participants page

**Requirements:**

- [ ] Player chrome includes a **Participants** button — same visual class as category pickers.
- [ ] Button navigates to `/preview/participants`.
- [ ] Page shows a **grid of thumbnails**: one representative Video per distinct Participant (pick first Video or a designated thumbnail per person).
- [ ] Grid is **built from scratch** — do not reuse the Mateo/legacy thumb grid UI.
- [ ] No hover overlay / metadata on thumbnails.
- [ ] Whether to show Participant name under each thumbnail — see §5 Design tasks.

**Interaction:**

- [ ] Clicking a thumbnail returns to home (`/preview/`) and loads a Playlist of **all Videos by that Participant** (typically one or two).
- [ ] While that Playlist is active, the Participants button shows the **Participant name** instead of the generic label.

**Acceptance criteria:**

- Grid lists every visible Participant exactly once.
- Selecting a Participant plays their Videos and labels the Participants control with their name.

---

## 3. About

**Current state:** Preview embeds About text inline on the home page (prototype variants A/B/C). Legacy home keeps About on a separate anchor (`#about`, `#clock`) with clock iframe, gallery, and credits.

**Requirements:**

- [ ] Move About off the home page to `/preview/about` — home stays player-only.
- [ ] Reuse from legacy site:
  - [ ] Realtime clock (`realtime/index.html` iframe)
  - [ ] Gallery (`views/_gallery.php` or equivalent assets)
  - [ ] About text (already partially copied in `preview/index.php`)
  - [ ] Credits / trio video / sponsor logos
- [ ] Apply Roboto + `rgb(0, 120, 0)` to About page.

**Acceptance criteria:**

- Home has no About scroll region.
- About page matches legacy content scope (clock + gallery + text + credits), with updated typography and colour.

---

## 4. Next sprint

### 4.1 Tags page

Playlist selection by Tag — same interaction model as Participants.

- [ ] Route: `/preview/tags`
- [ ] Display: tag **cloud** inspired by [blind.wiki tags](https://blind.wiki/tags/) — font size scales with usage frequency.
- [ ] Clicking a tag returns to home with a Playlist of Videos carrying that Tag.

### 4.2 Map page

- [ ] Route: `/preview/map`
- [ ] Interactive map of DEAF.city Edition locations (reuse/adapt legacy `leaflet/` map if available).
- [ ] Selecting a location filters or loads the Edition Playlist.

---

## Decisions

| # | Decision | Detail |
|---|----------|--------|
| D1 | **Reset button** | Restarts the **current Video** from t=0 only. Restarting the entire Playlist from the first Video is out of scope for now. |
| D2 | **Default Playlist** | All visible Videos, **random order on every visit**. Each Video has equal chance of appearing at any position. Fresh shuffle per page load. |

---

## 5. Design tasks *(open — standalone sprint item)*

Visual and layout decisions blocked on design work. Implementation tasks above reference this section where needed.

- [x] **Home navigation** — **decided:** text buttons below player transport + language filters; no top nav bar; home non-scrollable (PRD D9–D10). Participants / Tags / Map buttons join the same row as those routes ship.
- [ ] **Subtitle language picker placement** — where it sits in the player chrome (transport row, filter row, near captions, etc.). Blocks §1.3 implementation.
- [ ] **Participants grid labels** — show Participant name under each thumbnail, or image-only. Blocks §2.2 polish.

Deliverable: mockups or annotated wireframes for the player chrome + navigation, sufficient to implement without further product questions.

---

## Dependencies & notes

- **Catalog:** All Playlist logic reads visible entries from `data/catalog.json` (`invisible: true` excluded).
- **Config labels:** Edition and Sign language display names come from `data/studio-config.json`.
- **Player component:** Extend `vimeo_caption_player.php` / `.js` / `.css` — avoid forking playback logic.
- **Legacy reference:** `views/index.php` (player, clock, gallery, map, navigation).
- **Prototype cleanup:** Remove variant switcher (A/B/C) and inline About blocks from `preview/index.php` when implementing final IA.

---

## Suggested implementation order

1. Design system (Roboto, green, caption layout) — **caption layout done (issue 02)**; Roboto/green apply to remaining pages  
2. Playback model (no autoplay, remove mute badge, default random all-Videos Playlist, Playlist advance rules)  
3. Category pickers + Playlist filtering  
4. **Design tasks (§5)** — can run in parallel with 1–3  
5. Home navigation + non-scroll layout *(after §5)*  
6. Participant catalog field + Participants page *(grid labels after §5)*  
7. About page (separate route, legacy content)  
8. *(Next sprint)* Tags page, Map page
