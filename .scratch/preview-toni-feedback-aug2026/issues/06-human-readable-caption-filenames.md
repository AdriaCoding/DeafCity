Status: done

# Human-readable caption filenames

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Caption files saved to `data/captions/` are currently named `{vimeo_id}.{lang}.vtt` (e.g. `1211722064.en.vtt`), set independently by each caller — at minimum `batch_translate_captions.php:199` builds this pattern, and other Studio caption-writing call sites (`CaptionUploadHandler`, `SubtitleEditorHandler`, `TranslationRunner`/`translate.php` output paths) follow the same or an equivalent convention. There is no single shared naming function today.

Introduce one shared naming convention and use it everywhere a caption filename is generated: `{video title, with the trailing resolution/quality suffix stripped}_{LANGCODE}.vtt`, reusing the Catalog's existing `title` field with its full 4-digit year as already stored (e.g. title `2020_VALENCIA_Aurora_1_4k` → caption filename `2020_VALENCIA_Aurora_1_EN.vtt`). The resolution/quality suffix (`_4k`, `_HD`, etc. — whatever trailing segment follows the participant's sequence number) must be stripped before appending the language code.

This task includes a one-time migration: rename every already-existing file in `data/captions/` to the new convention, and update the corresponding `captions[].file` entry for each video in `catalog.json` to match. Run this as `www-data` or fix ownership afterward per `AGENTS.md`'s `data/` ownership rule.

**Also add a "download `data/` as zip" button in Studio Continguts**, placed between the existing "Sincronitza desde Google Sheet" and "Tradueix subtítols pendents" buttons (`studio/views/continguts.php:653-656`, ids `sheet-sync-trigger` and `batch-translate-trigger`). Clicking it zips the entire `data/` directory (catalog, captions, studio-config, ui-localizations, etc. — the whole tree) server-side and streams it back as a file download, giving Toni/Producers a one-click way to pull a full local backup — directly useful right around this issue's filename migration, but a standing capability afterward too.

## Acceptance criteria

- [x] A single shared function derives the caption filename from a video's title and language code, used by every caption-writing call site (batch translation, single-video translation, manual upload, Subtitle Editor saves)
- [x] New translations/uploads are written with the new naming convention
- [x] All pre-existing caption files on disk are renamed to the new convention
- [x] `catalog.json`'s `captions[].file` references are updated to match the renamed files, for every affected video
- [x] Existing captions continue to serve correctly on the Website and in Vimeo sync after the rename (no broken references)
- [x] `data/captions/` files remain writable by `www-data` after the migration (ownership fixed if the migration script ran as root)
- [x] A new button sits between "Sincronitza desde Google Sheet" and "Tradueix subtítols pendents" in Studio Continguts, labeled clearly (Catalan, matching surrounding button style)
- [x] Clicking it downloads a zip archive of the entire `data/` directory as it currently stands on disk
- [x] The zip generation runs server-side without exposing any path traversal or arbitrary-file-read risk (archive strictly scoped to the `data/` directory)

## Blocked by

None - can start immediately

## Comments

> **Migration blast radius (Aug 2026):** Only one video in `catalog.json` (`1211691420`) had a non-empty `captions[]` array, so the catalog-driven migration only renamed 8 files. The other 31 files in `data/captions/` were orphans (no catalog entry referenced them, mostly leftovers from re-added/removed videos, plus a few legacy Vimeo-style codes like `en-x-autogen`/`es-MX`). Per Toni's call, these were deleted rather than renamed (backed up first) since there's no title to derive a new name from.
>
> `CaptionFormatParityTest` was reading one of those orphans directly off `data/captions/` as a test fixture — deleting it broke the test. Copied it into `studio/tests/fixtures/production_sample.vtt` and repointed the test, so it no longer depends on mutable production data.
