Status: ready-for-agent

# Language: English persistence, no video jump, gallery i18n

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

**English persistence (C1):** Selecting English from the language picker must reliably switch UI to English. Today, English URLs omit `?lang=en`, so reload falls back to `Accept-Language` auto-detect and may revert to CA/ES. Fix with explicit `?lang=en`, session cookie, or equivalent — consistent with other languages.

**No video jump on language change (C1 related / #16):** Changing site language while watching a video must not advance to a different video. Preserve playlist index and filter state across the reload (sessionStorage handoff, same approach as issue 02).

**Gallery localization (C2):** Move About gallery image captions from hardcoded English in `gallery.json` into `ui-localizations.json` (or per-image localized fields), rendered via `preview_t()`.

## Acceptance criteria

- [ ] Browser with `Accept-Language: ca` on `/preview/?lang=es` → pick English → UI is English on reload
- [ ] Language change mid-playback resumes same video (same vimeo id / playlist index)
- [ ] Gallery captions render in active UI language for all configured languages (fallback EN)
- [ ] `language_resolver_test.php` or new test covers English explicit selection
- [ ] Studio extract script updated if new i18n keys added

## Blocked by

- [02-transport-functional-secondary-pages.md](02-transport-functional-secondary-pages.md) (shared sessionStorage pattern; can parallelize if schema agreed)
