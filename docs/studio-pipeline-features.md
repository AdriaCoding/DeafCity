# Studio — Pipeline Feature Map

Vertical slices built after the auth gate, in order. **All six pipeline slices are shipped** (2026-05-30). The **Continguts** post-publication management section and the **Standalone transcription** pipeline are also shipped.

## Already shipped

**Auth gate** — password prompt, session management, blocker view, Studio shell.

**Intake (Slice 1)** — single intake form (`?action=intake`), Vimeo URL/ID parsing and API validation, curated dropdowns from `data/studio-config.json`, WebVTT or SubRip (.srt) upload (SRT converted to WebVTT at intake) or interpreter-audio path, job folder at `data/jobs/current/`. See [Slice 1](#slice-1--intake).

**Subtitle Editor (Slice 2)** — full-page cue editor with Vimeo player, live caption preview, integrity validation, Save & Translate / Skip to Tagging; VTT and SRT download for any draft file. See [Slice 2](#slice-2--subtitle-editor).

**Subtitle Generation (Slice 3)** — Whisper transcription from interpreter audio; `CueChunker` post-processes word-level timestamps into readable single-line cues. See [Slice 3](#slice-3--subtitle-generation).

**Translation (Slice 4)** — Gemini 2.0 Flash batch translation to all remaining subtitle languages, loading screen, Translation Hub, per-language retry. See [Slice 4](#slice-4--translation).

**Tagging (Slice 5)** — checkbox tag pool from `catalog.json`, new-tag input, advances to Publication. See [Slice 5](#slice-5--tagging).

**Publication (Slice 6)** — summary screen, **Publicar** action: Vimeo text tracks (best-effort), server caption files, catalog upsert, job deletion. See [Slice 6](#slice-6--publication).

**Continguts** — post-publication catalog management: edit video title/tags, inline-edit edition and sign language labels, add/delete editions and sign languages. See [Continguts](#continguts).

**Standalone transcription** — separate pipeline for transcribing interpreter audio to downloadable caption files (no Vimeo), with automatic English translation chained. See [Standalone transcription](#standalone-transcription).

## Feature slices

| # | Feature | Status | Depends on |
|---|---------|--------|------------|
| 1 | Intake | Shipped | Auth gate |
| 2 | Subtitle Editor | Shipped | Intake |
| 3 | Subtitle Generation | Shipped | Intake |
| 4 | Translation | Shipped | Subtitle Editor (Master subtitle) |
| 5 | Tagging | Shipped | — (any time before Publication) |
| 6 | Publication | Shipped | Subtitle Editor + Tagging |
| — | Continguts | Shipped | — (idle screen only) |
| — | Standalone transcription | Shipped | — (separate entry point) |

Build sequence for pipeline slices is strictly 1 → 2 → 3 → 4 → 5 → 6. PRDs live under `.scratch/{slice}/PRD.md`.

---

## Slice 1 — Intake

**Shipped.** Implementation lives under `studio/` (`VimeoIdParser`, `VimeoClient`, `StudioConfig`, `JobManager`, `WebVttValidator`, `IntakeHandler`; routes in `studio/index.php`).

The Producer uploads the Video to Vimeo directly, by any means (Vimeo web UI, batch tool, mobile app), before opening the Studio. See [ADR-0003](adr/0003-producer-uploads-video-to-vimeo-directly.md).

In the Studio, the Producer fills a single intake form:
- **Vimeo URL or ID** — accepts a raw numeric ID, standard watch URLs (`https://vimeo.com/{id}`, with optional slug or query string), and Vimeo manage URLs (`https://vimeo.com/manage/videos/{id}`). PHP extracts the numeric ID, calls `GET /videos/{id}` to validate it and fetch the Video title.
- **Sign language** — dropdown from `data/studio-config.json`.
- **Edition** — dropdown from `data/studio-config.json`.
- **Subtitle language** — dropdown from `data/studio-config.json` (the written language of the subtitle file being uploaded).
- **Subtitle file** — WebVTT or SubRip (.srt) upload (converted to `draft.vtt` at intake), or interpreter audio for auto-generation (Slice 3).

Submitting creates `data/jobs/current/` containing `job.json` and `draft.vtt`. `data/jobs/` is denied direct web access via `.htaccess`.

The Studio shell shows one of two states:
- **No active Job** — **Nova feina** leads to the intake form.
- **Active Job** — displays Video title + Edition + current pipeline step, with **Continua** and **Cancel·la la feina** buttons. Resume links to the current step route.

One Job is processed at a time. PHPUnit covers the parser, config reader, job manager, and WebVTT validator (`studio/tests/`).

## Slice 2 — Subtitle Editor

**Shipped.** Full-page editor at `?action=subtitle-editor`: sticky Vimeo player with live green caption overlay, editable cue list, integrity checks, **Desa i tradueix** (Save & Translate) and **Omet i ves a l'etiquetatge** (Skip to Tagging). Reused for reviewing translated cues in Slice 4.

**Download endpoints** (available for any active Job, for both the master draft and any translated draft):
- `?action=download-vtt[&lang=XX]` — serves `draft.vtt` or `draft_{lang}.vtt` as a WebVTT attachment.
- `?action=download-srt[&lang=XX]` — converts the same file to SubRip on the fly via `VttToSrtConverter` and serves it as an `.srt` attachment. The filename includes the Vimeo ID (or audio filename for transcription Jobs) and the language code.

## Slice 3 — Subtitle Generation

**Shipped.** Alternate intake path: Producer uploads Interpreter audio instead of a subtitle file. The intake form has a radio toggle ("Upload WebVTT" / "Generate from interpreter audio").

Transcription uses **Groq as the primary cloud engine with a local faster-whisper fallback** (see [ADR-0006](adr/0006-groq-primary-faster-whisper-fallback-transcription.md)). On the generate path the Intake POST calls `TranscriptionOrchestrator` synchronously:

1. **Preprocess** — `AudioPreprocessor` shells ffmpeg to a 16 kHz mono FLAC in the Job folder (~11× smaller, removes Groq's upload cap; the temp FLAC is deleted after upload).
2. **Groq cloud call** — `GroqTranscriber` posts the FLAC to the OpenAI-compatible `audio/transcriptions` endpoint (`temperature=0`, `response_format=verbose_json`, `language=<subtitle language>`), retrying once (1 s backoff) on 429/5xx/timeout within a 20 s per-request ceiling. Segments are clamped (`start ≥ prev_end`, drop `start ≥ end`) identically to the local path.
3. **Route per the fallback matrix** —
   - **success** ⇒ write `draft.vtt`, stamp `transcription_engine: groq:<model>` on `job.json`, redirect straight to the Subtitle Editor (no loading screen, ~2 s);
   - **transport / empty** ⇒ stamp `transcription_engine: local:<model>`, spawn the async local engine, show the loading screen with a Catalan "fast engine unavailable, may take a few minutes" notice;
   - **auth (401/403) / bad input (400)** ⇒ destroy the Job and re-render Intake with a Catalan error;
   - **blank `GROQ_API_KEY`** ⇒ skip Groq entirely and go straight to local.

The **local fallback** spawns `studio/scripts/run_transcribe.sh` (nohup) which activates the dedicated **`studio/.venv`** and runs `studio/scripts/transcribe.py` — now **faster-whisper (CTranslate2, int8, `vad_filter=True`)** reading CT2 models from `studio/models/`, accepting `--model` (default `whisper-large-v3-turbo`). The old `transformers` engine and Blind Wiki venv coupling are removed. Status stays tracked in `transcription.json` (`pending → running → done|error`); the loading screen polls `?action=transcription-status` every 3 s and auto-redirects to the Subtitle Editor when done. Any format ffmpeg cannot decode shows "El format de l'àudio no es reconeix".

**CueChunker post-processing:** raw Whisper output (word-level timestamps) is re-merged into readable single-line cues by `CueChunker`. The algorithm runs in two phases: (1) a split phase that closes the current cue at hard constraints (max characters, silence pause, max duration) and retreats to the last punctuation break when capacity is exceeded; (2) a time-fit phase that enforces minimum gap between cues and clamps durations. The PHP class (`src/CueChunker.php`) and its Python mirror (`scripts/cue_chunker.py`) are pinned to an identical golden fixture (`tests/fixtures/cue_chunker_cases.json`) so the Groq PHP path and the local faster-whisper Python path produce the same cue shapes. Key parameters (all corpus-calibrated): `max_chars=50`, `pause_threshold=0.4 s`, `max_duration=6.0 s`, `cps_target=14`, `min_duration=1.0 s`, `min_gap=0.1 s`.

**Provenance:** `transcription_engine` (`groq:<model>` / `local:<model>`) is written to `job.json`, plus one structured line per run in `data/logs/studio.log` (engine, model, lang, wall-time, fallback category on fallback).

**Configuration** (all `defined()`-guarded; documented in `config/config.example.php`): `GROQ_API_KEY`, `GROQ_TRANSCRIBE_MODEL`, `GROQ_BASE_URL`, `GROQ_TIMEOUT_SECONDS`, `STUDIO_LOCAL_TRANSCRIBE_MODEL`. Verify the live Groq integration with `GROQ_SMOKE=1 php studio/scripts/test_groq_transcribe.php`.

## Slice 4 — Translation

**Shipped.** From the Master subtitle, **Desa i tradueix** saves `draft.vtt` and spawns `studio/scripts/translate.php` (Gemini 2.0 Flash via `GeminiTranslator` / `TranslationRunner`; see [ADR-0005](adr/0005-gemini-flash-for-subtitle-translation.md)). All configured subtitle languages except the Master are translated to `draft_{lang}.vtt`. Loading screen polls `?action=translation-status`; Translation Hub at `?action=translation` lists per-language status with optional Subtitle Editor review and single-language retry.

## Slice 5 — Tagging

**Shipped.** At `?action=tagging`, Producer selects from tags in `catalog.json` via `CatalogTagPool`, can add new tags, must select at least one. Save persists tags in `job.json` and advances `step` to `publication`.

## Slice 6 — Publication

**Shipped.** At `?action=publication`, Producer sees a read-only summary and clicks **Publicar**. `PublicationHandler` orchestrates:

1. **Delete existing Vimeo text tracks** — `GET /videos/{id}/texttracks`, then delete each (best-effort).
2. **Upload subtitles to Vimeo** — for each caption file in the Job folder (`draft.vtt` + `draft_{lang}.vtt`), upload WebVTT via the [text tracks API](https://developer.vimeo.com/api/upload/texttracks). Failures are collected as warnings; Catalog write proceeds regardless.
3. **Save caption files on the server** — copy to `data/captions/` as `{vimeo_id}.{lang}.vtt`.
4. **Update the Catalog** — upsert entry in `data/catalog.json` by `vimeo_id`.
5. **Delete the Job folder** — Studio returns to empty state.

On full success, redirect to Studio home. On Vimeo warnings, re-render Publication with a warning banner. On hard failure (e.g. catalog not writable), show an error banner (no generic 500).

**Playback:** the Preview site player loads Subtitles from server caption files ([ADR-0001](adr/0001-server-hosted-subtitles.md)). Vimeo text tracks are kept in sync at Publication for embeds and legacy `?texttrack=` compatibility.

**Operational:** Vimeo token needs `private`, `upload`, `edit` scopes. `data/catalog.json` and `data/captions/` must be writable by `www-data`. Test Vimeo integration with `php studio/scripts/test_vimeo_publish.php`.

**Legacy homepage:** still reads `playlists.json`; Publication does not update it ([ADR-0002](adr/0002-catalog-dual-source-transition.md)).

---

## Continguts

**Shipped.** Post-publication catalog management section at `?action=continguts`. Accessible from the Studio idle screen; blocked (redirect to shell) while a pipeline Job is active. PRD at `docs/prd-continguts.md`.

Three client-side tabs (JavaScript show/hide, no page reload):

**Vídeos** — lists every video from `catalog.json` with thumbnail (from `thumbnail_url`) and title. Clicking a row expands an edit panel. The Producer edits the title and adjusts tags (chip input with autocomplete from the existing tag pool). **Save** calls `?action=continguts-save-video` (POST), which delegates to `VideoEditHandler`:
1. Call `VimeoClient::updateTitle` and `VimeoClient::setTags` (best-effort: deletes existing tags one-by-one, then adds new ones).
2. Call `CatalogEditor::updateVideo` regardless of whether the Vimeo calls succeeded.
3. Return `{ok: true, vimeoWarning: string|null}`. A non-null `vimeoWarning` is shown as a non-blocking banner so the Producer knows to retry; `ok: false` means the catalog write also failed.

`CatalogEditor` performs an atomic read-modify-write on `catalog.json` using file locking (same pattern as `StudioConfig::appendConfigEntry`). It touches only `title` and `tags`; `id`, `captions`, `sign_language`, and `edition` are left unchanged.

**Ciutats** — lists editions from `studio-config.json`. Labels are inline-editable (click to edit, save on blur/Enter). Save calls `?action=continguts-save-edition-label` → `StudioConfig::updateEditionLabel`. Delete (`?action=continguts-delete-edition`) is shown only when no catalog video references the edition id; the check is performed by `CatalogEditor::getReferencedEditionIds()`. New editions can be added via the same add-panel component used in the intake form.

**Llengues de signes** — identical pattern to Ciutats but for sign languages. Uses `StudioConfig::updateSignLanguageLabel`, `StudioConfig::removeSignLanguage`, and `CatalogEditor::getReferencedSignLanguageIds()`.

**Shared add-panel component:** `studio/js/config-add-panel.js` exports `setupConfigAddPanel`, included by both `intake.php` and `continguts.php`. Renders a city + year form (editions) or code + qualifier form (sign languages) with a live label/id preview before confirming.

**New `StudioConfig` methods** (all file-locked): `updateEditionLabel`, `updateSignLanguageLabel`, `removeEdition`, `removeSignLanguage`.

PHPUnit coverage: `VideoEditHandlerTest`, `CatalogEditorTest`, `StudioConfigMutationTest`.

---

## Standalone transcription

**Shipped.** A separate intake flow at `?action=transcription-intake` for transcribing interpreter audio to downloadable caption files with no Vimeo involvement. Creates a Job with `job_type: transcription`.

**Intake:** Producer uploads an audio file and selects the source language. `TranscriptionIntakeHandler` validates the upload, creates the Job folder via `JobManager::createWithAudio`, and calls `TranscriptionOrchestrator::run()` (same Groq-first / local-fallback logic as Slice 3).

**Groq success path:** orchestrator returns `pipeline_transcribed`. `TranscriptionIntakeHandler` immediately initiates English translation: calls `TranslationJobState::initiate(['en'])` and `BackgroundJobLauncher::launchTranslation`, then returns `created: true` (shell redirects to idle, which shows the transcription-loading view).

**Local fallback path:** orchestrator returns `loading`. The shell's transcription-loading view handles the rest asynchronously.

**Chained pipeline script:** `scripts/run_transcription_pipeline.sh` is a shell wrapper used by the local-fallback path that runs `transcribe.py` to completion, then spawns `run_translate.sh` as a nohup background process once transcription succeeds. This ensures translate is chained even when PHP does not drive the process.

**Loading view** (`views/transcription-loading.php`) — driven by `TranscriptionPipelineStatus`, which derives one of four states:

| State | Condition |
|---|---|
| `transcribing` | `draft.vtt` does not yet exist |
| `translating` | `draft.vtt` exists; translation state is `pending` or `running` |
| `translation_error` | translation state is `done`; English entry is `error` |
| `download_ready` | `draft_{en}.vtt` exists and English entry is `done` |

The view polls `?action=transcription-status` (transcribing state) or `?action=translation-status` (translating state) every 3 s and reloads on transition. The `translation_error` state offers a retry button (posts to `?action=translation-retry` with `lang=en`) and a cancel button.

**Download-ready state** shows two file cards — source language and English — each with VTT and SRT download links (`?action=download-vtt[&lang=en]`, `?action=download-srt[&lang=en]`). **Finalitza** cancels the Job and returns the Studio to idle.

PHPUnit coverage: `TranscriptionIntakeHandlerTest`, `TranscriptionPipelineStatusTest`.
