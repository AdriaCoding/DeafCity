Status: done

# Alphabetical language order (preview + Studio)

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

Sort oral/site languages **alphabetically by displayed label** in the preview language picker dropdown. Apply the same ordering logic to Studio wherever subtitle/oral languages are listed for display (config UI, pickers, etc.).

English may remain first or be sorted purely alphabetically — pick one rule and apply consistently; document in issue comments if product prefers EN pinned first then A–Z for the rest.

## Acceptance criteria

- [x] Preview language dropdown order is alphabetical by localized label (verify ES, CA, EN, AR entries)
- [x] Studio language list matches the same ordering rule
- [x] Existing tests for language switcher updated
- [x] No change to language resolution / completeness gate logic

## Comments

> **Alphabetical order rule (Jul 2026):** Pure A–Z by displayed label — English is not pinned first; all languages including EN sort alphabetically.
