# ADR-0007 — Server as master for Subtitle languages; Vimeo as caption backup

**Date**: 2026-06-07
**Status**: Accepted
**Relates to**: [ADR-0001](0001-server-hosted-subtitles.md) (server-hosted caption playback), [ADR-0003](0003-producer-uploads-video-to-vimeo-directly.md) (video on Vimeo before intake)

---

## Context

The server (`data/catalog.json`, `data/captions/`, `data/studio-config.json`) should be the authoritative source for Subtitle languages and Caption files. Vimeo text tracks are a **backup mirror** for the legacy homepage embed and external tools — not a second master.

Today this is violated in several ways:

- `sync_from_vimeo.php` **pulls** titles, tags, and text tracks from Vimeo into `catalog.json` and `data/captions/`, overwriting server state. Pulling destroys dialect identity when Vimeo collapses extended ISO codes (`arq` and `aeb` both appear as `ar`).
- `VimeoClient::LANGUAGE_MAP` hardcodes server `id` → Vimeo locale mapping, including a bug where both `arq` and `aeb` map to `ar` — only one dialect can exist as a Vimeo text track per video.
- Continguts allows free-text Subtitle language codes instead of a curated ISO list.
- `subtitle_languages` entries store only `id` and `label`; there is no persisted `vimeo_code`.

The Preview site player already fetches Caption files from the server ([ADR-0001](0001-server-hosted-subtitles.md)). The legacy homepage still uses Vimeo `?texttrack=` but will be deprecated in favour of the Preview player within weeks — we accept its dialect auto-match limitations and will not add a server-side mapping layer for it.

## Decision

### Subtitle language data model

Each Subtitle language in `studio-config.json` has three fields:

| Field | Meaning |
| --- | --- |
| `id` | **Extended ISO code** — canonical key everywhere on the server (catalog `captions[].lang`, VTT filenames `{vimeo_id}.{id}.vtt`, intake, translation). Prefer ISO 639-1 when one exists (`es`, `ca`, `pt`); otherwise ISO 639-3 (`arq`, `aeb`). Immutable after creation. |
| `label` | Human-readable name in Studio and the Preview caption picker, set from the ISO list at add time (e.g. "Algerian Arabic"). Immutable after creation. |
| `vimeo_code` | Locale code Vimeo accepts when uploading a text track. Equals `id` when Vimeo supports that code 1:1; otherwise a Producer-chosen code from Vimeo's restricted list. Must be **unique** across all configured Subtitle languages (Vimeo allows one active track per locale per video). Editable only while no catalog caption references this `id`. |

Example mappings:

- `ca` → `vimeo_code: ca` (1:1)
- `arq` → `vimeo_code: ar` (Algerian Darija stored under Arabic on Vimeo)
- `aeb` → `vimeo_code: mt` (Tunisian Arabic stored under Maltese on Vimeo; codes need not be linguistically related, only unique slots)

**Backfill:** production `data/studio-config.json` is updated explicitly for all existing languages. **Runtime fallback:** `vimeo_code ?? id` when reading config — safety net for tests/fixtures; production JSON must be explicit for dialects.

**Remove `VimeoClient::LANGUAGE_MAP`.** Callers resolve `vimeo_code` from `StudioConfig` and pass the resolved Vimeo locale to `uploadAndActivateTextTrack` directly.

### Reference data (single source of truth)

| File | Purpose |
| --- | --- |
| `studio/js/iso-639-3.json` | Searchable language picker — `{ languages: [{ code, label }] }`; 639-1 preferred when available |
| `studio/js/vimeo-texttrack-locales.json` | Vimeo text-track locale list — `{ locales: [{ code, label }] }` |

Both Continguts (browser) and Studio PHP validation read the **same files**. No duplicate copies.

On add, the server rejects unknown `id` (not in ISO registry) and unknown `vimeo_code` (not in Vimeo registry), and enforces `vimeo_code` uniqueness.

