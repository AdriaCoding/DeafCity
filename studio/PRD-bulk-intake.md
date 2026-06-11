# PRD: Bulk Transcription Intake

## Problem Statement

Studio operators need to transcribe multiple interpreter audio files in a single session — for example, all recordings from a conference day. Currently, the transcription intake form accepts only one file at a time, and each file must complete the full pipeline before the next can begin. For batch work, this is prohibitively slow and repetitive.

## Solution

Extend the existing transcription intake page (`?action=transcription-intake`) to accept multiple audio files. When 2+ files are selected, the form transforms into a per-file table with per-row language selection and auto-detection from filename. The backend runs files sequentially through transcription + English translation, shows per-file live progress, and auto-downloads all output VTT files as a single ZIP when done. Single-file submissions continue to use the existing flow unchanged.

## User Stories

1. As a Studio operator, I want to select multiple audio files at once on the transcription intake form, so that I can submit a whole batch in one go instead of repeating the process file by file.
2. As a Studio operator, I want the form to automatically detect the source language of each file from its filename suffix (e.g. `talk_ca.mp3` → Catalan), so that I do not have to set each language manually.
3. As a Studio operator, I want a per-file language dropdown in the bulk table so that I can correct any wrong auto-detection before submitting.
4. As a Studio operator, I want the form to behave exactly as before when I select only one file, so that the familiar single-file flow is not disrupted.
5. As a Studio operator, I want to see a live list of files while the batch runs, with each row showing its current status (pending / processing / done / failed), so that I have clear feedback on progress.
6. As a Studio operator, I want failed files to be skipped automatically rather than stopping the whole batch, so that one bad recording does not block the rest.
7. As a Studio operator, I want all successfully transcribed and translated VTT files bundled into a single ZIP that auto-downloads when the batch completes, so that I can retrieve all outputs in one action.
8. As a Studio operator, I want each output VTT file named `{original_stem}_EN.vtt` inside the ZIP, so that the source recording is clearly identifiable.
9. As a Studio operator, I want the bulk job to block the single-file intake (and vice versa), so that I cannot accidentally corrupt the queue.
10. As a Studio operator, I want failed files clearly marked in the progress list with a reason, so that I know which recordings to re-submit manually.
11. As a Studio operator, I want the progress list to remain visible after the batch finishes, so that I can review what succeeded and what failed before the ZIP downloads.

## Implementation Decisions

### Vertical Slice 1 — Multi-file form UI (no backend changes)
- The file input in `transcription-intake.php` gains the `multiple` attribute and its `name` changes to `intake_file[]`.
- `transcription-intake.js` listens to the file input `change` event:
  - `files.length === 1` → existing single-language dropdown shown, single-job mode (no change to behaviour).
  - `files.length >= 2` → single dropdown hidden; a per-row table is rendered dynamically. Each row: filename + a cloned `<select>` for language, pre-populated via the existing `detectSubtitleLanguageFromFilename` function (already exported on `window.TranscriptionIntake`).
- On form submit in bulk mode, per-row language selections are serialised as `bulk_languages[{i}]` fields alongside the multi-file upload.

### Vertical Slice 2 — BulkIntakeQueue (deep module, independently testable)
New class `BulkIntakeQueue`. Backed by a single JSON file at `/data/jobs/bulk-queue.json`. Interface:
- `create(items[])` — initialise queue; each item: `{id, originalFilename, language, tmpAudioPath, status: 'pending'}`.
- `current()` — first `pending` item, or `null`.
- `markProcessing(id)` / `markDone(id, vttPath)` / `markFailed(id, reason)` — item state transitions.
- `exists()` — whether an active queue file is present.
- `destroy()` — remove queue file and bulk-tmp audio directory.
- `statusSnapshot()` — all items with statuses (for polling endpoint).

Queue file writes must be atomic (write to a `.tmp` file then rename) to avoid corrupt reads from concurrent polling.

### Vertical Slice 3 — BulkIntakeHandler + BulkItemProcessor
**BulkIntakeHandler** — new class, handles the multi-file POST from `?action=transcription-intake`:
- Validates each row: language present and in `StudioConfig`, file upload has no PHP error.
- Persists uploaded audio to `/data/jobs/bulk-tmp/{id}.{ext}` (outside `/data/jobs/current/` to avoid collision with `JobManager`).
- Creates the `BulkIntakeQueue`.
- Spawns a background script (`run_bulk.sh`) to process the queue, then returns a redirect to the bulk progress view.

