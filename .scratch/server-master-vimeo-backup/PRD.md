Status: ready-for-agent

# PRD — Server-master Subtitle languages; Vimeo caption backup (ADR-0007)

## Problem Statement

Subtitle language configuration and caption files on the server can be overwritten today by `sync_from_vimeo.php`, which pulls titles, tags, and text tracks from Vimeo into `catalog.json` and `data/captions/`. That destroys dialect identity when Vimeo collapses extended ISO codes (e.g. `arq` and `aeb` both appear as `ar` on Vimeo).

Vimeo locale mapping is hardcoded in `VimeoClient::LANGUAGE_MAP`, including a bug where both `arq` and `aeb` map to `ar`, so only one dialect can exist as a Vimeo text track per video. Producers can also enter free-text language codes in Continguts, which bypasses ISO standards.

The project needs the server to be the authoritative source for Subtitle languages and Caption files, with Vimeo text tracks as a push-only backup mirror for the legacy homepage (to be deprecated in favour of the Preview player within weeks).

## Solution

Extend each Subtitle language in `studio-config.json` with a `vimeo_code` field. Replace the hardcoded `LANGUAGE_MAP` with config-driven lookups. Rework Continguts so Producers pick languages from the searchable `iso-639-3.json` dataset and, when needed, choose a Vimeo locale slot from `vimeo-texttrack-locales.json`. Repurpose bulk sync as push-only (`sync_to_vimeo.php`, button **Sincronitzar a Vimeo**). Keep a narrow one-time pull from Vimeo only when adding a new catalog video (title, thumbnail, tags as initial seed).

## User Stories

1. As a Producer, I want each Subtitle language to store a `vimeo_code` so that dialects like Algerian Darija (`arq`) and Tunisian Arabic (`aeb`) can occupy distinct Vimeo text-track slots on the same video.
2. As a Producer, I want to pick a Subtitle language from a searchable ISO list (639-1 preferred when available) so that I cannot enter invalid free-text codes.
3. As a Producer, I want the label set from the ISO list at add time so that display names stay consistent with the canonical dataset.
4. As a Producer, I want a second dropdown to choose a Vimeo locale only when my language code has no 1:1 Vimeo mapping so that the add flow stays simple for common languages like Spanish and Catalan.
5. As a Producer, I want already-assigned Vimeo codes excluded from the second dropdown so that I cannot accidentally assign the same Vimeo slot to two Subtitle languages.
6. As a Producer, I want to see the Vimeo mapping in the Subtitle language list only when it differs from the server `id` so that dialect mappings are visible without cluttering 1:1 languages.
7. As a Producer, I want to change `vimeo_code` only while no catalog video references that Subtitle language so that existing Vimeo tracks are not silently repointed.
8. As a Producer, I want tags from Vimeo copied once when I add a new video to Continguts so that I do not re-enter tags the uploader already set on Vimeo.
9. As a Producer, I want title and thumbnail pulled once at video add (existing behaviour) so that the catalog entry is usable immediately.
10. As a Producer, I want **Sincronitzar a Vimeo** to push title, tags, and all caption files for every catalog video so that Vimeo matches server state after bulk edits or mapping fixes.
11. As a Producer, I want bulk sync to fetch a missing thumbnail URL from Vimeo so that older catalog entries without thumbnails get backfilled without overwriting my metadata.
12. As a Producer, I want bulk sync never to overwrite `catalog.json` caption entries or `data/captions/` files from Vimeo so that server dialect identity is preserved.
13. As a Producer, I want Publication and caption upload to push text tracks using each language's `vimeo_code` and `label` so that Vimeo backup tracks match the server mapping.
14. As a Producer, I want Vimeo upload failures to remain non-fatal warnings so that server caption files and catalog state are always saved first.
15. As a developer, I want a README note to run **Sincronitzar a Vimeo** once after deploying the `vimeo_code` migration so that existing Vimeo tracks (especially `aeb`) are re-uploaded under the correct locale codes.

## Implementation Decisions

### Subtitle language schema (`studio-config.json`)

Each entry in `subtitle_languages` gains a required `vimeo_code` string:

