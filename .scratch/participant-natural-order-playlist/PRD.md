# Participant playlist: natural sequence order (no shuffle)

Status: ready-for-agent

## Problem Statement

When a visitor plays a Participant-only Playlist on Preview, videos still follow shuffle-oriented queue logic (or catalog appearance order). Toni wants Participant collections to play in natural clip order — `Name_1`, `Name_2`, … — with no shuffling.

## Solution

Pure Participant collection mode uses a linear Playlist sorted by numeric `participant_sequence`. Cold load starts on the natural first video (poster = queue head). Session restore keeps the current video and time but rebuilds the queue in that natural order. R2 / tag / unfiltered ALL Playlists keep today’s shuffle behaviour.

## User Stories

1. As a visitor opening `?participant=Hamida`, I want playback to start on Hamida 1, so that the collection begins at the natural first clip.
2. As a visitor pressing Next in Participant mode, I want Hamida 2 then Hamida 3 in sequence, so that I watch the person’s work in order.
3. As a visitor pressing Prev, I want to move backward through that same sequence, so that navigation is predictable.
4. As a visitor at the last clip of a Participant Playlist, I want the player to pause on the natural first clip, so that end-of-playlist matches other collections without reshuffling.
5. As a visitor whose Participant has a clip with no parseable sequence, I want that clip after all numbered ones, so that titled sequences stay intact.
6. As a visitor with two unnumbered clips for one Participant, I want those clips to keep catalog order among themselves, so that ordering stays stable.
7. As a visitor restoring a mid-Playlist Participant session after About, I want the same video and time, with Prev/Next following natural order around it, so that browsing secondary pages does not scramble the queue.
8. As a visitor entering via a Participants card click, I want the same natural-order rules as `?participant=`, so that entry path does not change behaviour.
9. As a visitor who then fixes an R2 or tag filter, I want Participant mode to clear and shuffle to resume as today, so that only pure Participant collections are linear.
10. As a visitor on an unfiltered ALL Playlist, I want fresh random order to continue as today, so that this change does not affect default browsing.
11. As a visitor on first paint with `?participant=`, I want the paused poster already to be sequence 1, so that SSR and client agree before JS runs.
12. As a visitor with sequences `2` and `10`, I want `2` before `10`, so that ordering is numeric not lexicographic.

## Implementation Decisions

- **Scope**: Pure Participant collection only (no R2/tag pins). Mutual exclusion with filters unchanged.
- **Sort key**: Numeric ascending `participant_sequence`; empty/missing sequences after all numbered; catalog/master index as tie-breaker.
- **Cold load**: Start at sort head; poster = queue head; `shuffleMode = false`.
- **Session restore**: Keep current master video + playback time; rebuild filtered indices in natural order; remap cursor; `shuffleMode = false`.
- **End of playlist**: Existing generalized rule — pause on first of current playback sequence (natural head); no reshuffle, no redirect.
- **Modules**:
  1. Participant playlist orderer (pure) — indices for a participant name in natural order (JS + PHP parity).
  2. Participant collection planner — enter/restore plans with linear mode + natural indices.
  3. Player bootstrap wiring — replace identity-as-shuffle Participant path.
  4. SSR / `vpc_participant_playlist_from_catalog` — return sequence-sorted playlist for poster correctness.

## Testing Decisions

- Good tests assert observable order and planner outputs (indices, `shuffleMode`, cursor, load index), not DOM class names.
- Unit-test the orderer and collection planner in `vimeo_playlist_logic` tests.
- PHP: assert `vpc_participant_playlist_from_catalog` returns numeric sequence order (unnumbered last).
- Prior art: `vimeo_playlist_logic.test.js`, `participant_nav_sequence_test.php`.

## Out of Scope

- Changing Participants grid card order or thumb rotation.
- Composing Participant mode with R2/tag filters.
- Changing shuffle behaviour for ALL or facet-filtered Playlists.
- Editing how `participant_sequence` is parsed from titles.

## Further Notes

Decisions locked in grill-me (Jul 2026): sequence sort; all pure Participant paths; unnumbered last; cold start on head; session keeps video/time; numeric compare; end pauses on natural first.
