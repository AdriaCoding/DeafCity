# PRD: Caption Track Management Actions — Video Details Page

## Problem Statement

The video details page (`continguts-video`) allows uploading and downloading caption files, but once a track is in the catalog the producer cannot correct it without developer intervention. Three routine operations are missing: removing a track that was uploaded by mistake, replacing a track with a corrected file, and fixing the text of a specific cue without re-exporting the whole file.

## Solution

Add three action buttons to each row of the caption tracks table on the video details page:

| Action | Label | What it does |
|--------|-------|--------------|
| Delete | Trash icon + "Elimina" | Removes the caption file and its catalog entry |
| Replace | Upload icon + "Reemplaça" | Overwrites the track with a new SRT or VTT file |
| Edit | Pencil icon + "Edita" | Opens the cue-level text editor in a fullscreen modal |

All three actions are **local only**. Vimeo sync is triggered explicitly via the existing "Push to Vimeo" button that lives alongside "Desa" at the bottom of the form.

---

## User Stories

1. As a Producer, I want to delete a caption track from the table, so that a mistakenly uploaded file can be removed without involving a developer.
2. As a Producer, I want a confirmation prompt before deletion, so that I do not accidentally destroy a caption file I need.
3. As a Producer, when I delete the master caption, I want the system to automatically promote the first remaining track to master, so that the master is never left empty while other tracks exist.
4. As a Producer, I want to replace a caption track with a new SRT or VTT file in one step (without having to delete first), so that corrections are fast.
5. As a Producer, I want to edit individual cues of a caption track in the same editor I use during the translation review stage, so that the experience is familiar and I don't need to download, edit externally, and re-upload.
6. As a Producer, when editing a non-master track, I want to see the master track side-by-side on the left for reference, so that I can align the translation without switching files.
7. As a Producer, when editing the master track itself, I want it shown on both sides, so that I can still use the cue-aligned layout even without a separate reference.
8. As a Producer, I want the editor to open as a fullscreen modal overlay on the same page, so that I don't lose my place in the caption list.
9. As a Producer, when I save inside the editor, I want the modal to close and the caption table to stay where it is, so that I can immediately continue with another track.

---

## Scope

### In scope
- Delete action: removes file from `data/captions/` and entry from `catalog.json`; auto-promotes master if needed
- Replace action: inline file picker per row; on file selection, immediately uploads and overwrites the track (reuses `CaptionUploadHandler` for a single file, skips Vimeo sync)
- Edit action: fullscreen `<dialog>` containing an `<iframe>` pointing to `continguts-caption-review`
- New backend action `continguts-caption-review` (GET + POST) that:
  - GET: reads `vimeo_id` + `lang` from query, loads cues from `data/captions/`, renders `translation-review.php` in embed mode with master cues from the catalog master track
  - POST: saves edited cues back to `data/captions/{vimeo_id}.{lang}.vtt` via `SubtitleEditorHandler::handleForFilePath()`; returns `{ok: true}` or `{ok: false, errors: [...]}`
- New backend action `continguts-delete-caption` (POST: `vimeo_id` + `lang`) — delegates to `CaptionDeleteHandler`
- New backend action `continguts-replace-caption` (POST: `vimeo_id` + `lang` + file upload)
- `CatalogEditor::deleteCaption(string $vimeoId, string $lang): void` — removes entry and auto-promotes master
- `CaptionDeleteHandler` — orchestrates catalog delete + physical file removal
- Remove `guardNoActiveJob()` from all continguts routes (including `continguts-caption-review`) so catalog editing is never blocked by an active Job

### Out of scope
- Vimeo sync on individual action (always explicit via the "Push to Vimeo" button)
- Reordering caption tracks
- Bulk delete
- Creating a brand-new track from scratch via the editor (use upload for that)
- Removing the legacy main job pipeline (`intake`, `translation`, `publication`, etc.) — continguts becomes the primary caption workflow, but old routes stay as dead code until a separate cleanup pass
- PHPUnit tests for frontend UI (verified manually in the browser per `AGENTS.md`)

---

## Design Decisions (grilling session)

