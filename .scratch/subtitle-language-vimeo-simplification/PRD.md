Status: ready-for-agent

# PRD — Simplify Subtitle languages to canonical Vimeo IDs

## Problem Statement

Studio currently stores two identifiers for every Subtitle language: a server `id` and a Vimeo `vimeo_code`. That model was introduced to preserve Algerian Darija (`arq`) and Tunisian Arabic (`aeb`) as distinct server languages even where Vimeo did not support their ISO codes.

The project has since chosen a simpler policy: international Arabic (`ar`) is the sole configured Arabic Subtitle language. Every configured Subtitle language now has the same server and Vimeo identifier. The separate mapping is therefore redundant complexity in the configuration schema, Studio UI, API, filename detection, caption upload, Vimeo sync, tests, and ADR-0007.

The old policy also leaves stale artifacts: an unreferenced `arq` Caption file, an unused legacy fixture, dialect-specific test cases, and documentation that still directs Producers to a completed dialect migration. Several tests no longer represent the current Catalog or stylesheet structure, and Preview test scripts are incorrectly invoked with PHP 5.6 despite using PHP 7+/8 syntax.

## Solution

Use one canonical Subtitle language ID everywhere: Studio configuration, Catalog Caption references, server Caption filenames, Preview, filename detection, and Vimeo text-track uploads.

Remove `vimeo_code` as stored state and remove the producer-facing mapping UI/API. A Producer may add a Subtitle language only when its ISO language ID is also an accepted Vimeo text-track locale. Arabic is represented solely as `{ id: "ar", label: "Arabic" }`.

Rewrite ADR-0007 directly to record this simplified, current policy. Retain server-master Caption ownership and Vimeo’s push-only backup role. Update ADR-0010’s supersession note to state that the active configuration uses international Arabic only.

## User Stories

1. As a Producer, I want one Subtitle language identifier, so that Studio, Caption files, Preview, and Vimeo use the same language code.
2. As a Producer, I want to select Arabic once as `ar`, so that I do not have to choose between dialect-specific Arabic entries.
3. As a Producer, I want every configured Subtitle language to be accepted by Vimeo, so that a saved Caption can be mirrored to Vimeo without a secondary locale mapping.
4. As a Producer, I want Studio to reject an ISO language that Vimeo cannot accept, so that I cannot configure an unusable Subtitle language.
5. As a Producer, I want the Subtitle language picker to expose only the ISO/Vimeo intersection, so that every available choice is valid before I submit it.
6. As a Producer, I want Caption uploads and bulk sync to use the Caption language ID directly, so that there is no hidden translation between server and Vimeo identifiers.
7. As a Producer, I want existing configured languages to remain available, so that removing the redundant mapping does not change the current Subtitle language set.
8. As a Producer, I want the server Catalog and Caption files to remain the source of truth, so that Vimeo remains a recoverable backup mirror rather than a source of metadata.
9. As a Preview visitor, I want the existing `ar` Caption and RTL language behavior to continue working, so that this Studio simplification does not change the public experience.
10. As a maintainer, I want the accepted ADR to describe the actual one-ID policy, so that future work does not reintroduce dialect mapping accidentally.
11. As a maintainer, I want obsolete dialect fixtures and orphaned Caption data removed, so that repository searches and test failures reflect live behavior.
12. As a developer, I want tests to assert public behavior under the current language model, so that tests fail only for real regressions.
13. As a developer, I want Preview tests documented and run with their compatible PHP version, so that parser errors are not mistaken for Preview failures.

## Implementation Decisions

### Canonical Subtitle language schema

- Each `subtitle_languages` entry contains only `id` and `label`.
- `id` is the single canonical language identifier for:
  - Catalog `captions[].lang`;
  - server Caption filenames;
  - Studio intake filename detection;
  - Preview Caption selection;
  - Vimeo text-track upload locale.
- Remove `vimeo_code` from the active Studio configuration and every configuration fixture.
- Existing production entries already satisfy the intended invariant. Remove only the redundant field; do not alter the configured language list.
- Preserve `ar` with the label `Arabic`; do not add, migrate to, or configure `arq` or `aeb`.

### Adding Subtitle languages

- Retain the ISO registry and Vimeo locale registry as separate authoritative reference datasets.
- The selectable language set is their intersection, matched by code.
- Server-side add-language validation must require the submitted ID to exist in both registries.
- Remove the Vimeo locale field from the Studio form, request payload, action, response, and validation messages.
- Remove mapping-specific uniqueness and mutation behavior. A Subtitle language can continue to be removed only when the Catalog permits it under existing language-reference safeguards.

