# PRD: Continguts — Studio Content Management Section

## Problem Statement

Antoni (the Producer) cannot edit catalog metadata, edition labels, or sign language labels without involving a developer. When he needs to fix a video title, update tags, or correct an edition label after publication, he communicates the change verbally or by message, and the developer manually edits JSON files and commits them. This creates unnecessary friction for routine corrections and makes the project dependent on developer availability for non-technical content changes.

## Solution

A new **Continguts** section inside the existing Studio webapp, accessible from the idle screen, that lets Antoni:

- Browse all published videos and edit their title and tags directly
- Inline-edit the display labels of editions and sign languages
- Add new editions and sign languages
- Delete editions and sign languages that no videos currently reference

All edits to video title and tags are written back to Vimeo immediately, keeping the catalog and Vimeo in sync so that a subsequent sync does not overwrite manual corrections.

## User Stories

1. As a Producer, I want a "Continguts" link on the Studio idle screen, so that I can reach content management without starting a video pipeline job.
2. As a Producer, I want the Continguts section to be unavailable while a pipeline job is active, so that I am not distracted from an in-progress job.
3. As a Producer, I want three tabs — Vídeos, Edicions, Llengues de signes — so that I can navigate between content types without page reloads.
4. As a Producer, I want to see all catalog videos listed with their thumbnail and title, so that I can quickly identify the video I need to correct.
5. As a Producer, I want thumbnails to load instantly from the catalog (not from a live Vimeo API call), so that the page is fast even with many videos.
6. As a Producer, I want to click a video row to expand an edit panel, so that I can focus on one video at a time without leaving the list.
7. As a Producer, I want to edit a video's title in a text input inside the expanded panel, so that I can correct typos or update the name.
8. As a Producer, I want to add and remove tags using a chip input with autocomplete from the existing tag pool, so that I can reuse consistent tags and avoid duplicates.
9. As a Producer, I want a Save button inside each expanded video panel, so that I can confirm my edits explicitly before they are applied.
10. As a Producer, I want my title and tag edits written to Vimeo at save time, so that a subsequent sync does not overwrite my corrections.
11. As a Producer, I want the catalog entry saved even if the Vimeo write fails, so that my corrections are not lost due to a transient API error.
12. As a Producer, I want a clear warning shown when the Vimeo write fails, so that I know to retry or investigate without losing the local save.
13. As a Producer, I want to see all editions listed in the Edicions tab with their display label, so that I can review and correct them.
14. As a Producer, I want to click an edition's label text to edit it inline, so that I can correct labels quickly without opening a separate form.
15. As a Producer, I want a delete button shown only for editions that no video references, so that I can clean up unused entries without risking data loss.
16. As a Producer, I want an "add edition" form at the bottom of the Edicions tab with city and year fields, so that I can register new editions without going through the intake flow.
17. As a Producer, I want the add-edition form to show a live preview of the generated label and id before I confirm, so that I understand what will be created.
18. As a Producer, I want the same add-edition form component to work in both Continguts and the intake flow, so that the experience is consistent.
19. As a Producer, I want to see all sign languages listed in the Llengues de signes tab with their display label, so that I can review and correct them.
20. As a Producer, I want to click a sign language label to edit it inline, so that I can fix labels without a separate form.
21. As a Producer, I want a delete button shown only for sign languages that no video references, so that I can remove unused entries safely.
22. As a Producer, I want an "add sign language" form at the bottom of the Llengues de signes tab with code and qualifier fields, so that I can add new sign languages from Continguts as well as from intake.
23. As a Producer, I want the add-sign-language form to show a live preview of the label and id before I confirm, so that I know what will be saved.
24. As a Producer, I want edition and sign language label edits to save with a single click after inline editing, so that the interaction is lightweight.
25. As a Producer, I want to see an error message if an inline label save fails, so that I am not left wondering whether my change was applied.

## Implementation Decisions

### New route

A new `?action=continguts` route is added to the Studio dispatcher. It is blocked when a pipeline job is active, matching the behaviour of the intake route. A "Continguts" link is added to the idle screen alongside "Nova feina" and "Sincronitzar web amb Vimeo".

### New and modified backend modules

**VimeoClient** — two new write methods:
- `updateTitle(string $videoId, string $title): void` — sends a PATCH to `/videos/{id}` with the updated name.
- `setTags(string $videoId, array $tags): void` — replaces all tags on a Vimeo video. Because Vimeo's tag API does not support bulk replacement, the implementation deletes existing tags one-by-one and adds the new ones.

**CatalogEditor** (new class) — encapsulates atomic read-modify-write on `catalog.json` for post-publication edits. Exposes a single method: `updateVideo(string $videoId, string $title, array $tags): void`. Uses file locking (same pattern as `StudioConfig::appendConfigEntry`). Does not touch `captions`, `id`, `sign_language`, or `edition`.

**VideoEditHandler** (new class) — orchestrates a video save. Takes a `VimeoClient` and a `CatalogEditor`. Calls `VimeoClient::updateTitle` and `VimeoClient::setTags` first; if either throws, records the Vimeo error but proceeds to call `CatalogEditor::updateVideo`. Returns `['ok' => true, 'vimeoWarning' => string|null]`.

