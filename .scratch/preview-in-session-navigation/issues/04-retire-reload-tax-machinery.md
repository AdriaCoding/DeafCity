Status: ready-for-agent

# Retire the reload-tax session machinery

## Parent

[PRD](../PRD.md) — Preview site: in-session navigation (no full page reloads)

## What to build

With slices 01–03 landed, nothing in the Preview site reloads during a browsing session.
Every mechanism that existed only to survive a reload is now dead weight and should be
removed:

- the **fake transport bar** script on About and Participants, now that the real transport
  bar controls the live Video;
- the **playback session snapshot and restore** path — building, serializing, parsing and
  replaying a snapshot of Playlist cursor, shuffle state, filter facets and playback
  position;
- the **`vpc-nav-intent` handshake**, including the resume-intent decision ADR-0013
  introduced;
- the **user-activation forgery** (`vpc-playback-activated`, `vpc-gesture-activated` and
  the Participant gesture carry) — a gesture that is never destroyed does not need to be
  reconstructed.

This is a deletion slice. There is no user-visible change; success is that behavior is
identical with several hundred lines gone.

### Care required

Two things must be separated carefully rather than deleted wholesale:

**Same-tab refresh is not the same as in-session navigation.** A visitor pressing F5 still
gets a genuine cold load. Decide deliberately whether a hard refresh should resume where
it left off or start fresh, state the answer in the ADR, and make the code match. If any
resume-on-refresh behavior is kept, keep the minimum needed for exactly that and delete
the rest.

**Some helpers in the playlist logic module are shared.** Filter composition, shuffle
planning and Playlist cursor helpers are used by live playback, not just restore. Remove
only what is genuinely restore-specific; verify by test coverage rather than by name.

### Docs

Record the retirement in an ADR: ADR-0013's resume intent is fully obsolete once no path
reloads, and the reasoning belongs on the record so a future reader understands why the
machinery existed and why it stopped being needed. Update `CONTEXT.md` to drop or rewrite
the `Language switch resume intent` contract accordingly.

## Acceptance criteria

- [ ] The fake transport bar script is deleted and no route loads it
- [ ] The playback session snapshot/restore path is removed, along with its sessionStorage keys
- [ ] The `vpc-nav-intent` handshake is removed
- [ ] The user-activation forgery keys and the Participant gesture carry are removed
- [ ] Playback with sound still works across every in-session route change and Website language switch
- [ ] Same-tab hard refresh behaves as deliberately decided and documented, on every route
- [ ] Playlist logic helpers still used by live playback are retained; the test suite passes with no coverage lost
- [ ] Existing tests that asserted restore behavior are removed or rewritten to assert the new behavior — not left passing vacuously
- [ ] ADR recorded and `CONTEXT.md` updated

## Implementation notes

- Work by deleting an entry point first and letting the test suite and dead-code search
  reveal what becomes unreachable, rather than grepping for key names and pulling threads.
- `vimeo_playlist_logic.test.js` is large and contains substantial restore-path coverage;
  those cases should be deliberately triaged one by one, since a test that still passes
  after its subject is deleted is a test that was asserting nothing.

### Manual verification

Full pass over every route and Website language with sound on, confirming no re-prompt,
no lost playback, and no console errors from removed keys.

## Blocked by

- `issues/03-persistent-player-shell.md`
