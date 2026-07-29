# ADR-0007 — Server as master for Subtitle languages; Vimeo as caption backup

**Date**: 2026-06-07
**Status**: Accepted
**Relates to**: [ADR-0001](0001-server-hosted-subtitles.md) (server-hosted caption playback), [ADR-0003](0003-producer-uploads-video-to-vimeo-directly.md) (video on Vimeo before intake)

---

## Context

The server (`data/catalog.json`, `data/captions/`, and `data/studio-config.json`) is authoritative for Subtitle languages and Caption files. Vimeo text tracks are a backup mirror for legacy embeds and external tools, not a second master.

The previous model stored a server `id` and a separate `vimeo_code` for each configured Subtitle language. It was intended to preserve dialect-specific server IDs where Vimeo did not accept the same locale. The active configuration now uses only Subtitle language IDs that Vimeo accepts directly, so the mapping is redundant and creates inconsistent behavior across configuration, filenames, Caption uploads, and sync.

The Preview site plays server Caption files ([ADR-0001](0001-server-hosted-subtitles.md)). Vimeo tracks remain useful as a pushed backup, but are never the source of Caption data or Subtitle-language metadata.

## Decision

### Canonical Subtitle language ID

Each `subtitle_languages` entry in `studio-config.json` has exactly two fields:

| Field | Meaning |
| --- | --- |
| `id` | The canonical language ID used for Catalog `captions[].lang`, Caption filenames, Studio intake, translation, Preview selection, and Vimeo text-track uploads. |
| `label` | The immutable, human-readable name shown in Studio and the Preview caption picker. |

`vimeo_code` is not persisted or exposed by Studio. Vimeo upload uses the Caption language ID directly.

International Arabic (`ar`, label `Arabic`) is the only configured Arabic Subtitle language. ISO registry entries such as `arq` and `aeb` remain valid reference data, but are not configured Subtitle languages.

### Reference data and language management

| File | Purpose |
| --- | --- |
| `studio/js/iso-639-3.json` | Searchable ISO language registry — `{ languages: [{ code, label }] }` |
| `studio/js/vimeo-texttrack-locales.json` | Vimeo text-track locale registry — `{ locales: [{ code, label }] }` |

Continguts and Studio PHP validation use these same registries. A Producer can add a Subtitle language only when its ID exists in both registries. The selectable list is their code intersection; free-text IDs and a separate Vimeo-locale picker are not allowed.

Configured IDs remain immutable. A Subtitle language can be removed only when existing Catalog-reference safeguards allow it.

### Sync direction

| When | Direction | What |
| --- | --- | --- |
| Add Video at Continguts | **Pull once** from Vimeo | Title, thumbnail URL, tags as an initial seed |
| Edit Video metadata in Continguts | **Push** to Vimeo | Title and tags |
| Publication / Caption upload | **Push** to Vimeo | Server-written Caption file using its canonical language ID and label |
| Bulk sync (`sync_to_vimeo.php`, **Sincronitzar a Vimeo**) | **Push** for all Catalog Videos | Title, tags, and all Caption files; pull `thumbnail_url` only when absent |

The Catalog and `data/captions/` are never overwritten from Vimeo, except for the narrow missing-thumbnail backfill above. Publication and every push path save the server Caption file first; a Vimeo failure is non-fatal and reported as a warning.

### Playback and backup

| Concern | Source of truth |
| --- | --- |
| Preview site and future homepage player | Server Caption files and Catalog metadata |
| Legacy homepage `?texttrack=` | Vimeo text tracks, maintained by push-only backup sync |
| Studio editing, translation, and Continguts | Server only |

## Consequences

**Benefits:**

- One ID is used consistently across configuration, Catalog data, filenames, Studio workflows, Preview selection, and Vimeo uploads.
- Producers can select only Subtitle languages that can be synced to Vimeo without a hidden mapping.
- Bulk sync remains a reliable way to make Vimeo’s backup tracks match the server.
- One-time Vimeo metadata pull on Video add avoids re-entering title and tags already present on Vimeo.

**Trade-offs:**

- The ISO and Vimeo registries remain separate datasets and must be maintained as Vimeo changes its accepted locales.
- A valid ISO code that Vimeo does not accept cannot be configured as a Subtitle language.
- Vimeo remains a limited backup representation; it does not define the Catalog or Caption files.

## Alternatives considered

| Alternative | Reason rejected |
| --- | --- |
| **Vimeo as master with pull sync** | Pulling can overwrite server-owned Caption data and language metadata. |
| **Server-only Caption files with no Vimeo tracks** | Legacy embeds and external tools still need Vimeo text tracks. |
| **Persisted or hardcoded server-to-Vimeo mapping** | It duplicates the canonical ID, permits unusable language choices, and adds hidden translation behavior. |
| **Allow a Producer to choose an unrelated Vimeo locale** | A Caption should never be uploaded under a different language identity. |
| **Auto-run bulk sync on deploy** | It would make unexpected external API writes; Producers run sync deliberately. |
