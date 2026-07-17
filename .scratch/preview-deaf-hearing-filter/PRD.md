Status: implemented

# PRD — Preview: DEAF+HEARING tag filter

Builds on [preview-filters-rework PRD](../preview-filters-rework/PRD.md) (D1′, D17′, D18′, D21, D22) and the existing player chrome / playback-session model.

> **Rewrite note (v2):** This revision closes the gaps raised in review — `cold load` vs `session restore` boundary (DH14), empty-set behavior for the primary toggle (DH15), a generic single-slot tag facet instead of a bespoke boolean (DH16), toggle accessibility (DH13), explicit rationale + reversibility on the participant/R2 asymmetries (DH5/DH10), required integration coverage for the risky handoff seams, a success measure, and a worked given/when/then acceptance fixture.

## Problem Statement

The Preview site chrome already renders a **DEAF+HEARING** button (maketa layout), but it is a disabled stub that always *looks* active and does nothing. Visitors cannot browse the Videos Producers have tagged for deaf–hearing crossover humour. The Catalog carries the Tag `DEAF&HEARING` on a small set of Videos (today: 8 of 66), but the player playlist payload never receives Tags, so the client has no way to filter by them.

## Solution

Wire **DEAF+HEARING** as a real chrome control: a user-pinned filter that narrows the Playlist to Videos whose Tags include `DEAF&HEARING`. Internally it is **one slot of the existing filter state** (`tag: null | string`), not a bespoke boolean — the chrome simply pins the single value `"DEAF&HEARING"`. Default inactive (neutral). When on, the button is green (same "you pinned this" semantics as fixed R2 filters / active Participants). On the player it composes with Sign language / Edition / Typology via AND and cascading dropups; it is mutually exclusive with Participants collection mode; it toggles off on a second click and clears on Reset; it persists in the playback session as a **handoff**, not a sticky preference; from About / Participants it force-activates a pure tagged Playlist and plays.

## User Stories

1. As a Preview visitor on the home player, I want DEAF+HEARING to start inactive, so that green always means I chose something this visit.
2. As a Preview visitor, I want to click DEAF+HEARING to filter to Videos tagged `DEAF&HEARING`, so that I can browse that curated set.
3. As a Preview visitor, I want the DEAF+HEARING button to turn green (and expose a pressed state to assistive tech) when the filter is on, so that I can see my pinned context whether or not I can perceive colour.
4. As a Preview visitor, I want to click DEAF+HEARING again to clear that filter, so that I can return to the broader Playlist without Reset.
5. As a Preview visitor with DEAF+HEARING on, I want Reset to clear it along with all other pins, so that one control returns me to neutral home.
6. As a Preview visitor turning DEAF+HEARING on while the current Video already has the Tag (and still satisfies my R2 pins), I want playback to continue on that Video, so that I am not interrupted.
7. As a Preview visitor turning DEAF+HEARING on while the current Video is not in the new set, I want the player to jump into the freshly shuffled tagged Playlist and auto-play (after gesture unlock), so that I immediately see the filtered set.
8. As a Preview visitor with DEAF+HEARING on, I want to pin Sign language / Edition / Typology and get the AND intersection, so that I can narrow within the tagged set.
9. As a Preview visitor with DEAF+HEARING on, I want R2 dropups to list only values present among tagged Videos (and other fixed facets), so that I cannot pick a dead-end option when the cascade can prevent it.
10. As a Preview visitor who pins an R2 value that would make the intersection empty, I want that R2 pin reverted while DEAF+HEARING stays on, so that my explicit toggle is not silently dropped.
11. As a Preview visitor with a participant Playlist active, I want turning DEAF+HEARING on to clear participant mode, so that I get one clear Playlist story.
12. As a Preview visitor with DEAF+HEARING on, I want choosing a Participant to clear DEAF+HEARING, so that participant mode stays mutually exclusive with this filter.
13. As a Preview visitor on About, I want clicking DEAF+HEARING to take me to the player, force the tagged filter on, clear participant mode and any R2 pins, and play the pure tagged Playlist, so that the button means "play that set" from secondary screens.
14. As a Preview visitor on Participants, I want the same force-ON handoff as on About, so that behaviour is consistent across secondary chrome.
15. As a Preview visitor who turned DEAF+HEARING on then left for About, I want Play / Prev / Next transport to restore with DEAF+HEARING still on, so that my session round-trips honestly.
16. As a Preview visitor on About / Participants with DEAF+HEARING still in session, I want the button to show active (and pressed) there too, so that chrome mirrors session state.
17. As a Preview visitor turning DEAF+HEARING off, I want the current Video to keep playing (turning a filter off only widens the set, so it always still matches), so that clearing never interrupts me.
18. As a Preview visitor with only DEAF+HEARING on (no R2 pins), I want prev/next and end-of-video advance to walk the shuffled tagged Playlist, so that transport matches other filtered queues.
19. As a Preview visitor, I want the chrome label to remain **DEAF+HEARING** even though the Catalog Tag is **DEAF&HEARING**, so that branding stays readable while storage stays canonical.
20. As a Producer, I want existing inconsistent Tag spellings already normalized to `DEAF&HEARING` in the Catalog, so that the filter matches what I tag going forward.
21. As a developer, I want Tags included on playlist items fed to the player, so that client filtering does not invent a second metadata source.
22. As a developer, I want playlist-filter logic (including tag membership) testable in isolation from the Vimeo player DOM, so that AFK agents can verify behaviour without a browser.
23. As a developer, I want the nav-intent handoff and session round-trip covered by a bootstrap-level test, so that the two integration seams most likely to regress are not left to manual QA.
24. As a Preview visitor, I do not need a shareable URL for this filter in the first slice, so that session + button shipping stays small.
25. As a Preview visitor after gesture unlock, I want activating DEAF+HEARING from the player to auto-play when a jump is required, consistent with other filter fixes.
26. As a Preview visitor on a cold load (a plain home URL with no pending handoff), I want DEAF+HEARING never pre-activated even if a stale flag sits in session, so that home is unfiltered until I choose.
27. As a Producer or visitor, I want the control to disable itself when zero Videos carry the Tag, so that the button is never a live dead-end after a data slip.

