Status: ready-for-agent

# PRD — Slice 5: Tagging

## Problem Statement

Videos in the DEAF.city Catalog carry Sign language and Edition metadata, but no thematic or operational labels. A Producer processing a Video through the Studio has no way to attach reusable Tags — such as "installation" or "theme: humour" — before Publication. Without Tags, the Catalog cannot support filtering or grouping by arbitrary attributes, limiting future Website features.

## Solution

Add a Tagging step to the Studio pipeline as the final step before Publication. The Producer sees all Tags already applied across the Catalog as an alphabetically-sorted checkbox list, can check any combination, and can type new Tags that are appended as pre-checked checkboxes. At least one Tag must be selected before the step can be saved. On save, the selected Tags are persisted in the Job and the step advances to Publication.

## User Stories

1. As a Producer, I want to see all Tags currently used across the Catalog as a checkbox list so that I can quickly reuse established labels.
2. As a Producer, I want the existing Tag list sorted alphabetically so that I can scan it predictably as the pool grows.
3. As a Producer, I want to type a new Tag name and press Enter to add it as a checked item so that I can introduce labels not yet in use without leaving the page.
4. As a Producer, if the Tag I type exactly matches one already in the list, I want that existing checkbox checked automatically instead of a duplicate being created so that the Tag pool stays clean.
5. As a Producer, I want to select multiple Tags for one Video so that a Video can carry more than one label.
6. As a Producer, I want to be blocked from saving with zero Tags selected so that every Video reaching Publication is consistently tagged.
7. As a Producer, I want previously saved Tags pre-checked when I return to the tagging step so that I do not lose work across sessions.
8. As a Producer, after saving Tags I want to be returned to the Studio home showing Publication as the next step so that the pipeline progression is clear.
9. As a Producer, I want Tags preserved exactly as I typed them (with whitespace trimmed) so that capitalisation is intentional and not silently normalised.
10. As a Producer, I want duplicate Tags removed automatically on save so that the Catalog stays clean even if I accidentally added the same label twice.

## Implementation Decisions

### CatalogTagPool (new class)
Reads the Catalog JSON file, collects all `tags` string values across every Video entry, deduplicates, and returns them sorted alphabetically. Constructor takes the catalog file path. Single public method: `getTagsSortedAlphabetically(): string[]`. No dependencies beyond the filesystem.

At the time this slice ships, the catalog file is `data/videos.json`. Slice 6 (Publication) renames it to `data/catalog.json` and notes that `CatalogTagPool` must be updated at that point to reflect the new path.

### TaggingHandler (new class)
Receives raw POST data, validates that at least one non-empty tag is present, trims each value, deduplicates, and calls `JobManager::update()` to persist `tags` (array of strings) and advance `step` to `"publication"`. Returns `['ok' => bool, 'errors' => string[]]`. Mirrors the handler pattern used by `SubtitleEditorHandler`.

### Catalog schema
Each Video entry in `videos.json` gains a `"tags"` key: a JSON array of plain strings, e.g. `["installation", "theme: humour"]`. Tags are written to the Catalog at Publication (future slice), not at the Tagging step. Existing entries without the key are treated as having an empty tag list.

### Job state
Tags are persisted in `job.json` under a `"tags"` key (array of strings) when the Producer saves the Tagging step. The `"step"` field is advanced to `"publication"` at the same time via `JobManager::update()`, which merges fields without overwriting other Job metadata.

### Tag normalisation rules
- Whitespace trimmed from both ends.
- Case preserved exactly as typed — "Installation" and "installation" are distinct tags.
- Deduplication is exact-string match after trimming.

### Tag pool source
Tags are read from the Catalog on every page load (no caching). At current scale (~24 Videos) this is acceptable and keeps the list current without additional infrastructure.

### Routing
- `GET ?action=tagging` (Job must exist): reads `CatalogTagPool`, reads previously saved tags from `job.json`, renders the tagging view.
- `POST ?action=tagging` (Job must exist): delegates to `TaggingHandler`; on success redirects to `./`; on validation failure re-renders the view with error messages in Catalan.

### Tagging view
- Consistent dark Studio theme.
- Checkbox list of existing Catalog Tags in alphabetical order; any Tag already in the Job is pre-checked.
- Free-text input beneath the list. On Enter: if the typed value exactly matches an existing checkbox label, that checkbox is checked and the input is cleared; otherwise a new pre-checked checkbox is appended and the input is cleared. Pure client-side JS — no AJAX.
- Save button submits the form as a standard POST.
- Validation error shown inline in Catalan when zero tags are submitted.

### PipelineSteps
No change needed — `'tagging' => 'Etiquetatge'` already exists.

## Testing Decisions

Good tests verify external behaviour through the module's public interface only — not internal state, private methods, or filesystem layout beyond what the public API exposes.

**CatalogTagPool** — PHPUnit unit tests using fixture JSON files:
- Returns tags from multiple Video entries, deduplicated and sorted alphabetically.
- Handles Video entries with no `tags` key.
- Returns an empty array for a catalog with no tagged Videos.

**TaggingHandler** — PHPUnit unit tests using a real `JobManager` on a temp directory (matching the pattern in `JobManagerTest` and `TranslationJobStateTest`):
- Rejects an empty tag list and returns a validation error.
- Trims whitespace from tag strings before saving.
- Deduplicates tags before saving.
- Persists the normalised tag list and advances the step to `"publication"` in `job.json`.

Prior art: `JobManagerTest`, `StudioConfigTest`, `TranslationJobStateTest` — all use real temp directories, no mocking.

## Out of Scope

- Renaming or deleting Tags across the Catalog.
- Displaying Tags on the Website or Preview site.
- Filtering or grouping Videos by Tag on the Website.
- Writing Tags to `videos.json` — that is part of the Publication step (future slice).
- Tag frequency counts or usage badges in the UI.
- Case-insensitive deduplication.
- A managed tag list in `studio-config.json`.

## Further Notes

- The `skip-to-tagging` route already exists in `index.php` and sets `step = 'tagging'`; no change needed there.
- The tag pool grows organically — no pre-seeded list is required before the feature ships.
- No issue tracker is currently configured; this PRD lives in `.scratch/tagging/PRD.md`.
