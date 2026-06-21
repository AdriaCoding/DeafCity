Status: ready-for-agent

# About page with legacy clock, gallery, and credits

## Parent

[PRD](../PRD.md) — Preview site v3

## What to build

Move About content off the home page to **`/preview/about`**. Reuse legacy site content:

- Realtime clock (`realtime/index.html` iframe)
- Gallery (from legacy `views/_gallery.php` or equivalent assets)
- About text (already partially present in preview prototype)
- Credits, trio video, sponsor logos

Apply **Roboto** and **`rgb(0, 120, 0)`** to the About page. Coordinate with issue 06 so home no longer embeds About content.

## Acceptance criteria

- [ ] `/preview/about` is reachable from site navigation
- [ ] Page includes clock, gallery, about text, and credits matching legacy scope
- [ ] Typography and accents use Roboto and brand green
- [ ] Home page contains no About scroll region (see issue 06)

## Blocked by

None — can start immediately (coordinate home cleanup with issue 06 when both land)