### Adding a Subtitle language (Continguts)

Producers pick a language from the searchable ISO list. The `id` and `label` are set from that selection and cannot be changed afterward (delete and re-add to replace). Free-text language codes are not allowed.

- If the selected `id` is in the Vimeo locale list → `vimeo_code` defaults to `id`; no second step.
- If not → a second dropdown asks the Producer to choose a `vimeo_code` from the Vimeo locale list. Codes already assigned to another Subtitle language are excluded.

In the Subtitle language list, show `vimeo_code` **only when it differs from `id`**.

### Sync direction

| When | Direction | What |
| --- | --- | --- |
| Add video at Continguts | **Pull once** from Vimeo | Title, thumbnail URL, tags (initial seed) |
| Edit video metadata in Continguts | **Push** to Vimeo | Title, tags (`VideoEditHandler`, existing) |
| Publication / caption upload | **Push** to Vimeo | Caption files using `vimeo_code` + `label`; server files written first |
| Bulk sync (`sync_to_vimeo.php`, button **Sincronitzar a Vimeo**) | **Push** for all catalog videos | Title, tags, all caption files; **pull** `thumbnail_url` only when missing |

The server catalog and `data/captions/` are **never overwritten** from a Vimeo pull (except the narrow thumbnail backfill above).

Pull sync for captions/metadata was rejected because Vimeo's locale list is a strict subset of our extended ISO codes; pulling destroys dialect identity and labels.

### Playback vs backup

| Concern | Source of truth |
| --- | --- |
| Preview site / future homepage player | Server Caption files + catalog metadata |
| Legacy homepage `?texttrack=` | Vimeo text tracks (backup push; no mapping layer — deprecating soon) |
| Studio editing, translation, Continguts | Server only |

Publication and all push paths write server files first, then attempt Vimeo upload. Vimeo upload failure is non-fatal (warning banner); the server copy is already saved.

### Post-deploy

After deploying the `vimeo_code` backfill, run **Sincronitzar a Vimeo** once (documented in `studio/README.md`) to re-upload all text tracks under the correct locale codes — required to fix `aeb` tracks previously uploaded under `ar`. No in-app banner or auto-run.

## Consequences

**Benefits:**

- Dialect identity (`arq`, `aeb`) is preserved on the server and in caption filenames regardless of Vimeo locale slots.
- Producers configure Vimeo mappings once in Continguts; no developer edit of PHP constants.
- Bulk push is a reliable "make Vimeo match server" repair tool after mapping fixes or failed uploads.
- One-time tag pull on video add avoids re-entering tags already set on Vimeo before intake.

**Trade-offs:**

- Two locale lists to maintain (`iso-639-3.json`, `vimeo-texttrack-locales.json`), though both change rarely.
- Legacy homepage will not auto-select dialect tracks by browser language until it is replaced by Preview.
- After changing `vimeo_code` on an unreferenced language, existing Vimeo tracks are stale until the next per-video push or bulk sync.

## Alternatives considered

| Alternative | Reason rejected |
| --- | --- |
| **Vimeo as master with extended→Vimeo mapping and pull sync** | Reverse-mapping is ambiguous when multiple dialects share one Vimeo code; pull overwrites dialect metadata. |
| **Server only, no Vimeo captions** | Legacy homepage and embed compatibility still need tracks on Vimeo. |
| **Hardcoded `LANGUAGE_MAP` in `VimeoClient` (configurable or not)** | Same information as `vimeo_code` but hidden from Producers; current map breaks `arq`/`aeb` coexistence; rejected in favour of persisted `vimeo_code` on each language entry. |
| **Legacy homepage Accept-Language → `vimeo_code` mapping** | Rejected — page deprecating in weeks; not worth the investment. |
| **Auto-run bulk push on deploy** | Rejected — surprise API calls; manual sync once after migration is sufficient. |
| **Server as master, push-only, with explicit `vimeo_code` mapping** | **Chosen.** |
