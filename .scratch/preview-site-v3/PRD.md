Status: ready-for-agent

# PRD — Preview site v3 (3rd iteration)

Parent spec: [preview/tasks.md](../../preview/tasks.md)

## Problem Statement

The `/preview/` site validates the next DEAF.city homepage with a Playlist-driven player and dedicated About / Participants routes, on a minimal full-viewport home. **Shipped so far:** player-only non-scrollable home, captions layout, manual playback, About at `/preview/about`. **Still open:** the three-row control chrome (transport / filters / navigation), category + spoken-language pickers, shuffle toggle, server-side random poster, Participant browsing, and the end-of-playlist behaviour.

The home page must read as an **intuitive playlist video player with no explanatory text on screen**. Minimalism is the priority, achieved through restraint (quiet, evenly-styled controls) rather than hiding controls behind menus.

## Solution

Implement the v3 spec as thin vertical slices. The player chrome is **three rows of controls**; filtering and playback follow one readable rule per behaviour so a visitor never sees hidden state. Data is now ready: all 24 Catalog videos are visible and Sign language / Edition / Typology are populated; Participant is the only field still to backfill.

## Player chrome — three control rows

The player chrome is three stacked rows below the video. No hidden menus on desktop; minimalism comes from visual restraint (muted weight, brand green only on active state).

| Row | Contents |
|-----|----------|
| **R1 — Transport** | Play/Pause · Prev · Next · Shuffle · Reset (as today) |
| **R2 — Filters** | **Sign language** · **Spoken Language** · **City/Edition** · **Typology** |
| **R3 — Navigation** | **About** · **Participants** (later **Tags**, **Map**) — only shipped routes, no dead placeholders |

R2 mixes three real playlist **filters** (Sign language, City/Edition, Typology) with one **track selector** (Spoken Language — formerly "Captions/Subtitles"). Spoken Language rides in R2 for tidiness but does **not** re-queue the Playlist; the rename does the disambiguation work.

## Decisions (locked)

| # | Decision |
|---|----------|
| D1 | Reset restarts the **current Video** from t=0 only |
| D2 | Default Playlist: **all visible Videos**, **fresh random order on every visit**, equal probability per Video |
| D3 | Captions sit **above** the video, flush against the video top edge |
| D4 | Caption font size scales from **rendered iframe height** (~4.8%, clamped 14–38px) |
| D5 | Caption box reserves **two lines** fixed height; single-line cues align to the **bottom** row |
| D6 | Caption box width matches **visible video width**, centered in the stack |
| D7 | Sign language filter: **no default filter** on cold visit — Playlist includes all visible Videos until the visitor picks |
| D8 | Click on the video area toggles play/pause (transparent hit-area overlay) |
| D9 | **Home page is non-scrollable** — `html`/`body` and player root use `overflow: hidden` |
| D10 | **Site navigation on home** — link buttons in player chrome row R3, below transport and filters; only **other routes** shown |
| D11 | **Secondary page navigation** — scrollable routes show one **← go back to player** text link, not a button row |
| D12 | **Cold-load poster** — server-side picks a **random** Video as the paused poster, and that Video **is the head of the shuffled queue**. The paused frame the visitor sees is exactly what plays on Play. Reload = a new random face. No autoplay. |
| D13 | **Shuffle** — **on by default**. Shuffle **off** → remaining queue follows **catalog order from the current Video onward** (current keeps playing). Toggle state **not persisted**; every visit is a fresh shuffle-on. |
| D14 | **No standalone Playlist-name label** — Playlist context is read from the **picker button faces** (each shows its selected value), not a separate label. The unfiltered state shows nothing ("All videos" is noise). |
| D15 | **Three control rows** (R1 transport / R2 filters / R3 navigation). No hidden menus on desktop; minimalism via restraint. |
| D16 | **Spoken Language** (renamed from Captions/Subtitles) is a **track selector**, placed in R2 with the filters but does not re-queue. |
| D17 | **Pickers populate only from values present in the visible Catalog** — empty facets are hidden entirely. A visitor can never pick a facet that lands on an empty Playlist. (Sign language shows the 4 used languages, not all 9 config labels; City shows the 5 real Editions, not all 10.) |
| D18 | **Filter composition** — R2 facets (Sign, City, Typology) **compose (AND)**. **Page picks (Participant, later Tag) are a clean RESET**: picking one clears R2 facets and sets the queue to that collection; picking any R2 facet afterward clears the page pick. One active context at a time. Active Participant name shows on the **R3 Participants button**. |
| D19 | **End-of-playlist** — a **collection** (Participant/Tag) finishing → clear to a **fresh reshuffled unfiltered ALL Playlist, paused**. The **ALL** Playlist finishing → same (fresh reshuffle, paused). A **facet-filtered** Playlist finishing → **loop within the filter** (reshuffle, stay filtered) and **pause on the new first Video**. |
| D20 | **Mobile** — everything-visible first: controls shrink, R2 may wrap to two lines, video gives up height; non-scroll preserved (D9). Collapse R2 behind one filter control **only on narrow viewports** as a last resort. Desktop always shows all three rows in full. |