| # | Decision |
|---|----------|
| 1 | **TDD scope**: PHPUnit only on backend public interfaces; UI flows verified manually in the browser |
| 2 | **Implementation order**: Delete → Replace → Edit (vertical TDD slices) |
| 3 | **Master auto-promotion**: when the deleted track was master, set `master_caption_lang` to `captions[0]['lang']` after removal (catalog array order, same rule as `upsertCaptions`) |
| 4 | **`deleteCaption` errors**: `RuntimeException` if video not found; `InvalidArgumentException` if lang not in `captions[]`; unset `master_caption_lang` when the last caption is removed |
| 5 | **Replace lang**: fixed by the table row (`vimeo_id` + `lang`); always overwrites `data/captions/{vimeo_id}.{lang}.vtt` for an existing catalog entry — not a path for adding new tracks |
| 6 | **Replace Vimeo skip**: add `bool $syncToVimeo = true` to `CaptionUploadHandler::handle()`; replace passes `false` |
| 7 | **Replace validation**: reject with 422 if `lang` is not already in the video's `captions[]` |
| 8 | **Edit save path**: add `SubtitleEditorHandler::handleForFilePath(string $vttPath, …)` — reuse integrity checks + `VttParser::write`, no `JobManager` |
| 9 | **Job guard**: drop `guardNoActiveJob()` from continguts routes; transcripció keeps its own one-job check in `TranscriptionIntakeHandler` |
| 10 | **Post-save redirect**: add `window.__postSaveRedirect` check in `translation-review.js` before the default `?action=translation` fallback; catalog action sets it to `?action=continguts-video&vimeo_id={id}` |
| 11 | **Delete orchestration**: new `CaptionDeleteHandler` (catalog + file delete + `newMaster` response); `CatalogAction` is thin wiring |
| 12 | **Edit view**: render `translation-review.php` with `$embedMode = true` (hide Studio header nav inside the iframe) |
| 13 | **Edit GET missing files**: hard-fail (embed-mode error) if the edited track file is missing; show an empty master column if the master reference file is missing |

---

## UI Design

### Table columns (updated)
| Master | Language | File on server | Download | Actions |
|--------|----------|----------------|----------|---------|
| ● radio | Català | 638736137.ca.vtt | ↓ VTT  ↓ SRT | ✏ Edita  ↑ Reemplaça  🗑 Elimina |

Actions column contains three small buttons styled like the existing download buttons (border, small font, icon + text).

### Delete flow
1. User clicks "Elimina"
2. Inline confirmation appears in the row: "Eliminar aquest fitxer? [Sí] [No]"
3. On confirm: POST to `continguts-delete-caption`; on success, row is removed from the DOM; if master was promoted, check the radio on the first remaining row (`captions[0]`)

### Replace flow
1. User clicks "Reemplaça"
2. A hidden `<input type="file" accept=".vtt,.srt">` is triggered
3. On file selection: immediate POST to `continguts-replace-caption` with the file + `vimeo_id` + `lang` (lang from the row, not inferred from the upload); spinner shown in the row
4. On success: file cell updates to show the new filename; brief "Substituït correctament" feedback

### Edit flow
1. User clicks "Edita"
2. A fullscreen `<dialog>` opens with a loading spinner
3. An `<iframe>` inside the dialog loads `?action=continguts-caption-review&vimeo_id={id}&lang={lang}`
4. The iframe renders `translation-review.php` with `$embedMode = true` (toolbar + editor, no header nav)
5. User edits cues and clicks "Desa" (save button inside iframe)
6. The iframe POSTs to `continguts-caption-review`, gets `{ok: true}`, then sets `window.location.href` to `window.__postSaveRedirect` (video details page URL)
7. The parent page detects the iframe navigation (via `iframe.onload`) and closes the `<dialog>`

---

## Backend Design

