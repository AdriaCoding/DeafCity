# Preview site: About nav button shows "?" in every locale

The Preview site player chrome labels the About route with a single visible character — **`?`** — in all configured spoken-language locales. It is never translated to "About", "Sobre", "À propos", or equivalent.

## Why this came up

**Toni's design decision.** The project's artistic director chose **«?»** as the nav affordance for this section — in navigation copy, checklists, and the legacy site. That is intentional, not a placeholder. Implementation must follow it; do not substitute localized "About" strings or "improve" the label without Toni's explicit approval.

The section holds project meta-information (credits, context, gallery), not a conventional "About us" page. The single glyph keeps the bottom bar compact and matches the established visual language Toni defined for DEAF.city.

## Decision

1. **`player.nav.about` is always `"?"`.** Every entry in `data/ui-localizations.json` for this key uses `"?"`, including English. Do not seed or translate localized equivalents.
2. **Respect Toni's choice over i18n convention.** Other nav labels are localized; this one is exempt by design authority, not oversight.
3. **The route and page content stay localized.** Only the **nav button label** is fixed. Page `<title>`, body copy, credits, and other About-page strings continue to use normal locale keys (e.g. `about.page_title`).
4. **No separate aria-label override.** The link's accessible name is the same visible `"?"` text. Screen-reader users hear the punctuation mark; fuller context lives on the About page itself once navigated.

## Consequences

- The About control reads consistently across languages and fits the uniform chrome button width grid.
- Re-running `studio/scripts/extract_ui_localizations.php` must not reintroduce translated About labels — the manifest English string is `"?"` and existing non-English values for this key should remain `"?"`.
- Documentation and issue trackers should refer to this section as **«?»** or **About (`?`)**, not by locale-specific nav strings.
- Future contributors should treat `"?"` as a fixed design constraint from Toni, not a string open to localization review.

**Considered options:**

- **Localized words ("About", "Sobre", …)** — rejected. Contradicts Toni's explicit design decision; longer strings also break visual parity with other chrome buttons.
- **`?` visible + localized aria-label** — rejected for now. Adds complexity and second-guesses Toni's single-glyph affordance without his request to do so.
