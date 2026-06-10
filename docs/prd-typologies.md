# PRD: Video Typologies

## Problem Statement

Producers have no way to classify a Video by the kind of humorous performance it contains (joke, anecdote, riddle, etc.). Without this classification, it is impossible to filter or browse Videos by performance type — either in the Studio or eventually on the Website — and the artistic vocabulary of the DEAF.city project is not captured in the Catalog.

## Solution

Introduce **Typology** as a new first-class Video attribute: a closed, config-backed classification drawn from a managed list of values. Each Video is assigned exactly one Typology. The list of valid Typologies is managed by the Producer from a new tab in the Continguts section of the Studio, following the same add / rename / delete-if-not-referenced pattern as Editions. A Typology selector is added to the Video creation modal (required) and to the Video detail page (editable).

The five initial Typology values are:

| ID | Catalan label |
|----|---------------|
| `acudits` | ACUDITS |
| `anecdotes` | ANÈCDOTES |
| `malentesos` | MALENTESOS |
| `endevinalles` | ENDEVINALLES |
| `memories` | MEMORIES |

## User Stories

1. As a Producer, I want to select a Typology when adding a new Video to the Catalog, so that every new Video is classified from the moment it is created.
2. As a Producer, I want the Video creation modal to require a Typology selection before I can submit, so that Videos are never added without a classification.
3. As a Producer, I want to see and change the Typology of an existing Video on its detail page, so that I can correct a mis-classification without re-creating the Video.
4. As a Producer, I want the Typology field on the detail page to save immediately when I change it (like other fields), so that the workflow is consistent and fast.
5. As a Producer, I want to view all existing Typologies in a dedicated tab in Continguts, so that I can see what vocabulary is in use at a glance.
6. As a Producer, I want to rename an existing Typology in the Continguts tab, so that I can correct a label without losing the association with Videos that already reference it.
7. As a Producer, I want to add a new Typology in the Continguts tab, so that the vocabulary can grow if the project expands to new kinds of performances.
8. As a Producer, I want to delete a Typology that no Video currently references, so that unused values do not clutter the list.
9. As a Producer, I want the delete action to be blocked when a Typology is referenced by one or more Videos, so that I cannot accidentally break existing classifications.
10. As a Producer, I want the Typology selector in the creation modal to offer a quick-add option (like Edition and Sign language), so that I can create a new Typology inline without navigating away.
11. As a Producer, I want the Catalog to store a Video's Typology ID, so that the classification persists and can be read by the Website when it is built.
12. As a Producer, I want the Typology IDs in the Catalog to remain stable even if I rename the label, so that external consumers of the Catalog JSON are not broken.

## Implementation Decisions

### Data model

- The `studio-config.json` file gains a `typologies` array, following the same shape as `editions`: `[{"id": "acudits", "label": "ACUDITS"}, …]`. Seeded with the five initial values on first deploy.
- Each Video entry in `catalog.json` gains an optional `typology` string field holding a Typology ID (e.g. `"typology": "acudits"`). The field is absent on Videos created before this feature; it is required on all new Video additions after this feature ships.
- Typology IDs are lowercase slugs derived from the Catalan label at creation time (same slugify function already used for editions and sign languages). Once created, the ID never changes — only the label is mutable.

### StudioConfig module

- `getTypologies()` — returns the current list of `{id, label}` objects.
- `addTypology(string $id, string $label)` — appends a new entry, rejects duplicate IDs.
- `updateTypologyLabel(string $id, string $label)` — renames the display label, leaves the ID unchanged.
- `removeTypology(string $id, CatalogEditor $catalogEditor)` — removes the entry; throws if any Video in the Catalog references the ID (referential integrity, same guard used by `removeEdition`).

### TypologyAddHandler

A new handler (analogous to `EditionAddHandler`) that accepts a label string, derives the slug ID, validates uniqueness, and delegates to `StudioConfig::addTypology`. Returns `{ok, id, label}` or an errors array.

### CatalogEditor updates