## Implementation Decisions

### Product decisions (locked in grill)

| # | Decision |
|---|----------|
| **DH1** | Catalog Tag targeted by the filter is exactly `DEAF&HEARING` (no `#`, no `+`, no `%`, no HTML entities). UI chrome label remains **DEAF+HEARING** (display ≠ storage). |
| **DH2** | Default state is **inactive**. Green / `is-active` / `aria-pressed="true"` only when the filter is on. |
| **DH3** | On the player, the control is a **toggle**: click on → filter on; click again → filter off. **Reset** also clears it (with all other pins). |
| **DH4** | On the player, DEAF+HEARING **ANDs** with fixed R2 facets (Sign language, Edition, Typology). Existing R2 pins are kept when turning it on (see DH15 for the empty-intersection case). |
| **DH5** | DEAF+HEARING and **Participants** collection mode are **mutually exclusive** (either direction clears the other). **Why:** the product wants one legible Playlist story per screen; a "this participant's crossover videos" intersection is deferred to the future multi-Tag browser, not built here. **Reversible:** this is a product choice, not a technical constraint — modeling the tag as a facet (DH16) leaves the door open to compose it with collection mode later without rework. |
| **DH6** | Turn-**on** playback follows **keep-if-matches** (D22): keep the current Video if it still satisfies the new filter set; otherwise jump to the rebuilt shuffled queue head and auto-play post-gesture. |
| **DH6b** | Turn-**off** never jumps: widening the set means the current Video always still matches, so playback continues uninterrupted. (Do not rebuild-and-jump on the off path.) Reset remains the pause exception. |
| **DH7** | Empty AND after an **R2** change: **revert that R2 pin**; keep DEAF+HEARING on (same silent-revert pattern as today's empty R2 compose). |
| **DH8** | Cascading dropups (D17′) treat DEAF+HEARING as an additional constraint: options for each R2 facet come only from Videos that have the Tag when the toggle is on (plus other fixed facets). |
| **DH9** | Playback session **persists** the tag flag and restores it **only via the nav-intent handoff** (see DH14). Secondary chrome shows the button **active + pressed** when session says on. |
| **DH10** | Clicking DEAF+HEARING on About / Participants **force-ON**: navigate to player, clear participant mode, **clear all R2 pins**, activate DEAF+HEARING, rebuild the pure tagged Playlist, auto-play (gesture via the click). Not a toggle from secondary. **Why clear R2:** from a secondary screen the button promises "the deaf+hearing Playlist," not "intersect with a possibly-stale session"; a pure set is always non-empty when the control is enabled (DH27). **Known tradeoff:** a visitor who had R2 pins in session loses them on this path; accepted for the first slice, flagged for post-launch validation. |
| **DH11** | Secondary force-ON uses a one-shot **nav-intent** handoff (intent value `deaf-hearing`, same channel/family as `play` / `prev` / `next` / `reset`), consumed exactly once on player bootstrap. Not a URL query param in this slice. |
| **DH12** | No shareable / deep-link URL param in this PRD (can mirror `?participant=` later). |
| **DH13** | **Accessibility:** the control is a toggle button exposing `aria-pressed` (`false` default, `true` when on). Its accessible name is a real phrase (e.g. `player.filter.deaf_hearing` → "Deaf and hearing crossover videos"), **not** the `+`/`&` glyph string. State changes are perceivable without colour (pressed state + the shared active styling). Removing the stub's `disabled` must not leave the control unlabelled. |
| **DH14** | **Cold load vs session restore (crisp rule):** "Cold load" = the home player booting with **no pending nav-intent** in the session channel. On cold load the tag flag is **inactive regardless of any stored session flag** (DH2/Story 26 win). The stored flag is applied **only** when the home player boots by consuming a nav-intent (transport return `play`/`prev`/`next`, or the `deaf-hearing` force-ON intent). Session is a per-visit handoff, not a sticky preference. Scope: this rule governs the **tag flag**; existing R2 session-restore scope is unchanged. |
| **DH15** | **Empty-set for the primary toggle.** Two layers: (a) **Chrome guard (DH27):** if zero Videos in the visible Catalog carry the Tag, the control is rendered disabled/inactive at SSR and secondary force-ON is suppressed — so the *pure* tagged set is never empty when the control is live. (b) **Runtime toggle-on with R2 pins whose AND is empty:** the explicit toggle wins — keep DEAF+HEARING **on** and **clear all R2 pins**, falling back to the (guaranteed non-empty) pure tagged set, then keep-if-matches from there. This mirrors "explicit toggle not silently dropped" (DH7 spirit) without ambiguous per-facet revert. `planFilterPlaylistRebuild` returning `null` is the empty signal to act on. |
| **DH16** | **Generic single-slot tag facet, not a boolean.** Filter state gains `tag: null | string` alongside the existing nullable R2 facets. Chrome exposes exactly one button that pins `"DEAF&HEARING"`. **Match semantics differ from R2:** R2 facets are single-valued equality (`itemFacetValue === value`); the tag facet is **array membership** (`item.tags` includes the pinned value). Reuse `recomputeFilteredMasterIndices` / `videoMatchesFilterState` / `buildCascadingFilterOptions` / session snapshot with an added membership branch. Rationale: participant mutual-exclusion and reset-clears fall out for free (both already operate on `filterState`); a second crossover Tag later is configuration, not re-plumbing. |
| **DH17** | **Session schema version bump.** Adding `tag` to the persisted `filterState` bumps the snapshot `v` from `1` to `2`. A `v:1` snapshot restores with `tag: null` (no crash, no phantom activation). |

### Modules

1. **Catalog → player playlist payload**
   Extend the playlist-item shape emitted for the Preview player so each item includes the Video's `tags` array from the Catalog (enough to test membership of `DEAF&HEARING`). Single source of truth remains the Catalog. Emit the same on the secondary-chrome catalog payload used to compute the SSR guard (DH27).

2. **Playlist / filter logic (deep module — pure functions)**
   Extend `VpcFilterState` with `tag: null | string`. DOM-free responsibilities:
   - `recomputeFilteredMasterIndices` / `videoMatchesFilterState`: add a **membership** branch for `tag` (array contains), leaving R2 equality intact.
   - Cascading option builders constrained by the tag membership when `tag` is pinned.
   - Toggle on/off plans via `planFilterPlaylistRebuild` (keep-if-matches on; no-jump on off, DH6b).
   - Empty-intersection handling per DH15b (clear R2, keep tag) when rebuild returns `null`.
   - Mutual exclusion planning with participant mode (reuse `shouldClearCollectionOnFilterFix`; tag pin is a non-null facet fix → clears participant).
   - Session snapshot/restore including `tag` and the `v:1→v:2` migration (DH17).
   - Nav-intent planning for secondary force-ON: clear R2 + participant, set `tag`, rebuild pure set, autoplay.

3. **Home player chrome wiring**
   Enable the control; wire click → toggle; sync active/green + `aria-pressed`; integrate with existing filter-change / reset / participant paths; apply DH14 cold-load rule at bootstrap.

4. **Secondary chrome (About / Participants)**
   Make DEAF+HEARING clickable: set `deaf-hearing` nav-intent, navigate to player; reflect active + pressed from session when present (DH9/DH16); force-ON per DH10–DH11 (not local toggle); honour the DH27 guard.

5. **Chrome markup defaults**
   Remove the `disabled` + always-`is-active` stub so SSR/default matches **inactive** unless the DH27 guard disables it or a consumed intent/session (via DH14) says on. Add `aria-pressed` and the real accessible name (DH13).

### Interface shape (decision-level)

Filter state gains a nullable tag slot alongside the existing nullable R2 facets:

```text
filterState = {
  sign_language: null | string,   // single-valued equality
  edition:       null | string,   // single-valued equality
  typology:      null | string,   // single-valued equality
  tag:           null | string    // ARRAY MEMBERSHIP (item.tags includes value)
}
```

Tag match rule: an item is included when `filterState.tag` is `null`, **or** when the item's `tags` array **contains** the pinned value (membership — the item may carry other tags too; it need not be the sole tag).

Chrome binding: the single DEAF+HEARING button pins `filterState.tag = "DEAF&HEARING"` (on) or `null` (off). No other tag value is reachable from the UI in this slice.

Nav intent for secondary force-ON: a dedicated `deaf-hearing` value in the existing one-shot nav-intent channel (same pattern as `play` / `prev` / `next` / `reset`), consumed once on player bootstrap; consuming it is what authorizes applying the session flag under DH14.

### Data note (already done in discovery)

Catalog Tag variants (`#DEAF&HEARING`, `DEAF%HEARING`, `DEAF&amp;HEARING`, duplicates) were normalized to `DEAF&HEARING` on the affected Videos before this PRD (verified: 8 of 66 Videos carry it, stored in each Video's `tags` array). Implementation assumes the canonical string only; no runtime fuzzy matching.

## Testing Decisions

**What makes a good test here:** assert externally visible playlist/filter behaviour (which indices remain, whether participant mode clears, session flag round-trip, keep-vs-jump plans, cascade option sets, empty-revert). Do not assert DOM class names as the sole source of truth for logic; chrome/SSR tests may assert presence, enabled/disabled state, `aria-pressed`, and default-inactive markup.

**Modules to test (required):**

- **Playlist / filter logic** — unit tests co-located with the existing playlist-logic suite:
  - membership recompute with `tag` (contains, not equals; item with extra tags still matches);
  - AND of `tag` with each R2 facet;
  - cascade option sets constrained by the Tag;
  - toggle-on keep-if-matches vs jump; toggle-off never jumps (DH6b);
  - empty-intersection on toggle-on clears R2, keeps tag, lands on pure set (DH15b);
  - mutual exclusion both directions (tag-on clears participant; participant-pick clears tag);
  - session snapshot includes `tag`; `v:1` snapshot restores `tag:null` (DH17);
  - secondary force-ON plan clears R2 + participant and sets tag.
- **Integration / bootstrap seams (required — closes the highest-risk gap):** a DOM-light (jsdom or scripted) test that asserts (a) the `deaf-hearing` nav-intent is **consumed exactly once** on bootstrap (a reload does not re-force it), and (b) DH14 — a stored on-flag is applied when booting via an intent but **ignored on a bare cold load**.
- **Home chrome / SSR** — update existing home-page assertions that expect a disabled always-active placeholder; expect an enabled control that is **inactive by default**, carries `aria-pressed`, and is **disabled when the catalog tagged-count is zero** (DH27).

**Prior art:** Preview playlist-logic test suite and home-page chrome tests for filter pickers / Participants active state / reset.

**Optional / not required in first slice:** full browser E2E against Vimeo; Studio Tag-input changes.

### Acceptance fixture (worked example)

Deterministic fixture (test data, not the live catalog): 6 master items, `tags`/facets as shown. `T` = has `DEAF&HEARING`.

| idx | tags | sign_language | edition |
|----|------|---------------|---------|
| 0 | `[T]` | LSC | BCN |
| 1 | `[]` | LSC | BCN |
| 2 | `[T, IDEA!]` | LSA | ALG |
| 3 | `[T]` | LSA | ALG |
| 4 | `[]` | LSC | ALG |
| 5 | `[T]` | LSC | BCN |

- **Given** neutral state on the player, current index 1 · **When** DEAF+HEARING on · **Then** filtered master set = `{0,2,3,5}`; index 1 not in set → **jump** to shuffled head of `{0,2,3,5}`, autoplay; `aria-pressed=true`. (Stories 2,3,7)
- **Given** DEAF+HEARING on, current index 0 · **When** pin Sign language = LSC · **Then** set = `{0,5}`; 0 still matches → **keep** index 0 playing. (Story 8, DH6)
- **Given** DEAF+HEARING on, current index 0 · **When** pin Edition = ALG · **Then** AND = `{}` → **revert Edition pin**, tag stays on, set back to `{0,2,3,5}`. (Story 10, DH7)
- **Given** neutral, R2 pinned Sign language = LSA (set `{2,3,4}`), current index 4 · **When** DEAF+HEARING on · **Then** tag∧LSA = `{2,3}` (non-empty) → keep tag + LSA, jump into `{2,3}`. *(If instead the R2 pin made the AND empty, DH15b: clear R2, land on pure `{0,2,3,5}`.)*
- **Given** DEAF+HEARING on in session, on About · **When** press Play (transport intent) · **Then** player boots, consumes intent, applies flag → tag on. **When** instead the bare home URL is opened (no intent) · **Then** tag **inactive** (DH14, Story 26).
- **Given** a fixture with zero `T` items · **Then** the control renders **disabled/inactive** and secondary force-ON is suppressed (DH27).

## Success measure

Lightweight, no new analytics infra required for the slice: (1) the 8 tagged Videos are reachable and playable via the toggle from home and via force-ON from both secondary screens; (2) the required logic + bootstrap tests are green in CI; (3) manual smoke: green/pressed state is announced by VoiceOver/NVDA. A usage metric (toggle activations) is **out of scope** here and can ride the future Tags-browser work.

## Out of Scope

- Shareable URL / `?deaf_hearing=1` deep link
- General Tags collection page or multi-Tag browser (still future; this is one dedicated chrome toggle)
- Composing DEAF+HEARING with Participants collection mode (deferred with the multi-Tag browser; DH5 keeps the data model open to it)
- Renaming the Catalog Tag to match the UI label (or vice versa)
- Runtime fuzzy matching of historical Tag typos (Catalog already normalized)
- Changing Studio tagging UX beyond documenting the canonical Tag for Producers
- Filtering by any Tag other than `DEAF&HEARING` from the UI (the `tag` slot is generic, but only one value is bound)
- Replacing the live homepage outside Preview
- Changing R2 cascade/empty-revert policy except as it interacts with this facet (DH7–DH8, DH15)
- Usage analytics for the toggle

## Further Notes

- **Mental model:** green = user-pinned context this visit (filters-rework D21). DEAF+HEARING is green only when on — never a permanent maketa decoration. DH14 keeps this honest: session is a handoff, so a stale flag never colours a cold home load.
- **Why a facet, not a boolean (DH16):** the existing engine already gives us participant mutual-exclusion, reset-clears, and session persistence for anything living in `filterState`. A named boolean would re-implement those; a `tag` slot inherits them. The only genuinely new logic is membership-vs-equality matching.
- **Relationship to D18′:** Participants remains mutually exclusive with R2 **and** with the tag facet. Unlike Participants, the tag facet **composes** with R2 on the player (DH4). Secondary force-ON intentionally clears R2 (DH10) so the button means "the deaf+hearing Playlist," not "intersect with a stale session" — a deliberate, documented asymmetry to validate post-launch.
- **Data fragility:** the tagged set is small (8 today). DH27 turns the "no data → dead button" failure into a disabled control rather than a live dead-end; the pure-set-non-empty invariant is what lets DH10/DH15b fall back safely.
- After implementation, Producers should keep using exactly `DEAF&HEARING` when tagging crossover Videos in Studio.
