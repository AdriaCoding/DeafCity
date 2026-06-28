Status: ready-for-agent

# PRD — Preview localisation (multilingual preview + Studio localisation tab)

Builds on: [translation-targets PRD](../translation-targets/PRD.md) (subtitle-language / `translation_target` model) and [preview-filters-rework PRD](../preview-filters-rework/PRD.md) (preview chrome, pickers, `vpc-config` channel).

## Problem Statement

The DEAF.city preview site (`preview/` — the home player plus the About and Participants pages) is English-only. Every visible label, button, filter, accessibility string, and the About/Participants prose is hardcoded English in the PHP templates and in the player JS. Visitors who read Spanish, Italian, French, Catalan, Portuguese, Algerian Darija, or Tunisian Arabic get an English interface around the signed videos — and there is no way for Antoni ("Toni"), who runs the catalogue from Studio, to provide or edit translations of that interface. The two Arabic-script languages also need right-to-left layout, which the preview does not support at all.

## Solution

1. **The preview auto-localises** into whatever spoken/subtitle languages the catalogue supports, detected from the visitor's browser. All interface chrome, the About/Participants prose, and the catalogue content labels (typology / edition / sign-language names) render in the detected language, falling back to English wherever a translation is missing. Arabic languages render right-to-left.
2. **Studio gains a Localitzacions tab** (in Catalan, like the rest of Studio) where Toni sees every interface string with its English source and an editable cell per language. He can type translations or click a button to machine-translate the empty cells with the existing Gemini pipeline and then correct them. Saving is immediate.

A language only becomes a target the browser can auto-route to once its **interface chrome** is fully translated, so visitors never land on a half-translated page; partially-translated languages remain editable in Studio and reachable via an explicit `?lang=` override for QA.

## User Stories

### Preview visitor

1. As a Spanish-reading visitor, I want the preview interface to appear in Spanish automatically, so that I can use the player without knowing English.
2. As a French / Italian / Catalan / Portuguese-reading visitor, I want the same automatic localisation in my language, so that the site feels made for me.
3. As an Algerian Darija reader, I want the interface in Darija and laid out right-to-left, so that it reads naturally.
4. As a Tunisian Arabic reader, I want the interface in Tunisian Arabic and laid out right-to-left, so that it reads naturally.
5. As a visitor whose browser language has no complete translation yet, I want the interface to appear in English rather than a broken half-translation, so that the site is always coherent.
6. As a visitor, I want the player buttons, filter dropdowns ("All cities", "All typologies", "All sign languages"), the Spoken Language selector, "No subtitles", and Play/Pause/Shuffle/Prev/Next controls all in my language, so that the whole player is usable.
7. As a screen-reader user, I want the accessibility labels (aria-labels) localised too, so that assistive tech announces controls in my language.
8. As a visitor, I want the About and Participants page prose in my language, so that I understand the project and the people.
9. As a visitor, I want the catalogue content labels — typology names (Acudits, Anècdotes…), edition/city names, sign-language names — shown in my language where a translation exists, so that the filters read naturally.
10. As a visitor, I want participant names to stay as they are (not "translated"), so that people's names are respected.
11. As a visitor sharing a link, I want to be able to force a specific language with a URL parameter, so that I can show someone the site in a chosen language regardless of their browser.
12. As a visitor, I want the page's `lang` (and `dir` for Arabic) attributes set correctly, so that browser features and assistive tech behave correctly.

### Studio editor (Toni)

13. As Toni, I want a Localitzacions tab in Studio, so that I have one place to manage all interface translations.
14. As Toni, I want to see every interface string grouped by section (player, about, participants, content labels) with its English source shown as read-only reference, so that I know exactly what I'm translating and where it appears.
15. As Toni, I want one editable cell per supported language for each string, so that I can fill in translations directly.
16. As Toni, I want my edits to go live on the preview immediately on save, so that I don't have to hunt for a separate publish step.
17. As Toni, I want a button that machine-translates the empty cells for a language (or section) using the existing Gemini translation, so that I can populate many strings quickly instead of typing each one.
18. As Toni, I want machine-translation to only fill empty cells and never overwrite what I've already written, so that my own work is safe.
19. As Toni, I want machine-seeded cells visually marked as "seeded / unreviewed" until I touch them, so that I can tell apart what I've verified from what the machine guessed.
20. As Toni, I want to see at a glance which languages are "complete" (chrome fully translated, therefore live for auto-detect) and which are not, so that I know what still needs work before a language goes live to visitors.
21. As Toni, when I add a new subtitle language in the Catàleg tab, I want it to appear automatically as a new column in the Localitzacions tab, so that I don't have to configure localisation separately.
22. As Toni, I want the Studio interface itself to stay entirely in Catalan, so that the admin tool remains consistent.

## Implementation Decisions

### Language set & resolution

