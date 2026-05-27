Status: shipped

# PRD — Slice 6: Publication

## Problem Statement

After completing Tagging, a Producer has a fully processed Job — a Vimeo video with reviewed caption files and selected Tags — but no way to make it live from the Studio. Publishing currently requires manually editing `videos.json` and calling the Vimeo API outside the pipeline. The Producer needs a single Publication action inside the Studio that registers the Video in the Catalog and uploads all caption files to Vimeo.

## Solution

Add a Publication step as the final step of the Studio pipeline, reached after Tagging. The Producer sees a read-only summary of the Job (video title, sign language, edition, tags, and caption languages) and a single **Publicar** button. Clicking it: uploads all caption files to Vimeo as text tracks (best-effort), copies the same files to `data/captions/`, writes or overwrites the Video entry in `catalog.json`, and deletes the Job folder. On full success the Producer is redirected to the Studio home (empty state). If any Vimeo upload failed, the same Publication screen is re-rendered with a warning listing the affected languages; the Catalog has already been written.

As part of this slice, `data/videos.json` is renamed to `data/catalog.json`, all 24 existing entries are migrated to the new ID-based schema, and the Preview site is updated to read from `catalog.json`.

## User Stories

1. As a Producer, I want to see a summary of the Job on the Publication screen so that I can confirm the correct video, edition, tags, and caption languages before committing.
2. As a Producer, I want a single **Publicar** button on the Publication screen so that the entire Publication action is one deliberate click.
3. As a Producer, I want Publication to upload all reviewed caption files to Vimeo as text tracks so that embeds and tools that use Vimeo captions are kept in sync.
4. As a Producer, I want Publication to copy the reviewed caption files to the server so that the Preview site player can serve them.
5. As a Producer, I want Publication to register the Video in the Catalog so that it appears in the Preview site.
6. As a Producer, I want Publication to overwrite any existing Catalog entry for the same Vimeo ID so that re-processing a Video updates rather than duplicates its record.
7. As a Producer, I want the Job folder to be deleted automatically after successful Publication so that the Studio returns to the empty state and I can start a new Job.
8. As a Producer, I want to be redirected to the Studio home after successful Publication so that I can immediately start processing the next Video.
9. As a Producer, I want any Vimeo text track upload failures shown as warnings on the Publication screen (not blocking) so that I know which languages are out of sync on Vimeo while the Catalog and server files are already live.
10. As a Producer, I want Publication to first delete all existing text tracks on Vimeo before uploading new ones so that re-publishing a Video does not create duplicate tracks.
11. As a Producer, I want the pipeline step indicator in the Studio shell to show "Publicació" when the Job is in the Publication step so that I can see at a glance where the Job is.
12. As a Producer, I want to resume a Job that is in the Publication step after closing the browser so that I do not lose the Job.

## Implementation Decisions

### Catalog file rename and migration
`data/videos.json` is renamed to `data/catalog.json` as part of this slice. All 24 existing Video entries are migrated: `sign_language` is converted from full label string (e.g. `"LSM Mexican Sign Language"`) to sign language ID (e.g. `"lsm"`); `edition` is added where missing using the ID format (e.g. `"mexico-2021"`); entries without `tags` gain an empty `"tags": []`; entries without `"captions"` gain an empty `"captions": []`. The Preview site is updated to read from `catalog.json` in the same slice.

### Catalog entry schema
Each Video entry in `catalog.json` has the following shape:

```json
{
  "id": "lsm_639494119",
  "vimeo_id": "639494119",
  "title": "LSM_Ciudad de México_Luis_2",
  "sign_language": "lsm",
  "edition": "mexico-2021",
  "tags": ["humor", "ciudad"],
  "captions": [
    { "lang": "es", "label": "Spanish", "file": "639494119.es.vtt" },
    { "lang": "en", "label": "English", "file": "639494119.en.vtt" }
  ]
}
```

- `id` — `{sign_language_id}_{vimeo_id}`, derived at Publication from `job.json`.
- `sign_language` — sign language ID from `studio-config.json` (matches `job.json`).
- `edition` — edition ID from `studio-config.json` (matches `job.json`).
- `tags` — plain string array, read from `job.json["tags"]` (interface contract with Slice 5).
- `captions[].lang` — ISO 639-1 ID from `studio-config.json`.
- `captions[].label` — subtitle language label from `studio-config.json`.
- `captions[].file` — basename under `data/captions/`.

### Caption file naming convention
Published caption files use the pattern `{vimeo_id}.{lang}.vtt` (e.g. `639494119.es.vtt`). Each file is derived from the Job folder: `draft.vtt` (Master, language = `job["subtitle_language"]`) and `draft_{lang}.vtt` for each translated language. Only files that physically exist in the Job folder are published — languages that errored during translation have no file and are silently skipped.

### Vimeo text track upload — best-effort, delete-then-upload
Before uploading, Publication calls `GET /videos/{id}/texttracks` and deletes every existing track (to prevent duplicates on re-publish). It then uploads each caption file via the SDK's `uploadTexttrack` and activates the track with `PATCH {uri} {"active": true}`. Each upload is attempted independently; failures are collected and reported as warnings. The Catalog write and Job folder deletion proceed regardless of Vimeo results.