- `addVideo()` gains an optional `typology` parameter, written to the new `typology` field on the entry.
- `updateVideo()` gains a `typology` parameter (nullable string), written to the entry. A `null` value clears the field; omitting it (callers that do not yet pass it) keeps the existing value — or the signature requires it explicitly to avoid drift.
- `getReferencedTypologyIds()` — collects and deduplicates all `typology` values across Videos; used by `removeTypology` to enforce referential integrity.

### VideoEditHandler updates

`handle()` gains a `typology` parameter (nullable string) and passes it to `CatalogEditor::updateVideo()`.

### CatalogAction updates

- New action `add-typology` → `TypologyAddHandler`.
- New action `continguts-save-typology-label` → `StudioConfig::updateTypologyLabel()` (routed through the existing `saveLabel()` dispatcher).
- New action `continguts-delete-typology` → `StudioConfig::removeTypology()` (routed through the existing `deleteItem()` dispatcher).
- `addVideo()` reads `$_POST['typology']` and passes it to `CatalogVideoAddHandler` / `CatalogEditor::addVideo()`.
- `saveVideo()` reads `$_POST['typology']` and passes it to `VideoEditHandler::handle()`.
- `continguts()` passes `$typologies` and `$referencedTypologyIds` to the view.
- `contingutsVideo()` passes `$typologies` to the view.

### continguts.php view updates

- New "Tipologies" tab, rendered identically to the "Ciutats" (Editions) tab: a config list with inline rename, delete button (disabled when referenced), and an "Afegir tipologia…" add panel.
- The "Afegir vídeo" modal gains a required Typology `<select>` (with `+ Afegiu una tipologia…` inline-add option, same pattern as Edition and Sign language selectors). The submit button remains disabled until a Typology is selected.
- The `CONFIG_ACTIONS` JS map gains a `typology` entry with `save` and `delete` action names.

### continguts-video.php view updates

- A Typology `<select>` field is added to the Video detail form. The current value is pre-selected from `$video['typology']`. Saving the form submits `typology` alongside title and tags.

## Testing Decisions

Good tests verify observable outcomes (what the handler returns and what it writes to the filesystem), not internal implementation paths.

### TypologyAddHandlerTest

- Happy path: valid label creates a new entry in config with the correct ID and label.
- Duplicate ID: adding a Typology whose slugified ID already exists returns an error.
- Empty label: rejected with an error response.

Prior art: `EditionAddHandlerTest` (same structure).

### StudioConfigTest (extend existing fixture)

- `getTypologies()` returns the typologies array from the config fixture.
- `removeTypology()` succeeds when no Video references the ID.
- `removeTypology()` throws when a Video references the ID.

Prior art: `StudioConfigTest` already covers `getSignLanguages`, `getEditions`, `getSubtitleLanguages`.

### VideoEditHandlerTest (extend existing)

- Passing a `typology` value writes it to the catalog entry.
- Passing `null` for `typology` clears the field on the catalog entry.

Prior art: existing `VideoEditHandlerTest` uses a temp catalog file and a mocked `VimeoClient`.

### CatalogVideoAddHandlerTest (extend existing)

- Adding a Video with a `typology` value stores it in the catalog entry.
- Adding a Video without a `typology` stores no `typology` field (optional).

## Out of Scope

- Bilingual labels (Catalan + English): Typology labels are single-language for now. Translation of labels is deferred until the Website internationalisation work begins.
- Website display: filtering or browsing Videos by Typology on the public Website is out of scope for this PRD.
- Retroactive assignment: existing Videos in the Catalog will have no `typology` field. Bulk back-filling is out of scope; a Producer can update Videos one at a time via the detail page.
- Multiple Typologies per Video: a Video has at most one Typology. Multi-classification is explicitly out of scope.

## Further Notes

- The term "Tipologia" / "Tipologies" is used throughout the Studio UI (Catalan). The backend identifier in code and data is always `typology` / `typologies`, consistent with the all-English backend convention (same relationship as "Ciutat" ↔ `edition`).
- The domain glossary (`CONTEXT.md`) has been updated with the **Typology** entry as part of the grilling session that produced this PRD.
