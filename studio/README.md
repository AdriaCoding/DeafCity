# Studio

Private web application where Producers process Videos (intake, subtitle editing, translation, tagging, publication). See `CONTEXT.md` for domain terms.

## Pipeline

All six pipeline slices are **shipped** (see `docs/studio-pipeline-features.md`). PRDs under `.scratch/*/PRD.md`.

**Publication ops:** `data/catalog.json` and `data/captions/` must be writable by the web server user (`www-data`). Vimeo token needs `private`, `upload`, `edit` scopes — see `config/config.example.php`. Test with `php studio/scripts/test_vimeo_publish.php`.

**Catalog sync:** Pull title, tags, and captions for every video in `catalog.json` from Vimeo. Downloads VTT files to `data/captions/` and overwrites each catalog entry in place.

```bash
# Run from the public_html root
php studio/scripts/sync_from_vimeo.php
```

## UI language

**All Studio controls and user-visible text must be written in Catalan.**

This includes:

- View templates under `views/` (labels, buttons, headings, help text, confirm dialogs)
- Client-side strings in `js/`
- User-facing error and validation messages returned by PHP handlers (`IntakeHandler`, `CaptionFileIntegrityChecker`, `WebVttValidator`, `VimeoIdParser`, `VimeoClient`, etc.)
- Pipeline step labels in `PipelineSteps.php`

Set `lang="ca"` on HTML documents. Keep product and brand names as proper nouns where appropriate (e.g. **Studio**, **DEAF.city**, **Vimeo**, **WebVTT**).

Internal code comments, PHPUnit assertions on non-UI behaviour, and developer documentation may remain in English.
