Status: ready-for-agent

# Participant name always on Participants nav button

## Parent

[PRD](../PRD.md) — Preview Toni feedback (Jul 2026)

## What to build

The Participants nav button must show the **participant name** (green active state) whenever the current playback context is a single participant — not only when entered via `?participant=Name`.

Today, narrowing via Cities or Sign Language filters clears participant mode and reverts the button to the generic label. Extend detection so that when the active filtered playlist contains exactly one distinct participant (or the current video's participant is unambiguous), the nav button shows that name.

Name must remain visible during **Play** mode (not only when paused).

## Acceptance criteria

- [ ] Enter via Participants grid → name on button (existing behaviour preserved)
- [ ] Enter via filter narrowing to one participant's videos → name on button
- [ ] Name visible while video is playing
- [ ] Multiple participants in playlist → generic "Participants" label
- [ ] Reset clears name back to generic
- [ ] Tests in `site_nav_test.php` or JS logic tests cover filter-entry path

## Blocked by

- [01-unified-player-chrome-phase0.md](01-unified-player-chrome-phase0.md)