**StudioConfig** — four new methods, all using the existing file-locking `appendConfigEntry` pattern:
- `updateEditionLabel(string $id, string $label): void`
- `updateSignLanguageLabel(string $id, string $label): void`
- `removeEdition(string $id): void` — throws if any catalog video references the id.
- `removeSignLanguage(string $id): void` — throws if any catalog video references the id.

The "unused" check for remove is performed by `CatalogEditor` (or a helper it exposes), which reads the catalog and returns the set of referenced edition/sign-language ids.

**sync_from_vimeo.php** — extended to also fetch the video thumbnail URL from the Vimeo API response (`pictures.sizes` array) and store it as `thumbnail_url` in each catalog entry. Videos processed before this change are backfilled on next sync. The Continguts view shows a neutral placeholder for entries with no `thumbnail_url`.

### New action endpoints (all POST, return JSON)

- `?action=continguts-save-video` — delegates to `VideoEditHandler`.
- `?action=continguts-save-edition-label` — delegates to `StudioConfig::updateEditionLabel`.
- `?action=continguts-save-sign-language-label` — delegates to `StudioConfig::updateSignLanguageLabel`.
- `?action=continguts-delete-edition` — delegates to `StudioConfig::removeEdition`.
- `?action=continguts-delete-sign-language` — delegates to `StudioConfig::removeSignLanguage`.

### New view

`studio/views/continguts.php` — a full-page view with three client-side tabs (Vídeos, Edicions, Llengues de signes). Tab switching is handled via JavaScript (show/hide); no additional page load per tab.

The tag chip UI in the Vídeos tab reuses the same CSS classes and JavaScript pattern as the existing Tagging step view.

### Shared component: config-add-panel

The `setupConfigAddPanel` JavaScript factory function currently embedded in `intake.php` is extracted to `studio/js/config-add-panel.js` and included by both `intake.php` and `continguts.php`. The associated CSS classes (`.config-new-panel`, `.config-new-grid`, etc.) are similarly extracted or duplicated into the continguts view. The PHP panel HTML (id/label inputs, preview, add/cancel buttons) becomes a PHP partial included in both views.

### Thumbnail source

The sync script stores one thumbnail URL per video in `catalog.json` as `thumbnail_url` (a string). The Continguts view renders it as an `<img>` tag with a fixed small size. No live Vimeo API calls are made on the Continguts page load.

### Vimeo write-back on failure

If the Vimeo API call fails during a video save, `VideoEditHandler` catches the exception, proceeds to save `catalog.json`, and returns `vimeoWarning` with the error message. The view surfaces this as a non-blocking warning banner so Antoni knows to retry. A full save failure (catalog write also fails) returns `ok: false`.

### What does NOT write to Vimeo

Edition and sign language label edits are Studio-internal metadata (Vimeo has no concept of them). Only video title and tags are written back to Vimeo.

## Testing Decisions

Tests follow the project pattern: PHPUnit, no framework mocks beyond `createMock`, temp files/dirs created in `setUp` and deleted in `tearDown`. Only external behaviour is tested — not internal method calls, not file formats, not SQL.

**VimeoClient** — tested with a mocked `Vimeo` SDK instance (as in `PublicationHandlerTest`). Verify that `updateTitle` sends the correct PATCH body and that `setTags` deletes old tags and adds new ones in the expected order. Verify that API error responses are surfaced as exceptions.

**CatalogEditor** — tested against a temp `catalog.json` file (as in `CatalogTagPoolTest`). Verify that `updateVideo` overwrites title and tags but leaves captions, id, sign_language, and edition untouched. Verify that concurrent writes do not corrupt the file (lock behaviour).

**VideoEditHandler** — tested with a mocked `VimeoClient` and a temp catalog. Verify: (1) happy path writes both Vimeo and catalog; (2) Vimeo failure still writes catalog and returns a non-null `vimeoWarning`; (3) catalog write failure returns `ok: false`.

**StudioConfig (new methods)** — tested against a temp config file (as in `EditionAddHandlerTest`). Verify `updateEditionLabel` changes the label but not the id. Verify `removeEdition` succeeds when unused and throws when referenced. Same for sign language equivalents.

Prior art for all of the above: `EditionAddHandlerTest`, `StudioConfigTest`, `CatalogTagPoolTest`, `PublicationHandlerTest`.

## Out of Scope

- Editing credits text or any other about-page content (future Continguts tab).
- Editing website copy or internationalization strings.
- Managing subtitle languages (the list in `studio-config.json`).
- Renaming the `id` of an edition, sign language, or video — ids are immutable.
- Changing the `sign_language` or `edition` assignment on a published video.
- Deleting a video from the catalog.
- Uploading or replacing caption files from Continguts.

## Further Notes

- The Continguts section is intentionally scoped to catalog management only in this first iteration. Credits, website copy, and i18n are planned as future tabs within the same section.
- The `thumbnail_url` field added to `catalog.json` by the extended sync script is additive — no migration is needed; older entries simply lack the field and show a placeholder until the next sync.
- The `setupConfigAddPanel` extraction is a refactor that touches `intake.php`. Care should be taken to validate that intake behaviour is unchanged after the extraction.
