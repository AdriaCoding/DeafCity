Status: ready-for-agent

# Fix: PARTICIPANTS button not updating during shuffled playback

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Toni observed that the PARTICIPANTS nav button shows the current participant's name while playing a city's playlist, but not while playing a sign-language playlist. `getCollectionNavState('participants')` (`vimeo_caption_player.js:1279-1297`) and `resolveParticipantsNavState` (`vimeo_playlist_logic.js:1351`) are both facet-agnostic — they read `item.participant` off whatever's at `fullPlaylistItems[playlistIndex]` regardless of which filter produced it — so the button logic itself does not distinguish city vs. sign-language filtering.

Suspected root cause, needs verification: `advanceOnEnded()`'s shuffle-mode branch (`vimeo_caption_player.js:1216-1218`) calls `loadVideoMaster(masterIx, true).then(function () { updatePlaylistNavButtons(); })` **without** also calling `syncCollectionNavButtons()`, which is what actually refreshes the PARTICIPANTS button label. Compare with `pauseAtPlaylistHead()` (`:1192-1195`), which calls both. If confirmed, this means the PARTICIPANTS button label goes stale on every shuffle-mode auto-advance, for *any* filter — city included — and Toni's report is likely explained by testing city playlists on their first video (right after `applyFilterChange` calls `syncCollectionNavButtons()`) versus a longer LSE session that advanced further into the shuffle and hit the stale state.

## Acceptance criteria

- [ ] Confirm (or refute) that `advanceOnEnded()`'s shuffle branch is missing the `syncCollectionNavButtons()` call
- [ ] If confirmed: add the missing call so the PARTICIPANTS button label updates on every shuffle-mode auto-advance, not just at explicit filter changes and end-of-playlist
- [ ] Verify the fix on both a city-filtered playlist and a sign-language-filtered (LSE) playlist, confirming the participant name updates correctly as playback advances through several videos in each
- [ ] If the hypothesis is refuted, document the actual cause found and fix that instead
- [ ] Regression test added covering PARTICIPANTS button state across multiple shuffle-mode advances

## Blocked by

None - can start immediately
