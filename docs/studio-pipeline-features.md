# Studio — Pipeline Feature Map

Vertical slices built after the auth gate, in order. **All six pipeline slices are shipped** (2026-05-30).

## Already shipped

**Auth gate** — password prompt, session management, blocker view, Studio shell.

**Intake (Slice 1)** — single intake form (`?action=intake`), Vimeo URL/ID parsing and API validation, curated dropdowns from `data/studio-config.json`, WebVTT upload or interpreter-audio path, job folder at `data/jobs/current/`. See [Slice 1](#slice-1--intake).

**Subtitle Editor (Slice 2)** — full-page cue editor with Vimeo player, live caption preview, integrity validation, Save & Translate / Skip to Tagging. See [Slice 2](#slice-2--subtitle-editor).

**Subtitle Generation (Slice 3)** — Whisper transcription from interpreter audio. See [Slice 3](#slice-3--subtitle-generation).

**Translation (Slice 4)** — Gemini 2.0 Flash batch translation to all remaining subtitle languages, loading screen, Translation Hub, per-language retry. See [Slice 4](#slice-4--translation).

**Tagging (Slice 5)** — checkbox tag pool from `catalog.json`, new-tag input, advances to Publication. See [Slice 5](#slice-5--tagging).

**Publication (Slice 6)** — summary screen, **Publicar** action: Vimeo text tracks (best-effort), server caption files, catalog upsert, job deletion. See [Slice 6](#slice-6--publication).

## Feature slices

| # | Feature | Status | Depends on |
|---|---------|--------|------------|
| 1 | Intake | Shipped | Auth gate |
| 2 | Subtitle Editor | Shipped | Intake |
| 3 | Subtitle Generation | Shipped | Intake |
| 4 | Translation | Shipped | Subtitle Editor (Master subtitle) |
| 5 | Tagging | Shipped | — (any time before Publication) |
| 6 | Publication | Shipped | Subtitle Editor + Tagging |

Build sequence is strictly 1 → 2 → 3 → 4 → 5 → 6. PRDs live under `.scratch/{slice}/PRD.md`.

---

## Slice 1 — Intake

**Shipped.** Implementation lives under `studio/` (`VimeoIdParser`, `VimeoClient`, `StudioConfig`, `JobManager`, `WebVttValidator`, `IntakeHandler`; routes in `studio/index.php`).

The Producer uploads the Video to Vimeo directly, by any means (Vimeo web UI, batch tool, mobile app), before opening the Studio. See [ADR-0003](adr/0003-producer-uploads-video-to-vimeo-directly.md).

In the Studio, the Producer fills a single intake form:
- **Vimeo URL or ID** — accepts a raw numeric ID, standard watch URLs (`https://vimeo.com/{id}`, with optional slug or query string), and Vimeo manage URLs (`https://vimeo.com/manage/videos/{id}`). PHP extracts the numeric ID, calls `GET /videos/{id}` to validate it and fetch the Video title.
- **Sign language** — dropdown from `data/studio-config.json`.
- **Edition** — dropdown from `data/studio-config.json`.
- **Subtitle language** — dropdown from `data/studio-config.json` (the written language of the subtitle file being uploaded).
- **Subtitle file** — WebVTT upload, or interpreter audio for auto-generation (Slice 3).

Submitting creates `data/jobs/current/` containing `job.json` and `draft.vtt`. `data/jobs/` is denied direct web access via `.htaccess`.

The Studio shell shows one of two states:
- **No active Job** — **Nova feina** leads to the intake form.
- **Active Job** — displays Video title + Edition + current pipeline step, with **Continua** and **Cancel·la la feina** buttons. Resume links to the current step route.

One Job is processed at a time. PHPUnit covers the parser, config reader, job manager, and WebVTT validator (`studio/tests/`).

## Slice 2 — Subtitle Editor

**Shipped.** Full-page editor at `?action=subtitle-editor`: sticky Vimeo player with live green caption overlay, editable cue list, integrity checks, **Desa i tradueix** (Save & Translate) and **Omet i ves a l'etiquetatge** (Skip to Tagging). Reused for reviewing translated cues in Slice 4.

## Slice 3 — Subtitle Generation

**Shipped.** Alternate intake path: Producer uploads Interpreter audio instead of a subtitle file. The intake form has a radio toggle ("Upload WebVTT" / "Generate from interpreter audio"). On the generate path, PHP spawns `studio/scripts/run_transcribe.sh` as a background process (nohup) which sources the Blind Wiki venv and calls `studio/scripts/transcribe.py`.

The Python script runs `openai/whisper-large-v3-turbo` via HuggingFace transformers (`return_timestamps=True`) using models cached at `/srv/www/blind.wiki/public_html/Tagger/cache`. It passes `subtitle_language` as the Whisper language hint, converts timestamped chunks to WebVTT, and writes `draft.vtt` into the Job folder. Status is tracked in `transcription.json` (`pending → running → done|error`).

While transcription runs, the shell shows a full-screen loading state that polls `?action=transcription-status` every 3 seconds and auto-redirects to the Subtitle Editor when done. Any format ffmpeg cannot decode shows "El format de l'àudio no es reconeix".

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
