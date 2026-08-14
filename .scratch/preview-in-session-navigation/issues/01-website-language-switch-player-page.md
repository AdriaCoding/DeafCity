Status: done

Implemented; automated coverage is green. Browser verification (2026-08-14) confirmed the
core in-session architecture on the main player page; two edge-case failures are recorded
under Comments.

# Website language switch without a page reload (player page)

## Parent

[PRD](../PRD.md) — Preview site: in-session navigation (no full page reloads)

## What to build

On the player page, changing Website language from the chrome picker must stop being a
navigation. The Vimeo iframe stays mounted and playing; only the localized text, the
document language/direction, and the selected Subtitle track change.

The server stays the single source of truth for every localized label. A new read-only
endpoint returns, for a requested Website language, the resolved chrome string map plus
the localized filter option labels for Sign language, Edition and Typology — produced by
the *same* PHP functions that render those labels server-side, so a switched page and a
cold-loaded page cannot drift apart.

On the client, the switch:

- replaces the active string map and the filter option label catalog, then re-runs the
  existing label rendering so picker faces, dropdown options, "All …" entries and
  collection nav buttons repaint;
- updates `<html lang>` and `<html dir>` (RTL styling is already attribute-driven, so
  Arabic flips live);
- updates the sticky Subtitle language and re-selects the Subtitle track for the Video
  that is currently playing, without reloading the Video;
- rewrites `?lang=` via `replaceState` so a manual refresh or a shared URL keeps the
  chosen Website language;
- performs no navigation and writes no playback session snapshot.

Localized strings that are rendered server-side and *not* currently owned by JS (the
Reset button text, `aria-label`s on transport and filter controls) must be marked up so a
single generic pass can update them. A switch that leaves any visible stale string is a
failed switch.

If the endpoint request fails for any reason, fall back to today's behavior — navigate to
the `?lang=` URL — so this change is strictly additive and cannot regress.

Scope is the player page only. The same picker also renders on About and Participants via
the shared bottom bar; there it keeps navigating, and slice 02 handles it.

### Docs

This slice makes ADR-0013's failure mode structurally impossible on the Website language
path: with no navigation there is no restore, so there is no paused-thumbnail-plus-in-cue
Subtitle to guard against. Add a superseding ADR (next free number, 0017) recording that
the Website language switch is now an in-session update, and mark ADR-0013 superseded for
this path — noting it still governs About/Participants navigation until slice 03 lands.
Update the `Language switch resume intent` contract in `CONTEXT.md` to match.

## Acceptance criteria