- **The set of preview UI languages is read live from `studio-config.json` `subtitle_languages`** (`es, en, it, fr, ca, pt, arq, aeb` today). Adding a subtitle language in the Catàleg tab automatically makes it a candidate UI language and an editor column. This deliberately decouples UI localisation from the `translation_target` flag, which stays scoped to VTT subtitle translation only.
- **English (`en`) is the canonical source and the universal fallback.**
- **Auto-detect only, no visible language switcher.** Resolution: parse `Accept-Language` in quality order, map each tag to a language id, and pick the first id that **passes the completeness gate**; otherwise fall back to `en`.
- **BCP-47 → id mapping:** ISO-639-1 tags match directly (`fr→fr`, etc.); `pt-BR` and any `pt-*` collapse to `pt`; `ar-DZ→arq`, `ar-TN→aeb`; a bare `ar` or any other `ar-*` resolves to the **first Arabic-script target that passes the gate, in studio-config order** (self-healing — if only one of `arq`/`aeb` is ready, it is chosen). All other unmapped tags fall through to the next candidate, ultimately `en`.
- **`?lang=<id>` override** is honoured: it is validated against the known id set, **bypasses the completeness gate** (so QA and shared links can preview an incomplete language), and is stateless (no cookie). An unknown value is ignored and normal auto-detect applies.

### Completeness gate

- A language is auto-detect-routable only when **all chrome keys** (player + about + participants) are present and **non-empty after trimming** for that language. **Content-label keys never count toward the gate** (an untranslated city name must not block a whole language).
- "Translated" means present and non-empty after trim — identical-to-English is allowed (legitimate for short strings like "OK").
- Completeness is computed by a **per-request scan** of the store. The file is small (tens of keys); no caching, no cache-invalidation surface.

### Store

- **One JSON file, `data/ui-localizations.json`**, is the single source of truth for all languages **including English**. Templates never carry inline English defaults.
- Entry shape, keyed by dotted namespace:
  ```
  "player.filter.all_cities": {
    "section": "player",
    "context": "Default option in the City/Edition dropdown",
    "translations": { "en": "All cities", "es": "Todas las ciudades", ... },
    "seeded": { "es": true }        // per-language, machine-seeded-and-unreviewed flag (optional)
  }
  ```
- **Key namespaces:** `player.*`, `about.*`, `participants.*`, and `content.<type>.<id>.<field>` where `<type>` ∈ {`typology`, `edition`, `sign_language`}, `<id>` is the studio-config id, and `<field>` ∈ {`label`, `short_label`}.
- **Content labels translate both `label` and `short_label`.** Base (English) labels remain sourced from `studio-config.json`; the store only holds their translations and overrides them at render time for non-English languages.
- **Missing key at render time → render the raw key string** (an obvious, catchable defect, never a silent blank).
- Initial English is populated **once** by an extraction script that lifts the current hardcoded English out of the templates/JS into the manifest.

### PHP version split (load-bearing)

The site root runs **PHP 5.6** and Studio runs **PHP 8.4** (per AGENTS.md). Therefore the store is **not** a single shared class:

- **Studio (PHP 8.4):** a full read/write `LocalizationStore` (mirrors `StudioConfig` — file-locked writes, list-by-section, get/set cell, completeness computation, fill-empty seed, seeded-flag maintenance).
- **Preview (PHP 5.6):** a separate, **read-only** i18n loader/helper in `preview/lib/`, written in PHP 5.6-compatible syntax. It must not depend on the 8.4 class.
- The two share **only the JSON file format**, which both sides treat as the contract.

### Preview runtime

- A `LanguageResolver` (PHP 5.6, pure) implements the resolution algorithm above. Inputs: the `Accept-Language` header, the available-language list, the per-language completeness map, and the optional `?lang=` override. Output: a single resolved language id. This is the deep, edge-case-dense module.
- An i18n reader exposes a `t(key)` lookup returning the active language's translation with `en` fallback and raw-key-on-miss, plus a helper that produces the **resolved chrome map** for injection into JS.
- The preview sets `<html lang="<id>">` and `dir="rtl"` for `arq`/`aeb` (otherwise `ltr`), server-side.
- **JS delivery:** the existing `<script type="application/json" class="vpc-config">` blob (already `JSON.parse`d by the player JS) gains a `strings` member holding the **entire resolved chrome map**. The player JS reads its previously-hardcoded literals (`Pause video`, `Play video`, `No subtitles`, `Spoken Language`, etc.) from `cfg.strings`. Dropdown default labels that are already delivered as server-rendered `data-generic-label` attributes stay on that path (rendered via `t()`); mixed delivery of the same key by two paths is accepted.
- **Content-label localisation** is applied by a thin wrapper around the existing `vpc_*_options_from_catalog` helpers in `preview/lib/`, which substitutes the store's translated label/short_label for the active language and falls back to the studio-config label otherwise. **Participant names are never localised.**
- **Caching:** the preview cache key must include the resolved language id so localised renders don't collide.
- **RTL scope is minimal:** set `dir=rtl`, switch to logical CSS properties where needed, and fix anything that visibly breaks. No exhaustive mirrored-layout audit.
- **Prose granularity:** About/Participants prose is keyed **one key per paragraph/block**.

### Studio Localitzacions tab

