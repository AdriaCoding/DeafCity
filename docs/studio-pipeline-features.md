# Studio — Pipeline Feature Map

Vertical slices to build after the auth gate, in order.

## Already shipped

**Auth gate** — password prompt, session management, blocker view, Studio shell.

## Feature slices

| # | Feature | Depends on |
|---|---------|-----------|
| 1 | Intake | Auth gate |
| 2 | Subtitle Editor | Intake |
| 3 | Subtitle Generation | Intake |
| 4 | Translation | Subtitle Editor (Master subtitle) |
| 5 | Tagging | — (any time before Publication) |
| 6 | Publication | Subtitle Editor + Tagging |

Build sequence is strictly 1 → 2 → 3 → 4 → 5 → 6. Each slice is specced and shippable before the next starts.

---

## Slice 1 — Intake

Producer uploads a Video plus one of:
- An existing subtitle file (primary path, built first)
- Interpreter audio for AI generation (built in Slice 3)

Producer also selects: Sign language, Edition, Subtitle language.

Intake creates a **Job** folder on the server filesystem holding the uploaded files. The Studio shell lists resumable Jobs so a Producer can return across sessions. One Job is processed at a time.

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

Single **Publish** action from the Producer's perspective. On the server it runs as one workflow with two Vimeo phases (video, then text tracks); both must succeed or the action fails with no Catalog write (see requirements).

1. **Upload video to Vimeo** — resumable/TUS via API using Antoni's Vimeo Plus account (`upload` + `edit` scopes). Receive `vimeo_id`.
2. **Upload subtitles to Vimeo** — for each reviewed caption file in the Job folder (Master subtitle plus every translated Subtitle language), upload WebVTT via the [text tracks API](https://developer.vimeo.com/api/upload/texttracks) (`POST` resource → `PUT` file → activate). One track per Subtitle language; use `subtitles` type and IETF language tags (e.g. `es`, `en`).
3. **Save caption files on the server** — copy the same reviewed WebVTT files into the server caption directory (`data/captions/`).
4. **Update the Catalog** — Video metadata, `vimeo_id`, Tags, and caption file references.
5. **Delete the Job folder**

**Playback:** the Preview site player still loads Subtitles from server caption files ([ADR-0001](adr/0001-server-hosted-subtitles.md)). Vimeo text tracks are written at Publication so embeds and tools that use Vimeo captions stay in sync; re-Publish after subtitle edits must replace Vimeo tracks for that video.

**Failures:** if the Vimeo account is out of storage/quota, video upload fails, or any text-track upload fails, block Publish with a clear error and do not update the Catalog.
