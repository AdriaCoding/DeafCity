# Studio — Feature Map

Producer-facing Studio capabilities as of **June 2026**.

## Active features

| Feature | Entry point | Purpose |
|---------|-------------|---------|
| **Auth gate** | `/studio/` | Password prompt, session management |
| **Continguts (Catàleg)** | `/studio/` (default) | Catalog management: videos, captions, metadata, config |
| **Standalone transcription** | `?action=transcription-intake` | Audio → downloadable VTT/SRT (no Vimeo) |
| **Bulk transcription** | same form, 2+ files | Sequential batch transcription with ZIP download |
| **Catalog sync** | header **Sincronitzar a Vimeo** | Push catalog titles, tags, and captions to Vimeo |

### Continguts — primary workflow

**Shipped; now the default home screen.** Replaced the retired Nova feina pipeline for all day-to-day caption work.

**Add video** — **Afegir vídeo** modal on the Vídeos tab (`CatalogIntakeAddHandler`): Producer pastes a Vimeo URL/ID for a video already on Vimeo ([ADR-0003](adr/0003-producer-uploads-video-to-vimeo-directly.md)), selects sign language, edition, typology, and tags, and may upload initial caption files. Writes directly to `data/catalog.json` and `data/captions/` — no Job folder.

**Video details** (`?action=continguts-video&vimeo_id=…`) — edit title, tags, typology, visibility; manage caption tracks per row:

- Upload new tracks (SRT/VTT/audio)
- Replace, delete, set master
- Cue-level editor (embed of `translation-review.php` via `?action=continguts-caption-review`)
- Translate to all other configured subtitle languages (`?action=continguts-caption-translate-*`)

**Config tabs** — Ciutats, Llengues de signes, Tipologies, Llengues verbals: inline label edit, add, delete (when unreferenced). Add-panel UI is inline in `continguts.php`.

**Vimeo sync** — explicit **Sincronitzar a Vimeo** (header) or `php studio/scripts/sync_to_vimeo.php`. Caption changes on Continguts are local-first; Vimeo is updated on sync or per-action best-effort where implemented.

**Job guard removed** — Continguts is never blocked by an active transcription Job.

Key classes: `CatalogAction`, `CatalogEditor`, `CatalogIntakeAddHandler`, `CaptionUploadHandler`, `CaptionDeleteHandler`, `CaptionReplaceHandler`, `SubtitleEditorHandler::handleForFilePath`, `VideoEditHandler`.

PRDs: `docs/prd-continguts.md`, `docs/prd-caption-track-management.md`, `docs/prd-master-subtitle-revision.md` (Continguts translate path only).

### Standalone transcription

**Shipped.** Separate entry at `?action=transcription-intake` (nav: **Nova transcripció**). Creates a Job with `job_type: transcription` under `data/jobs/current/`.

**Intake:** Producer uploads audio and selects source language. `TranscriptionIntakeHandler` validates, calls `JobManager::createWithAudio`, and runs `TranscriptionOrchestrator` with English as the chained translation target.

**Groq success path:** orchestrator returns `pipeline_transcribed`; handler initiates English translation via `TranslationJobState` + `BackgroundJobLauncher::launchTranslation`, then redirects to `?action=resume-job`.

**Local fallback:** orchestrator returns `loading`; `views/transcription-loading.php` polls asynchronously.

**Loading view states** (`TranscriptionPipelineStatus`):

| State | Condition |
|---|---|
| `transcribing` | `draft.vtt` does not yet exist |
| `translating` | `draft.vtt` exists; translation state is `pending` or `running` |
| `translation_error` | English translation failed |
| `download_ready` | `draft_en.vtt` exists and English entry is `done` |

Polls `?action=transcription-status` or `?action=translation-status` every 3 s. Retry posts to `?action=translation-retry` with `lang=en`. **Finalitza** calls `?action=cancel`.

**Bulk path:** 2+ files → `BulkIntakeHandler` + `BulkIntakeQueue`; progress at `?action=bulk-progress`.

PHPUnit: `TranscriptionIntakeHandlerTest`, `TranscriptionPipelineStatusTest`, `BulkIntakeHandlerTest`.

### Subtitle generation engine (shared)

Transcription (standalone and bulk) uses **Groq primary + local faster-whisper fallback** ([ADR-0006](adr/0006-groq-primary-faster-whisper-fallback-transcription.md)):