- New nav entry alongside Catàleg / Nova transcripció. A new `NAV_LOCALIZATIONS` constant in the header model, the nav partial, and `resolveActiveNavFromAction` updated. The tab label and all of its UI text are **Catalan**.
- A new `LocalizationAction` (registered in the `studio/index.php` `match`) renders the editor view and handles AJAX save and seed, following the existing thin-Action pattern (JSON responses, like `CatalogAction`).
- **Editor view:** rows grouped by `section`; columns = all `subtitle_languages` read live; **English is the first column, read-only** (reference/source); every other language is an editable cell. Each language shows a completeness indicator (complete = live for auto-detect). Seeded-and-unreviewed cells are visually flagged; the flag clears when Toni edits the cell.
- **Save = immediately live.** A save writes through `LocalizationStore` to `data/ui-localizations.json`; the next preview request reflects it. No draft/publish state — the completeness gate already shields not-yet-complete languages from auto-detect.
- **Machine-seed:** a per-language (and/or per-section) action invokes a `SeedTranslator` that wraps the **existing Gemini translation infrastructure** (as used by the caption-translation pipeline). It **fills empty cells only**, never overwrites a non-empty cell, writes the results live, and marks each filled cell `seeded`.

### Schema / config changes

- New file: `data/ui-localizations.json` (created by the extraction script; committed or generated per existing data-file conventions).
- No change to `catalog.json`. No change to the `subtitle_languages` schema. No new `translation_target`-style flag — UI languages are derived from the existing `subtitle_languages` list.

## Testing Decisions

Good tests here assert **external behaviour** — resolved language ids for given inputs, rendered output containing/omitting expected strings, store state after an operation — not internal call sequences. Preview-side tests are plain PHP 5.6 scripts run with `php preview/tests/<name>_test.php` (prior art: `preview/tests/about_page_test.php`, `filter_pickers_test.php`, which include a page and assert on captured HTML). Studio-side tests are phpunit (prior art: `studio/tests/StudioConfigTest.php`, `StudioConfigTranslationTargetTest.php`, and `GeminiTranslatorTest.php` for a faked translation client).

Mandated coverage (all four chosen for tests):

1. **`LanguageResolver`** (preview, PHP 5.6 test script). The highest-value target. Cover: quality-order selection; ISO-639-1 direct match; `pt-BR`/`pt-*` → `pt`; `ar-DZ→arq`, `ar-TN→aeb`; bare `ar` → first-ready Arabic target in config order (incl. the case where only one is ready); completeness gate excluding an incomplete language from auto-detect; `?lang=` override (valid bypasses gate, invalid ignored); fallback to `en` when nothing matches.
2. **i18n reader / `t()`** (preview, PHP 5.6 test script). Cover: returns active-language value; falls back to `en` when the key lacks the active language; renders the raw key when the key is absent entirely; produces a resolved chrome map suitable for JS injection.
3. **`LocalizationStore`** (Studio, phpunit). Cover: list grouped by section; get/set a single cell persists; completeness computation (chrome-only, non-empty-after-trim, content labels excluded); fill-empty seed semantics (only empties filled, existing values untouched) and seeded-flag set/clear; locked write round-trips through the JSON.
4. **`SeedTranslator`** (Studio, phpunit, faked Gemini client). Cover: fills only empty cells, never overwrites non-empty cells, marks filled cells seeded, and is resilient to a translation-client error (partial fill leaves prior state intact).

Glue/UI — `LocalizationAction` and the editor view — is covered by a light smoke test or manual verification at `https://deaf.city/studio` (password `hola`), not unit tests.

## Out of Scope

- **Per-video content translation** — video titles and tags in `catalog.json` are not localised (content-level translation, much larger; explicitly excluded earlier).
- **A visible on-page language switcher** — auto-detect plus the `?lang=` override only.
- **Cookie/session persistence of language** — resolution is stateless per request.
- **Localising the Studio admin UI** — Studio stays Catalan-only.
- **Full mirrored RTL layout audit** — only `dir=rtl` + breakage fixes.
- **Changes to the VTT subtitle translation pipeline or the `translation_target` flag** — reused as infrastructure, not modified.
- **Editing English copy from the Studio tab** — English is read-only there; English wording changes happen via the seed/templates (revisit if Toni needs to reword English).

## Further Notes

- Two Arabic-script targets share the browser tag `ar`; the bare-`ar` → first-ready-in-config-order rule is the chosen tiebreak. Both Darija (`arq`) and Tunisian (`aeb`) are first-class supported targets.
- The `vpc-config` JSON channel and the `data-generic-label` attribute path already exist; localisation reuses them rather than introducing a new PHP→JS mechanism.
- Suggested build sequence: (1) store + schema + extraction/seed script + `LanguageResolver` + i18n reader; (2) preview extraction (PHP + JS to keys, `lang`/`dir`, language-keyed cache); (3) minimal RTL pass; (4) Studio Localitzacions tab (manual editing); (5) machine-seed via `SeedTranslator`; (6) content-label localisation.
- Manual testing video: **#SHEEP by Hamida** (`vimeo_id` 1197992193, Salamanca 2028).