```json
{ "id": "arq", "label": "Algerian Darija", "vimeo_code": "ar" }
{ "id": "aeb", "label": "Tunisian Arabic", "vimeo_code": "mt" }
{ "id": "es",  "label": "Spanish",         "vimeo_code": "es" }
```

- `id` — extended ISO code (639-1 preferred, else 639-3); canonical server key for catalog `captions[].lang`, VTT filenames `{vimeo_id}.{id}.vtt`, intake, translation.
- `label` — human-readable name for Studio and Preview caption picker.
- `vimeo_code` — Vimeo text-track locale; must be unique across all configured Subtitle languages on the account.

**Backfill:** update production `data/studio-config.json` explicitly for all eight current languages. **Runtime fallback:** `vimeo_code ?? id` when reading config (safety net for tests/fixtures only — production JSON must be explicit for dialects).

**Immutability:** `id` and `label` remain immutable after creation (set from the ISO list at add time). `vimeo_code` editable only when no catalog caption references that `id`.

### Remove `VimeoClient::LANGUAGE_MAP`

Delete the hardcoded constant. Change `uploadAndActivateTextTrack` to accept the resolved Vimeo locale code directly (rename parameter from server `lang` to `vimeoCode` for clarity). Callers resolve `vimeo_code` via `StudioConfig` before calling.

### `StudioConfig` extensions

- Persist `vimeo_code` on add; validate on add/update.
- `vimeoCodeFor(string $id): string` — returns configured `vimeo_code` or falls back to `$id`.
- `getUsedVimeoCodes(?string $exceptId = null): string[]` — for uniqueness checks and UI exclusion list.
- Block `vimeo_code` updates when `CatalogEditor` reports the language id is referenced by any caption.

### Reference data files (single source of truth)


| File                                     | Purpose                                                                         | Consumers                                      |
| ---------------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------- |
| `studio/js/iso-639-3.json`               | Searchable language picker; `{ languages: [{ code, label }] }`; 639-1 preferred | Continguts JS + PHP validation on add          |
| `studio/js/vimeo-texttrack-locales.json` | Vimeo text-track locale dropdown; `{ locales: [{ code, label }] }`              | Continguts JS + PHP validation of `vimeo_code` |


PHP reads the same JSON paths as the browser — no duplicate copies.

### New deep modules (testable in isolation)

`**Iso639LanguageRegistry`** — loads `iso-639-3.json`, exposes `isValidCode(string $code): bool`, `labelFor(string $code): ?string`.

`**VimeoLocaleRegistry**` — loads `vimeo-texttrack-locales.json`, exposes `isValidCode(string $code): bool`, `allCodes(): string[]`.

`**VimeoPushSync**` (or equivalent) — orchestrates bulk push for one or all catalog videos: push title, push tags, delete-then-upload all caption files using `vimeo_code` + `label`; pull thumbnail URL only when `thumbnail_url` is missing. No catalog or caption file writes from Vimeo except thumbnail backfill.

### Continguts UI — Subtitle languages tab

Replace free-text code/name inputs with:

1. Searchable picker over `iso-639-3.json` — selecting an entry sets `id` and `label` from the ISO dataset.
2. If selected `id` is in `vimeo-texttrack-locales.json` and not already used as another language's `vimeo_code` → set `vimeo_code = id`; hide second dropdown.
3. Else → show second searchable dropdown over available Vimeo locales (excluding codes already assigned to other Subtitle languages).
4. List view: show `vimeo_code` badge only when `vimeo_code !== id`. No inline label editing — delete and re-add to change a language.

Server-side `add-subtitle-language` action validates: `id` in ISO registry, `vimeo_code` in Vimeo registry, `vimeo_code` unique, `id` unique, format regex unchanged.

### One-time pull on catalog video add

Extend `CatalogVideoAddHandler` to call `VimeoClient::getTagNames` and seed `tags` on the new catalog entry (in addition to existing title/thumbnail pull). Server is master after creation; subsequent tag edits push via `VideoEditHandler`.

### Per-video push (unchanged direction, updated mapping)

