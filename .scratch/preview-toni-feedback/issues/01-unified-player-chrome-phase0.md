Status: ready-for-agent

# Unified player chrome (Phase 0)

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

Deprecate the secondary bottom-bar layout used on About and Participants. Every preview page renders the **same player chrome** as `/preview/`: filter pickers, transport cluster, site nav (`?`, Participants), and language picker.

Remove the **Reproductor** route from visible nav (home is reached via Reset or Play). Remove the **Random/shuffle** transport button. Show **Reset** with visible localized text; non-transport buttons share a uniform width derived from the longest label in the group; Prev / Play / Next keep their distinct sizing.

Reset from About navigates to neutral `/preview/` (clears filters and participant context, paused on a fresh poster per existing reset semantics).

## Acceptance criteria

- [ ] About and Participants include `bottom_bar.php` in `player` mode; `nav` mode and `preview_render_bottom_bar_nav()` are removed or unused
- [ ] Shuffle/Random button removed from transport; reset still clears all filters and collections
- [ ] Reset button shows visible text (not icon-only); aria-label retained
- [ ] Non-transport buttons (nav, filters, language, Reset) share uniform width; Prev / Play / Next unchanged
- [ ] No top border / horizontal rule above bottom chrome on any page
- [ ] Reproductor link no longer appears in site nav
- [ ] Tests updated (`home_page_test.php`, `about_page_test.php`, `participants_page_test.php`, `site_nav_test.php`)

## Blocked by

None — can start immediately