## Out of Scope (this batch)

- Tags cloud page (§4.1 — next sprint)
- Edition map page (§4.2 — next sprint)
- Replacing the live homepage (Preview remains at `/preview/` until Antoni approves)

## Data readiness (done)

- All **24** Catalog videos are visible (`invisible` cleared).
- **Typology** backfilled across all 24 (acudits/anècdotes/malentesos/endevinalles/memòries, ~5 each) → the Typology filter is meaningful.
- **Sign language** (LSE/LSM/LIBRAS/GSS) and **Edition** (São Paulo/Mexico/València/Bilbao/Salamanca) already populated.
- **Participant** is the only field still empty — backfilled in issue 08.

## Issue index

| # | Issue | Status |
|---|-------|--------|
| 01 | Manual playback and default random Playlist | **reopen** — poster must be server-side random = queue head (D12) |
| 02 | Caption layout and brand typography | **done** |
| 03 | Shuffle toggle UX | AFK — label dropped (D14); now shuffle toggle only |
| 04 | Category filter pickers (Sign, City, Typology) | AFK |
| 05 | Design player chrome and navigation | HITL — subtitle placement resolved (R2); only grid labels open |
| 06 | Home layout and site navigation | **done** |
| 07 | Spoken Language track selector (R2 styling) | AFK — placement resolved (R2); style as R2 picker |
| 08 | Participant catalog, grid page, and Playlist handoff | AFK |
| 09 | About page with legacy clock, gallery, and credits | **done** |

## Issue 01 — implementation notes (2026-06-21)

Playback slice shipped, but the poster behaviour now needs correction (D12):

| Topic | Original assumption | Shipped behaviour | v3 target (D12) |
|-------|---------------------|-------------------|-----------------|
| Cold-load poster | Shuffled order from first paint | PHP iframe renders catalog-order Video #0; JS may swap on SDK attach | **Server-side random** Video as poster, and it **is** the shuffled queue head |
| Full-catalog Playlist | All visible Videos on load | Sign language defaulted to first language | **All sign languages** (D7) |
| Video click | Play via transport only | Hit-area overlay toggles play/pause (D8) | keep |
| Shuffle UX | On by default | Toggle in markup/JS | obvious toggle + off-behaviour (issue 03, D13) |

## Issue 06 & 09 — implementation notes (2026-06-21)

Home layout and About route shipped on `master`.

| Topic | Shipped behaviour |
|-------|-------------------|
| Home page | Player-only; prototype switcher + inline About removed from `preview/index.php` |
| Home scroll | Non-scrollable (D9) |
| Home nav | Single **About** button in R3 (D10); no top nav bar |
| About route | `/preview/about/` — clock, gallery, about text, trio, credits; Roboto + brand green |
| About nav | Single text link **← go back to player** (D11) |
| Participants nav | Joins R3 when issue 08 ships |

**Key files:** `preview/index.php`, `preview/about/index.php`, `preview/components/site_nav.php`, `preview/components/vimeo_caption_player.php`, `preview/css/site-nav.css`, `preview/css/about-page.css`, `preview/tests/home_page_test.php`, `preview/tests/about_page_test.php`
