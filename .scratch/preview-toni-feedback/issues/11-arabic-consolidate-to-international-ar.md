Status: ready-for-agent

# Consolidate Arabic: remove Darija/Tunisian, use international Arabic (`ar`)

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

Remove **Algerian Darija** (`arq`) and **Tunisian Arabic** (`aeb`) as app languages. Replace with a single **international Arabic** language (`ar`).

Scope spans preview and Studio:

- `studio-config.json` — replace `arq` / `aeb` entries with one `ar` entry (label e.g. "Arabic" / "Árabe"; `vimeo_code: "ar"`)
- `ui-localizations.json` — migrate or merge `arq` / `aeb` translation slots into `ar`; remove dialect keys when complete
- `preview/lib/preview_locale.php` — RTL detection for `ar` (replace `arq` / `aeb` checks)
- `preview/lib/language_resolver.php` — simplify Arabic BCP-47 mapping (no dialect split / `__arabic_first_ready__` multi-dialect loop unless still needed for Accept-Language)
- Catalog caption tracks referencing `arq` / `aeb` — migrate to `ar` where applicable
- Studio UI lists and translation targets — drop dialect ids; wire `ar` as translation target if oral Arabic subtitles are still produced
- Tests: `language_resolver_test.php`, preview locale tests, any Studio language tests

**Product note for Toni:** Unified web + subtitle language control remains; users now pick one Arabic (MSA/international) instead of Darija or Tunisian variants.

## Acceptance criteria

- [ ] Language picker shows **Arabic** (`ar`), not Algerian Darija or Tunisian Arabic
- [ ] `?lang=ar` resolves correctly; RTL layout applies to page text
- [ ] No remaining references to `arq` or `aeb` in config, resolver, or active UI paths (grep clean or documented exceptions for legacy catalog data)
- [ ] Existing Arabic UI strings available under `ar` (merged from best complete dialect copy or re-translated — document choice in PR)
- [ ] Studio subtitle language list matches preview
- [ ] Tests pass

## Blocked by

None — can start immediately (orthogonal to issue 01; may ship in same PR as issue 09)

## Comments

> Decision confirmed Jul 2026 — replaces pending Alger/Tunis dialect scope question (#14).