### `CatalogEditor::deleteCaption(string $vimeoId, string $lang): void`
- Opens catalog with exclusive lock
- Throws `RuntimeException` if video not found
- Throws `InvalidArgumentException` if lang not in `captions[]`
- Removes the matching entry from `captions[]`
- If `master_caption_lang === $lang` and other captions remain, sets master to `captions[0]['lang']`
- If no captions remain, unsets `master_caption_lang`
- Does **not** delete the physical file (caller's responsibility, so tests can assert catalog changes independently)

### `CaptionDeleteHandler`
- Calls `catalogEditor->deleteCaption($vimeoId, $lang)`
- Deletes the physical file from `data/captions/` (ignores missing file)
- Re-reads catalog to determine `newMaster` if master was promoted
- Returns `{ok: true, newMaster?: string}`

### `CatalogAction::deleteCaption(): never`
- POST: `vimeo_id` + `lang`
- Delegates to `CaptionDeleteHandler`
- Maps exceptions to 422 JSON `{ok: false, error: "…"}`

### `CatalogAction::replaceCaption(): never`
- POST: `vimeo_id` + `lang` + file upload
- Validates lang exists in catalog for that video (422 if not)
- Runs through `CaptionUploadHandler::handle($vimeoId, $uploads, syncToVimeo: false)` for the single file
- Returns `{ok: true, caption: {lang, label, file}}`

### `CaptionUploadHandler::handle()` change
- Add `bool $syncToVimeo = true` parameter (default preserves existing `saveVideo` behaviour)
- When `false`, skip `syncVimeoCaptions()` entirely

### `CatalogAction::captionReview(): never` (GET + POST)
- **GET**: reads `vimeo_id` + `lang`; validates video and lang in catalog; hard-fails with embed-mode error if edited track file missing; loads master cues from master track file (empty array if master file missing); if editing master, uses same cues on both sides; renders `translation-review.php` with `$embedMode = true` and sets `window.__postSaveRedirect`
- **POST**: decodes cues JSON; calls `SubtitleEditorHandler::handleForFilePath($vttPath, $cues)`; returns `{ok: true}` or `{ok: false, errors: [...]}`

### `SubtitleEditorHandler::handleForFilePath(string $vttPath, array $cues): array`
- Runs `CaptionFileIntegrityChecker` on cues
- Reads existing file at `$vttPath` for header/opaque blocks via `VttParser`
- Writes updated cues back to `$vttPath`
- Returns `{ok: true}` or `{ok: false, errors: [...], cueErrors?: [...]}`

### `translation-review.js` change
- On successful save, redirect to `window.__postSaveRedirect` if set, otherwise `?action=translation`

### `translation-review.php` change
- Accept `$embedMode` flag; when `true`, omit the Studio header nav block

---

## Routing additions (`index.php`)
```
'continguts-delete-caption'
'continguts-replace-caption'
'continguts-caption-review'
```
All routed through `CatalogAction::handle()`.

Also remove `guardNoActiveJob()` calls from `continguts()` and `contingutsVideo()` (and do not add it to the new routes).

---

## TDD Plan

Test-driven development uses **vertical slices** (one test → minimal code → next test). PHPUnit only; UI verified manually.

### Slice 1 — `CatalogEditor::deleteCaption`
1. Removes the matching lang from `captions[]`
2. Promotes `captions[0]` when the deleted lang was master
3. Unsets `master_caption_lang` when the last caption is removed
4. Leaves master unchanged when a non-master is deleted
5. Throws `InvalidArgumentException` when lang not in catalog
6. Throws `RuntimeException` when video not found

### Slice 2 — `CaptionDeleteHandler`
7. Deletes the physical file from `data/captions/`
8. Succeeds even if the physical file is already missing
9. Returns `{ok: true, newMaster: "…"}` when master was promoted
10. Returns `{ok: true}` (no `newMaster`) when master unchanged

### Slice 3 — Replace
11. `CaptionUploadHandler` with `syncToVimeo: false` overwrites file without Vimeo calls
12. Replace rejects when lang is not already in the video's `captions[]`

### Slice 4 — Edit save
13. `SubtitleEditorHandler::handleForFilePath` writes valid cues to a catalog file path
14. Rejects integrity errors without modifying the file

### Manual browser checks (not PHPUnit)
- Inline delete confirmation in table row
- Replace file picker + spinner + success feedback
- Fullscreen dialog + iframe load/close via `iframe.onload`
- `__postSaveRedirect` closes modal and leaves table in place
- Continguts accessible while a transcripció Job is active

---

## Resolved Questions

1. **Replace and master auto-update**: if the user replaces the master caption file, `master_caption_lang` stays unchanged (same lang, new file content). No special handling needed.
2. **Translation-review JS post-save redirect**: `window.__postSaveRedirect` global checked before default fallback. No JS fork.
3. **iframe + dialog CSP**: no CSP headers on Studio; same-origin iframe is straightforward.
4. **Pipeline removal**: main job pipeline retirement is a separate effort; this PRD only drops the continguts Job guard and implements row actions.
5. **Master promotion order**: `captions[0]` in catalog array order after removal (not visual row-below semantics).
