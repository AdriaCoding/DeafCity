Status: ready-for-agent

# Translation targets on oral languages

## Problem Statement

Producers configure oral languages (Subtitle languages) for Intake, caption upload, and Vimeo Publication. Today the translation pipeline auto-translates to **every** configured language except the Master. That is wrong for languages that exist only for manual captions or Vimeo locale mapping — e.g. Algerian Darija and Tunisian Arabic — and gives Producers no control over which languages receive automatic translation.

## Solution

Add a **Translation target** flag (`translation_target`) on each Subtitle language entry in `studio-config.json`. The Llengues orals screen gains an **Objectiu de traducció** column with a per-row checkbox (immediate save). Only flagged languages are spawned by the main translation pipeline and appear on the Translation Hub / loading screen. Non-target languages remain fully usable at Intake, for manual caption upload, and at Publication.

## User Stories

1. As a Producer, I want to mark specific oral languages as translation targets, so that automatic translation only runs for languages I choose.
2. As a Producer, I want oral languages that are not translation targets to remain selectable at Intake as the Master language, so that I can still process Videos in dialect-only languages.
3. As a Producer, I want non-target languages to remain publishable to Vimeo with their configured locale, so that manual captions are not blocked.
4. As a Producer, I want a clear **Objectiu de traducció** checkbox on each row in Llengues orals, so that I can see and change the flag at a glance.
5. As a Producer, I want checkbox changes to save immediately, so that I do not need a separate save step.
6. As a Producer, I want to turn the flag on or off at any time, so that I am not blocked once a language is in use.
7. As a Producer, I want new oral languages to default to not being translation targets, so that I consciously opt in to auto-translation for each addition.
8. As a Producer, I want existing European languages (es, en, it, fr, ca, pt) to remain translation targets after upgrade, so that current workflow is preserved.
9. As a Producer, I want dialect entries (arq, aeb) to default to not being translation targets, so that they stop being auto-translated without removing them from the config.
10. As a Producer, I want the Translation Hub and loading screen to list only translation targets (excluding the Master), so that I am not shown languages that were never queued.
11. As a Producer, I want **Desa i tradueix** to skip the translation step when no translation targets remain, so that I can proceed directly when auto-translation is not needed.
12. As a Producer, I want toggling a flag during a running Job to have no effect until the next spawn, so that in-flight work is not disrupted.
13. As a developer, I want a typed accessor for translation targets on `StudioConfig`, so that pipeline code has one place to filter languages.
14. As a developer, I want migration logic for entries missing `translation_target`, so that existing configs upgrade safely.

## Implementation Decisions

### Schema

- Add `translation_target: boolean` to each object in `subtitle_languages`.
- **Migration (missing field)**: default `true`, except explicit denylist `{arq, aeb}` → `false`.
- **New entries** (via add handler): default `false`.

### StudioConfig

- Persist and read `translation_target` on subtitle language entries.
- Add `getTranslationTargetLanguages(): array` returning entries where `translation_target === true`.
- Add `setSubtitleLanguageTranslationTarget(string $id, bool $value): void` (or equivalent) with flock-safe JSON write, matching existing mutation patterns.
- Optionally apply migration defaults on read so legacy entries without the field behave correctly before first write.

### Translation pipeline

- `TranslationCoordinator::targetLanguages()` filters to flagged targets only, still excluding the Master language id.
- Single-language retry (`TranslationAction::retry`) continues to accept any lang id passed from the Hub; Hub only shows targets, so non-targets are unreachable in normal use.
- **Zero targets**: when `targetLanguages()` returns `[]`, initiate empty translation state and advance past translation (Subtitle Editor **Desa i tradueix** path skips to tagging — mirror existing empty-target handling in coordinator).
- **Transcription intake**: unchanged — post-transcription chain remains hardcoded to English only (`TranscriptionIntakeHandler`); does not consult `translation_target`.

### Translation Hub / loading screen

- `TranslationAction::hub()` iterates only translation targets (excluding Master) for both loading and done states.

### Catalog / Llengues orals UI

- Add column header **Objectiu de traducció** with checkbox per subtitle-language row.
- Checkbox POSTs immediately to a new action (e.g. `continguts-set-subtitle-language-translation-target`) with `id` and `translation_target` (0/1); revert checkbox on error response.
- **Afegir llengua oral** panel: no checkbox at add time; Producer enables in list afterward.
- Catalan-only UI strings.

### Gemini compatibility

- No API-side allowlist. Any configured Subtitle language may be marked as a translation target. `GeminiTranslator` prompt names remain unchanged; unknown codes still fall back to raw ISO id in prompts.

### Modules to build or modify

| Module | Change |
| --- | --- |
| `StudioConfig` | Field, migration, getter, setter |
| `TranslationCoordinator` | Filter targets |
| `TranslationAction` | Hub/loading filter |
| `CatalogAction` | New toggle endpoint |
| Subtitle Editor action (Save & Translate) | Skip translation when zero targets |
| `continguts.php` | Column UI + JS |
| `SubtitleLanguageAddHandler` | Persist `translation_target: false` on create |

Deep module: **`StudioConfig::getTranslationTargetLanguages()`** — single filter used by coordinator and hub; stable interface, hides JSON shape.

## Testing Decisions

Test external behaviour only — not DOM or inline JS.

| Module | What to test |
| --- | --- |
| `StudioConfig` | Read/write flag; migration denylist {arq, aeb}; new add defaults false; missing field reads as migrated default |
| `CatalogAction` toggle endpoint | Valid id toggles and persists; invalid id returns error JSON |
| `TranslationCoordinator` | Spawns only flagged targets excluding Master; empty when none flagged; Master excluded even if flagged |

Prior art: `StudioConfigVimeoCodeTest`, `StudioConfigMutationTest`, `TranslationCoordinator` tests (if present) or `BackgroundJobLauncherTest` spawn args.

Use `/tdd` during implementation.

## Out of Scope

- Gemini API language allowlist or UI disable for “incompatible” languages
- Checkbox in the **Afegir llengua oral** add panel
- Changing transcription intake’s hardcoded English-only post-translation chain
- Cancelling in-flight translation when flag turned off
- Locking the toggle when a language is referenced in the catalog
- Hub rows for non-target languages with “skipped” status
- Batch save for multiple toggles

## Further Notes

- Domain terms recorded in `CONTEXT.md`: **Translation target** (subset of Subtitle languages for auto-translation).
- ADR: [docs/adr/0010-subtitle-language-translation-target-flag.md](../docs/adr/0010-subtitle-language-translation-target-flag.md).
- After implementation, verify Llengues orals screen shows column for es/en/it/fr/ca/pt (checked) and arq/aeb (unchecked) on migrated config.