- `VideoEditHandler` — continues to push title + tags on save.
- `CaptionUploadHandler` / `PublicationHandler` — resolve `vimeo_code` from `StudioConfig` per caption `lang` before `uploadAndActivateTextTrack`.

### Bulk sync script rename and behaviour

- Rename `studio/scripts/sync_from_vimeo.php` → `studio/scripts/sync_to_vimeo.php`.
- Extract core logic into `VimeoPushSync` for testability; script remains thin CLI wrapper with `--status-file` progress JSON (same shape as today).
- Update `BackgroundJobLauncher::launchSync`, README, and shell button label to **Sincronitzar a Vimeo**.
- Progress file path may remain `data/sync-status.json`.

### Legacy homepage

No changes. Accept `Accept-Language` / `?texttrack=` limitations for dialects until Preview replaces the homepage.

### Post-deploy operations

Document in `studio/README.md`: after deploying `vimeo_code` backfill, run **Sincronitzar a Vimeo** once to re-upload all text tracks (required to fix `aeb` tracks previously uploaded under `ar`). No in-app banner or auto-run.

### ADR-0007

Update `docs/adr/0007-server-master-subtitle-languages-vimeo-backup.md` during implementation to reflect: script rename, legacy homepage decision, `vimeo_code` edit rules, one-time tag pull, thumbnail backfill rule, and reference data files.

## Testing Decisions

Test observable behaviour through public interfaces only — not private methods or Vimeo wire formats.

### Modules to test


| Module                                        | What to test                                                                                                                                                |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Iso639LanguageRegistry`                      | Valid/invalid codes; label lookup                                                                                                                           |
| `VimeoLocaleRegistry`                         | Valid/invalid codes; list completeness                                                                                                                      |
| `StudioConfig`                                | Add with `vimeo_code`; `vimeoCodeFor` fallback; block `vimeo_code` edit when referenced; uniqueness rejection                                               |
| Subtitle language add handler                 | Rejects unknown `id`, unknown `vimeo_code`, duplicate `vimeo_code`, duplicate `id`                                                                          |
| `VimeoPushSync`                               | Pushes title/tags/captions; does not overwrite catalog captions from Vimeo; pulls thumbnail only when missing; uses `vimeo_code` not server `id` for upload |
| `CatalogVideoAddHandler`                      | Seeds tags from Vimeo on add; still pulls title/thumbnail                                                                                                   |
| `CaptionUploadHandler` / `PublicationHandler` | Upload calls use resolved `vimeo_code` (mock `VimeoClient`, assert parameter)                                                                               |


Prior art: `StudioConfigMutationTest.php`, `CaptionUploadHandlerTest.php`, `CatalogVideoAddHandlerTest.php`, `VideoEditHandlerTest.php`, `VimeoClientWriteTest.php`.

### Modules not requiring dedicated tests

- Continguts JS picker wiring (manual/browser verification acceptable).
- Thin CLI script wrapper if `VimeoPushSync` is fully covered.

## Out of Scope

- Legacy homepage `Accept-Language` → `vimeo_code` mapping layer.
- Pulling caption files or catalog metadata from Vimeo in bulk sync.
- Auto-running bulk push on deploy or first Studio load.
- In-app migration banner for post-deploy re-push.
- Changes to Preview player caption fetching (already server-hosted per ADR-0001).
- Deprecating/removing the legacy homepage (separate effort, weeks away).

## Further Notes

### Confirmed mappings for production backfill


| id  | vimeo_code |
| --- | ---------- |
| es  | es         |
| en  | en         |
| it  | it         |
| fr  | fr         |
| ca  | ca         |
| pt  | pt         |
| arq | ar         |
| aeb | mt         |


### Reference files already prepared

- `studio/js/vimeo-texttrack-locales.json` — 159 locales from Vimeo help docs (editable).
- `studio/js/iso-639-3.json` — regenerated by maintainer with `{ languages: [{ code, label }] }` shape; 639-1 preferred.

### Grilling session decisions (2026-06-07)

All items above were confirmed interactively. No open design branches remain for implementation.