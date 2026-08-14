Status: ready-for-agent

# Persistent player shell: About & Participants stop being page navigations

## Parent

[PRD](../PRD.md) — Preview site: in-session navigation (no full page reloads)

## What to build

Turn the Preview site into a single persistent shell that permanently owns the Vimeo
iframe. About and Participants stop being separate documents: their bodies are fetched as
route fragments (the endpoint from slice 02) and swapped into the shell, with
`pushState` / `popstate` providing real browser history. Opening a Participant's Playlist
from a Participant card becomes the same in-session route change.

The Video is never unmounted, so playback, filter facets, shuffle order, Playlist cursor
and — critically — the browser's user-activation state all simply persist. Nothing needs
to be serialized and restored, because nothing is destroyed.

### Design decision (pre-resolved, do not re-open)

When About or Participants is showing, **the Video stays mounted and keeps playing**, and
the transport bar on those routes controls it for real.

This is not a new idea being introduced; it is the existing design intent made honest.
About and Participants already render a transport bar today, and that bar is a forgery —
it mimics a player that is not on the page, reading a serialized snapshot and navigating
back to the player when pressed. Keeping the Video alive lets that same bar do what it
has always appeared to do. Visually the Video is displaced or covered by the route body,
consistent with the minimalistic design directive in `AGENTS.md`; it is not paused and
not torn down.

### Direct entry must keep working

`/preview/about`, `/preview/participants` and `/preview/?participant=…` are real URLs that
can be linked, bookmarked and shared. Entering the site cold at any of them must still
server-render a complete, correct page — the shell takes over only for subsequent
in-session route changes. Do not reduce these to client-only routes.

## Acceptance criteria

- [ ] Navigating from the player to About or Participants does not interrupt playback and does not re-create the iframe
- [ ] The transport bar on About and Participants controls the live Video directly, rather than navigating back to the player
- [ ] Opening a Participant from a Participant card switches to that Participant's Playlist in session, with no document load
- [ ] Browser Back and Forward move between routes correctly, including back to a Participant Playlist
- [ ] The address bar always reflects the current route and Website language
- [ ] Cold entry directly at `/preview/about`, `/preview/participants` and `/preview/?participant=…` still server-renders a complete correct page
- [ ] Website language switching (slices 01–02) continues to work on every route after in-session navigation
- [ ] Playback with sound continues across route changes without any re-prompt or re-gating, because user activation is never destroyed
- [ ] Filter facets, shuffle order and Playlist cursor survive route changes untouched

## Implementation notes

- The iframe must never be moved between DOM parents — reparenting an iframe re-creates
  its document in every browser, which would defeat the entire slice. Route bodies swap
  *around* a fixed iframe host, never across it.
- Reuse the slice 02 route fragment endpoint as the navigation primitive.
- Intercept internal link clicks at the shell level rather than binding per-link, so
  fragment-injected markup gets the behavior automatically.
- Do not delete the session/intent machinery in this slice even though it stops being
  exercised — slice 04 removes it separately, so a regression here stays bisectable.

### Manual verification

Play a Video, navigate to About, then Participants, then open a Participant, then press
Back several times. Playback must be continuous throughout and history must be sane.
Separately, load each route cold in a fresh tab and confirm it renders fully.

## Blocked by

- `issues/02-website-language-switch-secondary-pages.md`
