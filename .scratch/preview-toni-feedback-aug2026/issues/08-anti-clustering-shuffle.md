Status: ready-for-agent

# Anti-clustering shuffle (no repeat participant, no long city runs)

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Replace the plain Fisher–Yates shuffle (`buildShuffledSequence`, `vimeo_playlist_logic.js:32`) used for every shuffled playlist (any filtered facet combination, and the base "one per city" playlist from issue #1) with an algorithm that avoids two complaints observed on the LSE (Bilbao + València) playlist: the same participant appearing back-to-back, and long unbroken runs from a single city before switching to another.

This relies on a domain fact confirmed in the Catalog: a Participant belongs to exactly one city (Edition) — nobody spans multiple cities. Two-phase algorithm:

1. **Group candidates by city.** Within each city's own bucket, order its videos with a small round-robin-by-participant pass: repeatedly pick a random participant different from the one picked immediately before, take a random unplayed video of theirs, until that city's bucket is exhausted.
2. **Interleave the city buckets round-robin.** Reshuffle the order of currently non-empty city buckets each round; take one video off the front of each bucket in turn; repeat until every bucket is empty.

Because participants don't span cities, same-participant adjacency can only occur at the tail once a single city bucket is the only one left — which step 1's within-city ordering already handles. Toni has confirmed uploads are kept roughly balanced across participants and cities, so no backtracking or constraint-solving fallback is required for the "one dominant participant/city" edge case — a plain shuffle fallback for that rare case is sufficient.

This generalizes for free to single-city filtered playlists (step 2 is a no-op with only one bucket, but the no-consecutive-same-participant improvement from step 1 still applies) and to single-participant pools (no-op, unaffected — true Participant Playlist mode keeps its existing natural-sequence-order behavior, unrelated to this change).

## Acceptance criteria

- [ ] New pure function(s) implementing the two-phase algorithm, alongside `buildShuffledSequence`/`buildShuffledSequenceWithHead` in `vimeo_playlist_logic.js`
- [ ] The base "one per city" playlist and every filtered/shuffled playlist (sign language, typology, tag, edition, or combinations) use the new algorithm in place of plain Fisher–Yates
- [ ] No two consecutive videos share the same participant, except at an unavoidable single-city tail
- [ ] No long unbroken run of videos from a single city while more than one city's bucket still has videos remaining
- [ ] Single-city and single-participant pools are unaffected in ways that would change their existing tested behavior beyond the participant-adjacency improvement
- [ ] Unit tests cover: multi-city interleaving, within-city participant ordering, and graceful behavior when a bucket is exhausted early

## Blocked by

None - can start immediately