**BulkItemProcessor** — new class, processes one queue item end-to-end:
1. `BulkIntakeQueue::markProcessing(id)`.
2. Move audio into `/data/jobs/current/` via `JobManager::createWithAudio()`.
3. Run `TranscriptionOrchestrator::run()` (Groq → local fallback, identical to the single-job path).
4. If source language ≠ `en`: run English translation via `BackgroundJobLauncher::launchTranslation()` and wait for completion. If source language = `en`: use `draft.vtt` directly (mirrors existing `shouldSkipEnglishTranslation` logic).
5. Copy the resulting VTT to `/data/jobs/bulk-output/{id}_EN.vtt`.
6. `JobManager::cancel()` to clean up `/data/jobs/current/`.
7. `BulkIntakeQueue::markDone(id, vttPath)` or `markFailed(id, reason)` on error.
8. Advance to the next item; if queue exhausted, signal completion in the queue file.

**IntakeAction::handleTranscription()** — detects bulk vs single mode: if `$_FILES['intake_file']` is an array with 2+ entries, delegate to `BulkIntakeHandler`; otherwise keep the existing `TranscriptionIntakeHandler` path.

**Concurrency guard** — `BulkIntakeQueue::exists()` is checked at the top of `handleTranscription()`, blocking single-file intake while a bulk queue is active. `BulkIntakeHandler` checks `JobManager::exists()` before creating the queue, blocking bulk intake while a single job is running.

### Vertical Slice 4 — BulkStatusAction + live progress UI
- New endpoint `?action=bulk-status` (GET, JSON): returns `BulkIntakeQueue::statusSnapshot()` plus a `completed` boolean.
- New view `bulk-progress.php`: renders the initial per-file list; JS polls `?action=bulk-status` every 2 seconds; each row updates its status indicator in place.
- When `completed=true`, JS redirects to `?action=bulk-download`.

### Vertical Slice 5 — BulkZipBuilder + auto-download
**BulkZipBuilder** — new class. Takes a list of `{originalFilename, vttPath}` pairs from the completed queue, produces a ZIP archive using PHP's `ZipArchive`. Each entry named `{stem}_EN.vtt`. Returns the archive as a binary string.

**BulkDownloadAction** (`?action=bulk-download`): calls `BulkZipBuilder`, serves the ZIP with `Content-Disposition: attachment; filename="transcriptions.zip"`, then calls `BulkIntakeQueue::destroy()` to clean up.

## Testing Decisions

Good tests verify observable behaviour through the module's public interface only — no assertions on internal file layout beyond what the interface exposes, no mocking of the module under test.

| Module | Test type | What to test |
|---|---|---|
| `BulkIntakeQueue` | PHPUnit unit (temp dir) | create → advance states → statusSnapshot → destroy leaves no artifacts |
| `BulkIntakeHandler` | PHPUnit integration (temp dir + real `JobManager` + real `BulkIntakeQueue` + stub orchestrator) | valid multi-file POST → queue created, first item processing; invalid POST → validation errors, no queue created |
| `BulkItemProcessor` | PHPUnit unit (stub orchestrator) | orchestrator returns `pipeline_transcribed` → item marked done, VTT saved; orchestrator returns `error` → item marked failed, queue advances |
| `BulkZipBuilder` | PHPUnit unit | N VTT strings → ZIP contains N entries with correct `_EN.vtt` names and correct content |
| Bulk table JS | Node.js (same pattern as `transcription-intake-language.test.js`) | 1 file → single-dropdown mode; 2+ files → table with correct row count; per-row language auto-detected; manual override reflected in serialised fields |

`BulkStatusAction` and `BulkDownloadAction` are thin HTTP adapters — no dedicated tests.

Prior art for test structure: `TranscriptionIntakeHandlerTest.php` (temp-dir setup/teardown, stub orchestrator pattern) and `transcription-intake-language.test.js` (Node `vm.runInNewContext` for JS).

## Out of Scope

- Translation to any language other than English — bulk output is English-only.
- The editor, publication, or Vimeo upload steps for bulk jobs.
- Parallel (concurrent) processing of multiple files — strictly sequential.
- Resume or retry of a partially failed batch after page reload.
- Cancellation of an in-progress bulk batch from the UI.
- Progress via WebSocket or Server-Sent Events — polling is sufficient.

## Further Notes

- The full single-job pipeline (editor → publication) is expected to be deprecated soon. The bulk feature intentionally targets only the transcription + English translation phase that will survive that deprecation.
- The existing `detectSubtitleLanguageFromFilename` in `transcription-intake.js` is already exported on `window.TranscriptionIntake` and handles 2- and 3-char suffix matching with longest-match-wins semantics — the bulk table JS must call it directly rather than reimplementing it.
- Verify that PHP's `ZipArchive` extension is available on the server before implementing Slice 5.
- The background script `run_bulk.sh` should follow the same shell pattern as the existing `run_transcribe.sh` and `run_translate.sh` in `studio/scripts/`.
