Status: ready-for-agent

# Reorder bottom-bar filter buttons

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

The bottom bar (`components/bottom_bar.php`) lays out three CSS grid columns: a left group (right-aligned toward center), the fixed transport cluster (Prev/Play/Next) in the middle, and a right group (left-aligned toward center). Today's left-to-right reading order is: `DEAF+HEARING, TIPOLOGIES, LANGUAGE | Prev Play Next | SIGNES, CIUTATS` — the Language picker (interface + subtitle language selector) currently sits last in the left group, immediately adjacent to the transport cluster.

Move the Language picker to become the first/outermost button in the left group, ahead of DEAF+HEARING. Final order: `LANGUAGE, DEAF+HEARING, TIPOLOGIES | Prev Play Next | SIGNES, CIUTATS`.

This is a DOM-order change within the existing `vpc-control-secondary-l` container in `bottom_bar.php` — the grid/flex layout mechanics (`vimeo_caption_player.css:147-183`) do not need to change, only the order in which the Language picker markup (`preview_render_lang_picker(...)` call) and the DEAF+HEARING button/Typology picker markup appear.

## Acceptance criteria

- [ ] Language picker renders first (leftmost) in the bottom bar's left group, before DEAF+HEARING
- [ ] DEAF+HEARING and Typology picker follow, in that order, unchanged relative to each other
- [ ] Right group (Sign language, City) and transport cluster positions are unaffected
- [ ] No visual regression to existing responsive breakpoints (≤1024px, ≤650px, ≤500px) for this row
- [ ] Existing tests referencing button order/labels in `home_page_test.php` (or equivalent) updated to match

## Blocked by

None - can start immediately
