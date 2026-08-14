Status: ready-for-agent

# Website language switch without a page reload (About & Participants)

## Parent

[PRD](../PRD.md) — Preview site: in-session navigation (no full page reloads)

## What to build

Extend the no-reload Website language switch from slice 01 to the About and Participants
pages, so the shared chrome picker behaves identically everywhere.

These pages differ from the player page in one way that matters: they carry localized
*prose* — the About narrative, the credits, the gallery captions — which lives in PHP
views and is not part of the chrome string map. Key-tagging every paragraph would be
both tedious and fragile, so instead add a read-only endpoint that renders a route's page
body for a requested Website language and returns it as an HTML fragment. The client
swaps the fragment into the page container and then applies the slice 01 locale update
for the surrounding chrome.

This endpoint is deliberately more than a slice-02 convenience: slice 03 reuses it as the
primitive for navigating between routes without a document load. Build it as a general
"render route body at language" capability, not as an About-specific translation hook.

Scroll position, and any gallery or clock state that can be preserved, should survive the
swap where doing so is straightforward; the swap must not restart the embedded clock
iframe unnecessarily.

As in slice 01, a failed request falls back to today's `?lang=` navigation.

## Acceptance criteria

- [ ] Changing Website language on About updates prose, credits and chrome in place, with no page load
- [ ] Changing Website language on Participants updates chrome and any localized labels in place, with no page load
- [ ] Participant names and thumbnails are unaffected by the switch (they are Catalog data, not localized copy)
- [ ] Fragment content for a route and Website language is identical to that route's server-rendered body at the same `?lang=`
- [ ] Arabic switches these pages to RTL live, matching a cold load
- [ ] `?lang=` is updated via `replaceState` on both pages
- [ ] The embedded clock iframe on About is not needlessly re-created by a language switch
- [ ] Endpoint failure falls back to the existing `?lang=` navigation
- [ ] The endpoint is route-general (About and Participants both served by the same mechanism), ready for reuse as navigation in slice 03

## Implementation notes

- Reuse the slice 01 locale payload for chrome; this slice adds the body fragment on top
  rather than replacing that mechanism.
- Render fragments through the same view includes the full pages use, so there is one
  rendering path and no second source of truth for page body markup.
- Tests: PHP-level coverage that a rendered fragment for a route+language matches the
  corresponding region of the full page render, following the existing
  `about_page_test.php` / `participants_page_test.php` output-assertion pattern.

### Manual verification

Switch Website language on both pages and confirm prose, credits, chrome and direction
all update together with no flash and no stale strings.

## Blocked by

- `issues/01-website-language-switch-player-page.md`
