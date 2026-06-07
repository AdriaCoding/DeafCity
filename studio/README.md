# Studio

Private web application where Producers process Videos (intake, subtitle editing, translation, tagging, publication). See `CONTEXT.md` for domain terms.

## Pipeline

All six pipeline slices are **shipped** (see `docs/studio-pipeline-features.md`). PRDs under `.scratch/*/PRD.md`.

**Publication ops:** `data/catalog.json` and `data/captions/` must be writable by the web server user (`www-data`). Vimeo token needs `private`, `upload`, `edit` scopes — see `config/config.example.php`. Test with `php studio/scripts/test_vimeo_publish.php`.

**Catalog sync:** Pull title, tags, captions, and thumbnail URLs for every video in `catalog.json` from Vimeo. Downloads VTT files to `data/captions/` and overwrites each catalog entry in place. Also backfills `thumbnail_url` from the Vimeo `pictures.sizes` array (missing on older entries until next sync).

Can be triggered two ways:

```bash
# Run from the public_html root (blocking)
php studio/scripts/sync_from_vimeo.php
```

Or via the Studio idle screen (**Sincronitzar web amb Vimeo**), which launches the same script as a background process. Progress is written to `data/sync-status.json`; the shell polls `?action=sync-status` every few seconds and shows a progress indicator. A sync already in progress blocks a second launch.

## Continguts

The **Continguts** section (`?action=continguts`) lets Producers manage catalog metadata after publication without starting a pipeline Job. Accessible from the idle screen; blocked while a Job is active. PRD at `docs/prd-continguts.md`.

Three tabs (client-side, no page reload per tab):

- **Vídeos** — lists all published videos (thumbnail + title). Clicking a row expands an edit panel with a title input and a tag chip picker. **Save** writes the new title and tags to Vimeo first (best-effort), then to `catalog.json`. If the Vimeo write fails the catalog is still saved and a warning is shown; if the catalog write fails the operation returns an error.
- **Ciutats** — lists editions with inline-editable labels. Delete is available only when no catalog video references the edition id.
- **Llengues de signes** — same pattern as Ciutats but for sign languages.

Both Ciutats and Llengues de signes include the same add-panel component (city + year → edition; code + qualifier → sign language) that appears in the intake form, sharing the `setupConfigAddPanel` JavaScript from `studio/js/config-add-panel.js`.

Key classes: `VideoEditHandler` (Vimeo-then-catalog orchestration), `CatalogEditor` (atomic read-modify-write on `catalog.json`), `CatalogAction` (routes all `continguts-*` actions and the `addEdition` / `addSignLanguage` endpoints).

## Standalone transcription

The **Transcription** section (`?action=transcription-intake`) transcribes an interpreter audio file and delivers downloadable caption files without any Vimeo involvement. It is a separate entry point from the main pipeline, used for external transcription work.

Flow:
1. Producer uploads an audio file and selects the source language.
2. `TranscriptionIntakeHandler` creates a Job with `job_type: transcription` and runs `TranscriptionOrchestrator` (same Groq-first / local-fallback logic as Slice 3).
3. On success, translation to English is chained automatically via `scripts/run_transcription_pipeline.sh` (a shell script that runs `transcribe.py` then spawns `run_translate.sh` as a background process).
4. The shell shows `views/transcription-loading.php`, which cycles through four states polled every 3 s:
   - `transcribing` — waiting for `transcription.json` to reach `done`
   - `translating` — waiting for `translation-status.json` English entry to reach `done`
   - `translation_error` — English translation failed; offers retry or cancel
   - `download_ready` — shows VTT and SRT download cards for both the source language and English
5. Producer downloads the files and clicks **Finalitza**, which cancels the Job and returns the Studio to idle.

Download endpoints (also available during any active pipeline Job):
- `?action=download-vtt[&lang=XX]` — serves the draft VTT for the master or a translated language
- `?action=download-srt[&lang=XX]` — converts VTT to SRT on the fly and serves it

## Developer scripts

Scripts under `studio/scripts/` that are not part of the runtime request path:

| Script | Purpose | Usage |
|---|---|---|
| `sync_from_vimeo.php` | Pull titles, tags, captions, and `thumbnail_url` from Vimeo into `catalog.json` / `data/captions/` | `php studio/scripts/sync_from_vimeo.php` |
| `test_vimeo_publish.php` | Smoke-test the Vimeo publish flow (text track upload) | `php studio/scripts/test_vimeo_publish.php` |
| `test_groq_transcribe.php` | Smoke-test the Groq transcription API | `GROQ_SMOKE=1 php studio/scripts/test_groq_transcribe.php` |
| `test-translate-integration.php` | Integration test for Gemini translation | `php studio/scripts/test-translate-integration.php` |
| `e2e_test.php` | HTTP-level E2E tests hitting the live Studio | `php studio/scripts/e2e_test.php [password]` (default: `hola`) |
| `translate.php` | Background translation worker; called by `run_translate.sh` | spawned by `BackgroundJobLauncher` |
| `transcribe.py` | faster-whisper transcription worker | spawned by `run_transcribe.sh` / `run_transcription_pipeline.sh` |
| `run_transcribe.sh` | Activates `.venv` and runs `transcribe.py` (nohup wrapper) | spawned by `BackgroundJobLauncher` |
| `run_translate.sh` | Runs `translate.php`; writes a fallback error to the status file if the script exits non-zero before updating status | spawned by `BackgroundJobLauncher` / `run_transcription_pipeline.sh` |
| `run_transcription_pipeline.sh` | Chains `transcribe.py` → `run_translate.sh` in one nohup background process (used by the standalone transcription path) | spawned by `BackgroundJobLauncher` |
| `cue_chunker.py` | Python mirror of `src/CueChunker.php`; used by `transcribe.py` to post-process word-level timestamps into readable cues | imported by `transcribe.py` |
| `studio_log.py` | Shared Python logging helper; sets up a `logging.Logger` writing to `data/logs/studio.log` | imported by `transcribe.py` and `bench_transcription.py` |
| `bench_transcription.py` | One-off benchmark: local faster-whisper vs Groq models on sample audio; outputs timing table and side-by-side transcripts to `audio_samples/benchmark/` | run manually |

## UI language

**All Studio controls and user-visible text must be written in Catalan.**

This includes:

- View templates under `views/` (labels, buttons, headings, help text, confirm dialogs)
- Client-side strings in `js/`
- User-facing error and validation messages returned by PHP handlers (`IntakeHandler`, `CaptionFileIntegrityChecker`, `WebVttValidator`, `VimeoIdParser`, `VimeoClient`, etc.)
- Pipeline step labels in `PipelineSteps.php`

Set `lang="ca"` on HTML documents. Keep product and brand names as proper nouns where appropriate (e.g. **Studio**, **DEAF.city**, **Vimeo**, **WebVTT**).

Internal code comments, PHPUnit assertions on non-UI behaviour, and developer documentation may remain in English.
