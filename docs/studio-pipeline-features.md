# Studio — Pipeline Feature Map

Vertical slices to build after the auth gate, in order.

## Already shipped

**Auth gate** — password prompt, session management, blocker view, Studio shell.

**Intake (Slice 1)** — single intake form (`?action=intake`), Vimeo URL/ID parsing and API validation, curated dropdowns from `data/studio-config.json`, WebVTT upload, job folder at `data/jobs/current/` (`job.json` + `draft.vtt`), shell states (New Job / active job with Resume and Cancel). Resume routes to the current pipeline step; Subtitle Editor UI is a placeholder until Slice 2. See [Slice 1](#slice-1--intake) below.

## Feature slices

| # | Feature | Status | Depends on |
|---|---------|--------|------------|
| 1 | Intake | Shipped | Auth gate |
| 2 | Subtitle Editor | Planned | Intake |
| 3 | Subtitle Generation | Planned | Intake |
| 4 | Translation | Planned | Subtitle Editor (Master subtitle) |
| 5 | Tagging | Planned | — (any time before Publication) |
| 6 | Publication | Planned | Subtitle Editor + Tagging |

Build sequence is strictly 1 → 2 → 3 → 4 → 5 → 6. Each slice is specced and shippable before the next starts.

---

## Slice 1 — Intake

**Shipped.** Implementation lives under `studio/` (`VimeoIdParser`, `VimeoClient`, `StudioConfig`, `JobManager`, `WebVttValidator`, `IntakeHandler`; routes in `studio/index.php`).

The Producer uploads the Video to Vimeo directly, by any means (Vimeo web UI, batch tool, mobile app), before opening the Studio. See [ADR-0003](adr/0003-producer-uploads-video-to-vimeo-directly.md).

In the Studio, the Producer fills a single intake form:
- **Vimeo URL or ID** — accepts a raw numeric ID, standard watch URLs (`https://vimeo.com/{id}`, with optional slug or query string), and Vimeo manage URLs (`https://vimeo.com/manage/videos/{id}`). PHP extracts the numeric ID, calls `GET /videos/{id}` to validate it and fetch the Video title.
- **Sign language** — dropdown from `data/studio-config.json`.
- **Edition** — dropdown from `data/studio-config.json`.
- **Subtitle language** — dropdown from `data/studio-config.json` (the written language of the subtitle file being uploaded).
- **Subtitle file** — WebVTT upload (Slice 1 primary path). Server validates `.vtt` extension and a `WEBVTT` header. Interpreter audio upload is deferred to Slice 3.

Submitting creates `data/jobs/current/` containing `job.json` (`vimeo_id`, `video_title`, `sign_language`, `edition`, `subtitle_language`, `step` set to `subtitle-editor`) and the uploaded file saved as **`draft.vtt`**. `data/jobs/` is denied direct web access via `.htaccess`.

The Studio shell shows one of two states:
- **No active Job** — "New Job" button leads to the intake form.
- **Active Job** — displays Video title + Edition + current pipeline step, with "Resume" and "Cancel" buttons. Resume links to the step route (today `?action=subtitle-editor` shows a placeholder until Slice 2). Cancel shows a confirm dialog that mentions deletion of `draft.vtt`, then removes `data/jobs/current/`.

One Job is processed at a time. PHPUnit covers the parser, config reader, job manager, and WebVTT validator (`studio/tests/`).

## Slice 2 — Subtitle Editor

Vanilla JS cue-list alongside a Vimeo player. Producer edits cue text and timestamps with live subtitle preview. Saves a draft caption file (WebVTT) into the Job folder. The reviewed and saved result is the **Master subtitle**.

## Slice 3 — Subtitle Generation

Alternate intake path: Producer uploads Interpreter audio instead of a subtitle file. The Studio calls the Blind Wiki Whisper pipeline via PHP `proc_open` to auto-generate subtitle cues. Output lands in the Job folder as a draft; Producer then reviews and corrects it in the Subtitle Editor.

**TBD before building:** verify the PHP → Python subprocess path is viable on this server. Gemini API is the fallback if Blind Wiki integration is too costly.

## Slice 4 — Translation

From the Master subtitle, generate Subtitle cues in additional Subtitle languages. Each translation opens in the Subtitle Editor for review and correction. Reviewed translations are saved as additional caption files in the Job folder.

**TBD before building:** confirm whether Blind Wiki's T2TT module handles sentence-level translation suitable for subtitle cues. Gemini API is the fallback.

## Slice 5 — Tagging

Producer picks existing Tags or creates new ones before Publication. Tag selection is stored in the Job folder until Publication writes it to the Catalog.

## Slice 6 — Publication

Single **Publish** action from the Producer's perspective. The Video is already on Vimeo (uploaded directly by the Producer at intake — see [ADR-0003](adr/0003-producer-uploads-video-to-vimeo-directly.md)). Publication uploads the caption files and writes the Catalog; all steps must succeed or the action fails with no Catalog write.

1. **Upload subtitles to Vimeo** — for each reviewed caption file in the Job folder (Master subtitle plus every translated Subtitle language), upload WebVTT via the [text tracks API](https://developer.vimeo.com/api/upload/texttracks) (`POST` resource → `PUT` file → activate). One track per Subtitle language; use `subtitles` type and IETF language tags (e.g. `es`, `en`).
2. **Save caption files on the server** — copy the same reviewed WebVTT files into `data/captions/`.
3. **Update the Catalog** — Video metadata, `vimeo_id`, Tags, and caption file references.
4. **Delete the Job folder**

**Playback:** the Preview site player loads Subtitles from server caption files ([ADR-0001](adr/0001-server-hosted-subtitles.md)). Vimeo text tracks are kept in sync at Publication so embeds and tools that use Vimeo captions work; re-Publish after subtitle edits must replace Vimeo tracks for that video.

**Failures:** if any text-track upload fails, block Publish with a clear error and do not update the Catalog.
