# Studio

Private web application where Producers manage the video catalog, caption tracks, and standalone audio transcription. See `CONTEXT.md` for domain terms.

## Active workflows

Studio has two producer-facing workflows:

1. **Continguts (Catàleg)** — the default home screen. Add videos to the catalog, upload and edit caption tracks, translate captions, and manage editions, sign languages, typologies, and subtitle languages. See [Continguts](#continguts) below and `docs/studio-pipeline-features.md`.
2. **Standalone transcription (Nova transcripció)** — transcribe interpreter audio to downloadable VTT/SRT files without any Vimeo or catalog involvement. See [Standalone transcription](#standalone-transcription) below.

The legacy **Nova feina** end-to-end pipeline (`?action=intake` → translation → tagging → publication) was **removed in June 2026**. Continguts replaced it as the primary caption workflow. Historical notes on the retired pipeline live in `docs/studio-pipeline-features.md`.

## Catalog sync (push to Vimeo)

Push title, tags, and caption files for every video in `catalog.json` to Vimeo using each Subtitle language's `vimeo_code`. The server catalog and `data/captions/` are never overwritten from Vimeo. Thumbnail URLs are pulled from Vimeo only when missing on a catalog entry.

**Publication ops:** `data/catalog.json` and `data/captions/` must be writable by the web server user (`www-data`). Vimeo token needs `private`, `upload`, `edit` scopes — see `config/config.example.php`. Test with `php studio/scripts/test_vimeo_publish.php`.

Can be triggered two ways:

```bash
# Run from the public_html root (blocking)
php studio/scripts/sync_to_vimeo.php
```

Or via the Studio header (**Sincronitzar a Vimeo**), which launches the same script as a background process. Progress is written to `data/sync-status.json`; the UI polls `?action=sync-status` every few seconds and shows a progress indicator. A sync already in progress blocks a second launch.

**After deploying `vimeo_code` backfill:** run **Sincronitzar a Vimeo** once so existing Vimeo text tracks (especially `aeb`, previously uploaded under `ar`) are re-uploaded under the correct locale codes.

## Continguts

The **Continguts** section is the Studio home screen (`/` or `?action=continguts`). Producers use it for all catalog and caption work after a video is on Vimeo ([ADR-0003](../docs/adr/0003-producer-uploads-video-to-vimeo-directly.md)).

Nav tabs (client-side, no page reload per tab):

- **Vídeos** — browse and filter the catalog; open a video details page to edit title, tags, typology, visibility, and caption tracks (upload, replace, delete, inline-edit master, cue-level editor, translate to configured target languages).
- **Ciutats** — inline-edit edition labels; add or delete editions when no catalog video references them.
- **Llengues de signes** — same pattern for sign languages.
- **Tipologies** — manage video typology labels.
- **Llengues verbals** — manage subtitle languages (all are auto-translation destinations).

Adding a new catalog video uses the **Afegir vídeo** modal (`CatalogIntakeAddHandler`): Vimeo URL/ID, sign language, edition, typology, tags, optional caption uploads — writes directly to `catalog.json` and `data/captions/` without a pipeline Job.

Key classes: `CatalogAction` (all `continguts-*` routes), `CatalogEditor`, `CatalogIntakeAddHandler`, `CaptionUploadHandler`, `VideoEditHandler`, `VideoVisibilityHandler`.

PRDs: `docs/prd-continguts.md`, `docs/prd-caption-track-management.md`.

Continguts is **not** blocked by an active transcription Job (only transcription and bulk-transcription flows use the one-job slot under `data/jobs/current/`).

## Standalone transcription

The **Nova transcripció** entry point (`?action=transcription-intake`) accepts interpreter audio or VTT/SRT drafts and delivers downloadable caption files. No Vimeo or catalog involvement.

Flow:

1. Producer uploads an audio or subtitle file and selects the source language (or submits a bulk batch of 2+ audio and/or VTT/SRT files).
2. `TranscriptionIntakeHandler` (or `BulkIntakeHandler` for multi-file) creates a Job with `job_type: transcription`. Audio runs `TranscriptionOrchestrator` (Groq-first / local-faster-whisper fallback; see [ADR-0006](../docs/adr/0006-groq-primary-faster-whisper-fallback-transcription.md)); subtitle uploads skip transcription and go straight to revise + translate.
3. On success, translation to English is chained automatically via `scripts/run_transcription_pipeline.sh` (or `run_revise.sh` for subtitle intake).
4. `views/transcription-loading.php` (`?action=resume-job`) cycles through four states polled every 3 s:
   - `transcribing` — waiting for `transcription.json` to reach `done`
   - `translating` — waiting for `translation.json` English entry to reach `done`
   - `translation_error` — English translation failed; offers retry or cancel
   - `download_ready` — VTT and SRT download cards for source language and English
5. Producer downloads the files and clicks **Finalitza**, which cancels the Job and returns to Continguts.

Download endpoints (during an active transcription Job only):

- `?action=download-vtt[&lang=XX]` — serves `draft.vtt` or `draft_{lang}.vtt`
- `?action=download-srt[&lang=XX]` — converts the same file to SubRip on the fly

Translation progress for transcription Jobs uses `?action=translation-status` and `?action=translation-retry` (not to be confused with the removed pipeline translation hub).

## Developer scripts

Scripts under `studio/scripts/` that are not part of the runtime request path:

| Script | Purpose | Usage |
|---|---|---|
| `sync_to_vimeo.php` | Push titles, tags, and captions to Vimeo; backfill missing `thumbnail_url` only | `php studio/scripts/sync_to_vimeo.php` |
| `test_vimeo_publish.php` | Smoke-test the Vimeo text-track upload flow | `php studio/scripts/test_vimeo_publish.php` |
| `test_groq_transcribe.php` | Smoke-test the Groq transcription API | `GROQ_SMOKE=1 php studio/scripts/test_groq_transcribe.php` |
| `test-translate-integration.php` | Integration test for Gemini translation | `php studio/scripts/test-translate-integration.php` |
| `e2e_test.php` | HTTP-level E2E tests hitting the live Studio | `php studio/scripts/e2e_test.php [password]` (default: `hola`) |
| `translate.php` | Background translation worker; called by `run_translate.sh` | spawned by `BackgroundJobLauncher` |
| `transcribe.py` | faster-whisper transcription worker | spawned by `run_transcribe.sh` / `run_transcription_pipeline.sh` |
| `run_transcribe.sh` | Activates `.venv` and runs `transcribe.py` (nohup wrapper) | spawned by `BackgroundJobLauncher` |
| `run_translate.sh` | Runs `translate.php`; writes a fallback error to the status file if the script exits non-zero before updating status | spawned by `BackgroundJobLauncher` / `run_transcription_pipeline.sh` |
| `run_transcription_pipeline.sh` | Chains transcribe → revise → translate (standalone transcription path) | spawned by `BackgroundJobLauncher` |
| `cue_chunker.py` | Python mirror of `src/CueChunker.php`; used by `transcribe.py` | imported by `transcribe.py` |
| `studio_log.py` | Shared Python logging helper writing to `data/logs/studio.log` | imported by `transcribe.py` and `bench_transcription.py` |
| `bench_transcription.py` | Benchmark local faster-whisper vs Groq on sample audio | run manually |

## UI language

**All Studio controls and user-visible text must be written in Catalan.**

This includes:

- View templates under `views/` (labels, buttons, headings, help text, confirm dialogs)
- Client-side strings in `js/`
- User-facing error and validation messages returned by PHP handlers (`CaptionFileIntegrityChecker`, `WebVttValidator`, `VimeoIdParser`, `VimeoClient`, etc.)

Set `lang="ca"` on HTML documents. Keep product and brand names as proper nouns where appropriate (e.g. **Studio**, **DEAF.city**, **Vimeo**, **WebVTT**).

Internal code comments, PHPUnit assertions on non-UI behaviour, and developer documentation may remain in English.