1. **Preprocess** — `AudioPreprocessor` → 16 kHz mono FLAC.
2. **Groq** — `GroqTranscriber` (`whisper-large-v3-turbo` default); 20 s timeout, one retry on 429/5xx.
3. **Fallback matrix** — success writes `draft.vtt`; transport/empty spawns local engine; auth/bad-input destroys Job with Catalan error; blank `GROQ_API_KEY` skips Groq.

**CueChunker** post-processes word-level timestamps into readable cues (`src/CueChunker.php` + `scripts/cue_chunker.py`, golden fixture at `tests/fixtures/cue_chunker_cases.json`).

**Local engine:** `studio/scripts/transcribe.py` (faster-whisper, `studio/.venv`, models in `studio/models/`).

---

## Retired: Nova feina pipeline (removed June 2026)

The original six-slice **Nova feina** workflow is **no longer in the codebase**. Routes, handlers, and views were deleted; Continguts is the replacement.

| # | Feature | Was | Removed with |
|---|---------|-----|--------------|
| 1 | Intake | `?action=intake` — Nova feina form | `IntakeHandler`, `views/intake.php` |
| 2 | Subtitle Editor | `?action=subtitle-editor` | `EditorAction` |
| 3 | Subtitle Generation | audio at intake → editor | intake path only (engine kept for transcription) |
| 4 | Translation hub | `?action=translation` | `TranslationAction`, `TranslationCoordinator` |
| 5 | Tagging | `?action=tagging` | `TaggingHandler`, `PublicationAction` |
| 6 | Publication | `?action=publication` | `PublicationHandler` |

**What moved to Continguts:**

- Intake form → **Afegir vídeo** modal (`CatalogIntakeAddHandler`)
- Subtitle editor → **Edita** on caption row (`continguts-caption-review`)
- Translation hub → **Translate** on video (`continguts-caption-translate-*`)
- Tagging → tags on video details / add-video modal
- Publication → caption upload + **Sincronitzar a Vimeo**

**Shared code that survived:** `JobManager`, `TranscriptionOrchestrator`, `GeminiTranslator`, `CueChunker`, `VttParser`, `CaptionUploadHandler`, `IntakeSourceDetector`, `SubtitleEditorHandler::handleForFilePath`, `translation-review.php` (Continguts embed).

Historical PRDs: `.scratch/{slice}/PRD.md` (slices 1–6).

---

## Retired pipeline — historical detail

The sections below document behaviour **as shipped before removal**, for archaeology and ADR context only.

<details>
<summary>Slice 1 — Intake (retired)</summary>

Single intake form at `?action=intake` (nav: **Nova feina**). Producer pasted Vimeo URL/ID, selected sign language, edition, subtitle language from `studio-config.json`, and uploaded WebVTT/SRT or interpreter audio. Created `data/jobs/current/` with `job.json` and `draft.vtt`.

</details>

<details>
<summary>Slice 2 — Subtitle Editor (retired)</summary>

Full-page editor at `?action=subtitle-editor`: Vimeo player, cue list, **Desa i tradueix** / **Omet i ves a l'etiquetatge**. Reused for translation review in Slice 4. Download endpoints `?action=download-vtt|download-srt` now serve transcription Jobs only.

</details>

<details>
<summary>Slice 3 — Subtitle Generation (retired at intake; engine kept)</summary>

Interpreter audio at Nova feina intake triggered `TranscriptionOrchestrator` synchronously on POST, then redirected to the subtitle editor or loading screen. The same orchestrator now powers standalone transcription only.

</details>

<details>
<summary>Slice 4 — Translation (retired)</summary>

**Desa i tradueix** spawned Gemini batch translation to all configured target languages. Translation Hub at `?action=translation` with per-language review and retry. Continguts caption translate uses the same `translate.php` worker with a per-video state file under `data/caption-translation/`.

</details>

<details>
<summary>Slice 5 — Tagging (retired)</summary>

`?action=tagging` — tag pool from `catalog.json`, at least one tag required, advanced to publication.

</details>

<details>
<summary>Slice 6 — Publication (retired)</summary>

`?action=publication` — **Publicar** deleted Vimeo text tracks, re-uploaded captions, copied files to `data/captions/`, upserted `catalog.json`, deleted Job. Catalog sync and Continguts upload/replace now cover this.

</details>