### Vimeo language code map — static map in `VimeoClient`
`VimeoClient` holds a private static `LANGUAGE_MAP` constant mapping app ISO 639-1 IDs to IETF BCP 47 tags passed to the Vimeo API. For the current six languages (`es`, `en`, `it`, `fr`, `ca`, `pt`) the codes are identical, making the map a no-op today. A missing key throws explicitly, making any future mismatch immediately visible.

### `VimeoClient` — new methods
- `getTextTracks(string $videoId): array` — `GET /videos/{id}/texttracks`, returns array of `{uri}` entries.
- `deleteTextTrack(string $uri): void` — `DELETE {uri}`.
- `uploadAndActivateTextTrack(string $videoId, string $filePath, string $lang, string $label): void` — calls `uploadTexttrack`, then `PATCH {uri} {"active": true, "language": ..., "name": ...}`. Throws on failure.

### `PublicationHandler` — new class
Orchestrates Publication. Dependencies injected: `VimeoClient`, `JobManager`, `StudioConfig`, `string $captionsDirPath`, `string $catalogFilePath`. Its `handle()` method:

1. Reads `job.json` to build the caption file list from the Job folder.
2. Calls `VimeoClient::getTextTracks` and `deleteTextTrack` for each existing track (errors here are also best-effort — non-fatal).
3. For each caption file, copies it to `$captionsDirPath`, then calls `VimeoClient::uploadAndActivateTextTrack`. Upload failures are collected but do not abort.
4. Reads `catalog.json` (or starts with `{"videos": []}` if not present), upserts the Video entry by `vimeo_id`, writes back.
5. Calls `JobManager::cancel()` to delete the Job folder.
6. Returns `['ok' => true, 'vimeoWarnings' => [...]]`.

### Publication step route
- `GET ?action=publication` (job must exist, step = `publication`): reads `job.json` and `studio-config.json` to build the summary (label lookups for sign language, edition, subtitle languages), renders `views/publication.php`.
- `POST ?action=publication` (job must exist): delegates to `PublicationHandler::handle()`. On full success, redirects to `./`. On success with Vimeo warnings, re-renders `views/publication.php` with a `$vimeoWarnings` array.

### Publication summary view
Displays: video title, sign language label, edition label, tags (comma-separated), and caption languages (labels, comma-separated). Then a single **Publicar** button. If `$vimeoWarnings` is set, renders a warning banner above the button area listing each failed language. A "Torna a l'inici" link is shown instead of (or alongside) the button when warnings are present.

### Preview site update
`preview/lib/videos_catalog.php` and `preview/index.php` are updated to read from `catalog.json`. The sign language filter options are derived from the catalog's distinct `sign_language` IDs, resolved to labels via `studio-config.json` (replacing the current read from `playlists.json`).

### `PipelineSteps`
A `'publication' => 'Publicació'` entry is added to the `LABELS` constant.

## Testing Decisions

Tests verify observable behaviour through public interfaces. They do not assert on private methods, file internals, or Vimeo API wire formats.

### `VimeoClient` — new text track methods
PHPUnit unit tests using a mock `Vimeo` SDK instance injected via constructor:
- `getTextTracks` returns a mapped array from the API response.
- `deleteTextTrack` calls DELETE on the given URI.
- `uploadAndActivateTextTrack` calls the SDK upload method then PATCH to activate; throws if SDK upload fails; throws if activation PATCH returns non-2xx.
- `LANGUAGE_MAP`: throws on an unrecognised language code.

Prior art: `VimeoClientTest.php`.

### `PublicationHandler`
PHPUnit integration tests on a real temp directory (matching the pattern in `JobManagerTest` and `TranslationJobStateTest`), with a stubbed `VimeoClient`:
- Full success path: caption files copied to `$captionsDirPath`, Catalog entry written (upsert), Job folder deleted, returns `ok: true, vimeoWarnings: []`.
- Vimeo upload failure: Catalog is still written, Job folder still deleted, failed language appears in `vimeoWarnings`.
- Overwrite: if `catalog.json` already has an entry with the same `vimeo_id`, it is replaced; other entries are unchanged.
- Only caption files that exist in the Job folder are published (missing `draft_{lang}.vtt` files are silently skipped).

Prior art: `TranslationJobStateTest.php`, `JobManagerTest.php`.

## Out of Scope

- Uploading the Video file itself to Vimeo — the Producer uploads it directly before Intake (ADR-0003).
- Choosing a subset of caption languages to publish — all existing caption files in the Job folder are always published.
- Editing caption content at the Publication step — that belongs in the Subtitle Editor.
- Displaying Tags or filtering by Tag on the Website or Preview site.
- Caption file version history or rollback after Publication.
- Vimeo text track retry UI — the Producer can re-trigger Publication by reprocessing the video.
- Removing the `playlists.json` file — it is still read by the legacy homepage; it is not touched in this slice.

## Further Notes

- The Catalog is read and written with a simple file lock (`LOCK_EX`) to guard against concurrent writes, even though a single Producer is the sole writer in practice.
- `StudioConfig::getSubtitleLanguages()` and `getSignLanguages()` are used for label lookups at Publication and in the summary view; no new config methods are needed.
- The migration of existing `videos.json` entries to `catalog.json` is a one-time manual JSON edit (24 entries), not automated code, since it runs once at deploy time.
- After this slice ships, `CatalogTagPool` in Slice 5 must be updated to read from `catalog.json` instead of `videos.json`.
