Status: done

# Fix: PARTICIPANTS button not updating during shuffled playback

## Parent

[PRD](../PRD.md) — Preview & Studio Toni feedback (Aug 2026)

## What to build

Toni observed that the PARTICIPANTS nav button shows the current participant's name while playing a city's playlist, but not while playing a sign-language playlist. `getCollectionNavState('participants')` (`vimeo_caption_player.js:1279-1297`) and `resolveParticipantsNavState` (`vimeo_playlist_logic.js:1351`) are both facet-agnostic — they read `item.participant` off whatever's at `fullPlaylistItems[playlistIndex]` regardless of which filter produced it — so the button logic itself does not distinguish city vs. sign-language filtering.

Suspected root cause, needs verification: `advanceOnEnded()`'s shuffle-mode branch (`vimeo_caption_player.js:1216-1218`) calls `loadVideoMaster(masterIx, true).then(function () { updatePlaylistNavButtons(); })` **without** also calling `syncCollectionNavButtons()`, which is what actually refreshes the PARTICIPANTS button label. Compare with `pauseAtPlaylistHead()` (`:1192-1195`), which calls both. If confirmed, this means the PARTICIPANTS button label goes stale on every shuffle-mode auto-advance, for *any* filter — city included — and Toni's report is likely explained by testing city playlists on their first video (right after `applyFilterChange` calls `syncCollectionNavButtons()`) versus a longer LSE session that advanced further into the shuffle and hit the stale state.

## Acceptance criteria

- [x] Runtime investigation completed; the shuffle-branch hypothesis was refuted for the reported navigation symptom
- [x] The actual cause was documented and fixed: Participants navigation now clears the active playback session
- [x] Verify the fix while a Participant video is playing and after the video completes
- [x] Regression coverage added for Participants navigation clearing playback context

## Blocked by

None - can start immediately

## Comments

- Runtime verification showed the reported failure was caused by the Participants navigation preserving the active playback session, including the Participant context. The Participants page then correctly restored that context and displayed the old name.
- Fixed by clearing the playback session and navigation intent when the PARTICIPANTS button is selected. Verified during playback and after video completion; transport regression tests pass.