- [x] Changing Website language while a Video is playing does not interrupt playback — the iframe is never re-created and playback position is continuous
- [x] Every visible localized string updates in one pass: picker faces, dropdown options, "All …" entries, collection nav buttons, Reset text, and `aria-label`s — no stale strings remain
- [x] Filter option labels obtained from the endpoint are identical to what the page renders server-side for the same Website language (no drift between a switched page and a cold load)
- [x] Switching to Arabic flips the document to RTL live; switching away restores LTR
- [x] After the switch, the selected Subtitle track is the one a cold load at that `?lang=` would have selected for the same Video
- [x] When the target Website language has no Subtitle track for the current Video, track selection falls back exactly as a cold load does — Subtitles are never silently lost
- [x] `?lang=` in the address bar reflects the active Website language; refreshing loads that same language
- [x] Selecting the already-active Website language is a no-op — no request, no repaint
- [x] No playback session snapshot is written and no navigation occurs during a switch (regression guard for ADR-0013's ghost caption)
- [x] Endpoint failure falls back to the existing `?lang=` navigation
- [x] Filter facets, shuffle order and Playlist cursor are unchanged across a switch
- [x] ADR-0017 added, ADR-0013 marked superseded for this path, `CONTEXT.md` contract updated

## Implementation notes

- Payload builder belongs beside the existing locale bootstrap so it reuses
  `preview_bootstrap_locale()` and `preview_localize_filter_options()` rather than
  reimplementing label composition — that reuse is what acceptance criterion 3 is
  really testing.
- The chrome string map already ships to JS today; `vpcString()` and the picker
  re-render functions already consume it. Prefer rebinding what exists over adding a
  parallel path.
- Put the *decisions* (should this switch happen, which Subtitle track results, what
  URL results) in pure functions in `preview/js/vimeo_playlist_logic.js` so they are
  testable in the existing `node` + `assert` style. Keep DOM mutation a thin shim.
- Tests: a new PHP test for the payload builder following the `i18n_test.php` /
  `site_nav_test.php` pattern, and new cases in `vimeo_playlist_logic.test.js` for the
  pure switch decisions.

### Manual verification

No DOM harness exists, so verify in a browser: start a Video, let it pass the first cue,
switch Website language mid-playback, and confirm audio/video continuity, no flash, no
stale labels, and no ghost caption. Repeat into Arabic for RTL. Repeat with a Video that
lacks a track in the target language.

## Blocked by

None - can start immediately

## Comments

**Implementation pass** — automated coverage green, manual pass outstanding.

Added: `preview/api/locale.php` (payload endpoint), `preview_build_locale_payload()`,
pure `planWebsiteLanguageSwitch()` / `urlWithWebsiteLanguage()` in the playlist logic
module, a `data-i18n-*` markup convention in the bottom bar, and an `applyLocalePayload`
shim exposed as `window.__vpcApplyWebsiteLanguage` once the Vimeo SDK is ready. The
picker calls it and falls back to the old navigation on any failure.

Tests: `locale_payload_test.php` (27), `language_switch_markup_test.php` (16), plus new
cases in `vimeo_playlist_logic.test.js`. Two of these are worth keeping in mind:

- the payload test asserts filter labels against the **real SSR path**, so drift between
  a switched page and a cold load fails the build;
- the markup test recomposes the accessible-name rule in PHP and requires it to
  reproduce what the page rendered, which is the only guard on the one piece of logic
  deliberately duplicated between PHP and JS.

Two pre-existing failures in `home_page_test.php` were found, both previously masked by
an early exit:

1. **Stale copy assertions** (fixed here). The test pinned literal English copy that
   Antoni has since changed in Studio (`"Deaf & Hearing interactions"` →
   `"Deaf and hearing crossover videos"`, `"Sign Language"` → `"Sign language"`, and the
   `All …` labels). Rewritten to resolve from the localization store, so copy edits no
   longer read as regressions. Copy itself was not touched.
2. **Icon viewBox mismatch** (left alone). The test expects `viewBox="6 6 12 12"` on the
   skip icons; the committed SVGs carry `viewBox="3 3 18 18"`. Unmodified files, fails on
   HEAD, and resolving it is an icon-cropping design call rather than part of this slice.

**Browser verification pass** (2026-08-14, `vimeo_caption_player.js?v=61` confirmed in
Network).

Environment: live https://deaf.city/preview/ via Cursor browser automation.

### Passed

1. **Playback continuity (check 1 — primary criterion).** Started playback on the main
   player page, set `window.__probe = Date.now()` and
   `document.querySelector('iframe').dataset.probe = '1'`, switched English → Catalan
   mid-playback (`Pause video` visible before switch). After switch: both probes still
   defined (`1786736948300` / `"1"`), `?lang=ca` rewritten via `replaceState`, only
   `/preview/api/locale.php?lang=ca` fetch observed — no document reload.

2. **RTL.** Arabic: `<html dir="rtl" lang="ar">`; switching to English restored
   `dir="ltr"`. Bottom bar remained usable in RTL (Arabic button labels, transport).

3. **Subtitles.** English cue *"just like those Americans"* became Spanish *"Después de
   comunicarle al director"* after in-session switch to `es`. Basque (`eu`) showed cues
   (*"Ohera eraman nuen eta etxea garbitu nuen."* etc.) — fallback/track selection did
   not leave the caption box permanently empty. After next-video navigation to a paused
   thumbnail, caption box was empty (no ghost caption on thumbnail).

4. **State preservation.** Set typology → Jokes, sign → LSE, city → Bilbao; switched to
   French — same `vimeoId` (`1211692573`), filters unchanged (labels translated:
   Blagues / LSE Espagnole / 2023 Bilbao).

5. **URL sync (main player).** `?lang=` updated in the address bar; hard refresh at
   `?lang=fr` cold-loaded French chrome.

6. **Endpoint failure fallback (check 7).** Blocked `*locale.php*` via DevTools
   `Network.setBlockedURLs`; switching Basque → English triggered full navigation to
   `?lang=en` with fully localized English chrome (not half-translated). Unblocked
   afterwards.

### Failed

1. **Stale `aria-label`s after in-session switch (acceptance criterion 2).** After
   switching to French (`lang=fr`), visible bottom-bar text and most transport
   `aria-label`s updated correctly, but two controls stayed in English:
   - Overlay play/pause button: `"Play or pause video"` (cold load at `?lang=fr` renders
     `"Lire ou mettre en pause la vidéo"` — confirms the string exists, it is not repainted
     in-session).
   - Participants nav link: `"Participants (P)"` in every language tested (ca, ar, fr,
     eu, es).
   Console snippet:
   `staleEnglish: ["Play or pause video", "Participants (P)"]`
   No raw i18n keys observed anywhere.

2. **Participant URL language switch (manual check 6).** Starting from
   `/preview/?participant=Hugo%203`, selecting Spanish (picker and direct
   `__vpcApplyWebsiteLanguage('es')`) failed with:
   `TypeError: Cannot read properties of undefined (reading 'tracks')`
   at `currentItemCueTracksRaw` (`vimeo_caption_player.js?v=61:300`). Language stayed
   English; URL never gained `?lang=es`; `participant=` was preserved but the switch did
   not complete and did not fall back to navigation.

**Browser verification pass — 2 defects found, both fixed.**

*Verified passing:* playback continuity (`window.__probe` and the iframe's own dataset
probe both survived a mid-playback switch; only the `locale.php` XHR fired, no document
request), Arabic RTL live, subtitle updates, filters and current Video preserved across a
switch, `?lang=` via replaceState with refresh, and the blocked-endpoint fallback.

**Defect 1 — crash switching language from a Participant URL.** Reported as
`TypeError: Cannot read properties of undefined (reading 'tracks')`.

Root cause was not the switch. The URL used for testing (`?participant=Hugo 3`) names a
*Video*, not a Participant — real Participants are Aurora, Dani, Monica, Pegolino, Pepa,
Riutort. An unknown Participant is a state `index.php` deliberately supports ("Empty/
unknown: keep catalog master + a technical SSR playlist"), and it resolves to
`loadMasterIndex: -1`, meaning no Video is selected. The reader of the current Video's
caption tracks dereferenced that -1 without a guard. Latent since before this work; the
language switch was simply the first caller to reach it.

Fixed in two places: the tracks reader now treats "no Video selected" as the supported
state it is, and the switch entry point can no longer throw *synchronously* — that second
part is why the reported failure showed no fallback navigation, since the caller's
`.catch()` can only run if a promise is returned. Regression test added covering an
unknown Participant producing a usable switch plan with the `participant` parameter
preserved.

**Defect 2 — two accessible names stayed in the previous language.** The video overlay
and the Participants nav link were rendered outside the marked-up region. Both now carry
markers. While fixing this I found the Participants link would have lost the Participant's
name from its accessible name on a switch (showing the generic route label instead), so
the accessible name is now kept in step with the visible label, hint included.

Coverage extended: the markup test asserts all three new markers, and the existing
PHP/JS composition cross-check now also covers the Participants link.

**Re-verification needed** (small): from `?participant=Aurora` — a real Participant —
switch language mid-playback and confirm no console error, `?lang=` gains the parameter
while `participant` survives, and the nav link keeps showing "Aurora". Then confirm the
video overlay and Participants link accessible names both change language.

**Re-verification pass** (2026-08-14, `vimeo_caption_player.js?v=62`). All checks pass.

From `?participant=Aurora`, mid-playback English → French (`__vpcApplyWebsiteLanguage`):
- No console errors
- URL → `?participant=Aurora&lang=fr` (both params present)
- Nav link accessible name `"Aurora 1 (P)"` — keeps participant name, matches cold load
- Overlay accessible name `"Play or pause video"` → `"Lire ou mettre en pause la vidéo"`
  (matches cold load at `?participant=Aurora&lang=fr`)
- Probes survived; playback continued

Picker path (English → Catalan, mid-playback): same URL shape
(`?participant=Aurora&lang=ca`), no console errors, `"Aurora 1 (P)"` preserved.