### Studio runtime simplification

- Remove `vimeoCodeFor`, `getUsedVimeoCodes`, and Vimeo-code mutation behavior from `StudioConfig`.
- Remove mapping-specific branches from Caption upload, bulk Vimeo sync, Subtitle output filename generation, and transcription intake language detection.
- Vimeo uploads use the Caption language ID directly.
- Filename detection accepts only configured language IDs; it no longer recognises an alternate Vimeo suffix.
- Retain `VimeoLocaleRegistry` because it enforces the intersection rule when a Producer adds a Subtitle language.

### Data cleanup

- Delete the orphaned `1201836589.arq.vtt` Caption file. It has no Catalog reference.
- Delete the unused legacy Subtitle-language configuration fixture containing `arq` and `aeb`.
- Do not rewrite Catalog data: all current Catalog Caption language references are configured, and none reference `arq` or `aeb`.
- Modify the active configuration through the normal ownership-safe workflow; after root writes beneath `data/`, restore `www-data:www-data` ownership.

### Documentation

- Rewrite ADR-0007 in place. Preserve its decisions that the server owns Caption data and Vimeo is a push-only backup mirror. Replace the extended-dialect and `vimeo_code` model with the canonical-ID/intersection rule.
- Update ADR-0010’s supersession section with a concise note that current configuration uses international Arabic (`ar`) only; retain its historical decision text.
- Update Studio documentation to remove the completed `vimeo_code` backfill and dialect re-upload instruction, and describe direct-ID Vimeo sync.
- Preserve ISO reference dataset entries for `arq` and `aeb`: they are valid reference codes but not configured application languages.
- Preserve historical `.scratch` planning records; they document past decisions and are not active configuration.

### Test maintenance beyond the language refactor

- Scope the single-line Caption CSS assertion to the Caption selector. It must permit ellipsis in unrelated chrome-button and reset-label selectors.
- Update Participant test spot checks to current Catalog Video IDs while preserving assertions for the same participant-name parsing behavior.
- Document and standardize PHP 8.4 as the Preview test runner because several test scripts use PHP 7+/8 syntax. This does not change the PHP 5.6 deployment constraint for Preview source code.

## Testing Decisions

- Good tests assert externally visible behavior and persisted configuration shape, not private implementation helpers.
- Run Studio PHPUnit using PHP 8.4. Update all mapping-specific tests to verify direct language-ID behavior:
  - Caption upload sends `ar` for an Arabic Caption;
  - Vimeo sync sends each Caption `lang` directly;
  - Subtitle output filenames use the source/target language ID suffix;
  - adding a language succeeds only when its ID exists in both registries;
  - an ISO-only language is rejected;
  - no configuration entry serializes `vimeo_code`.
- Update the transcription-intake JavaScript test fixture to use only canonical IDs and assert that only those IDs are detected.
- Keep direct registry tests for ISO and Vimeo data. The presence of `arq`/`aeb` in the ISO registry is valid; it must not make them selectable unless Vimeo also supports the identical code.
- Run all Preview PHP tests with `php8.4`, then run Preview JavaScript tests. Preserve PHP 5.6 compatibility for Preview runtime source with the appropriate lint/smoke check.
- Run Studio JavaScript and Python suites in addition to PHPUnit.
- Verify the Catalog contains no Caption references to removed dialect IDs and that the removed orphan file is absent.
- Perform a browser smoke test of Studio Subtitle-language management, an Arabic Preview page, and a Vimeo-backed Catalog video view.

## Out of Scope

- Adding Arabic dialects, aliases, or fallback mappings.
- Migrating or deleting valid ISO reference-dataset rows.
- Changing Preview’s existing Arabic UI strings, localization fallback, or RTL behavior.
- Changing server-master Caption ownership, caption pull policy, or Vimeo failure-warning semantics.
- Rewriting historical `.scratch` plans and completed issues.
- Changing the PHP version used to serve the Preview site.

## Further Notes

- Current configured Subtitle languages are `es`, `en`, `it`, `fr`, `ca`, `pt`, `ar`, and `eu`; all are present in Vimeo’s text-track locale registry.
- The active Catalog has Caption references only for configured languages.
- Grilling decisions, July 2026:
  - Rewrite ADR-0007 directly rather than create a superseding ADR.
  - Update ADR-0010’s supersession note only.
  - Delete `vimeo_code` entirely rather than preserve a redundant equality-constrained field.
  - Allow only ISO/Vimeo code intersections when adding Subtitle languages.
  - Delete the unreferenced `arq` Caption file and the unused dialect fixture.
