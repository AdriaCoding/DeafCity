# Participants nav: sequence label + natural casing

Status: ready-for-agent

## Problem Statement

On the preview chrome Participants button, the active participant name appears in ALL CAPS (CSS `text-transform: uppercase`) and without the video sequence number that already exists in Vimeo titles (e.g. `Hamida_1`). Toni wants the button to show `Hamida 1`, `Hamida 2`, etc., with the same casing as video titles (first letter capital, not all caps).

## Solution

Whenever the Participants chrome button shows a person name, format it as `{Name} {N}` using the sequence parsed from the video title, and render that label without uppercase transform. The generic “Participants” label stays uppercase. One label-building rule is used on the player, SSR, and secondary pages (via playback session).

## User Stories

1. As a visitor watching Hamida’s first video, I want the Participants button to read `Hamida 1`, so that I can see which clip of that person is current.
2. As a visitor advancing to the next Hamida clip, I want the button to update to `Hamida 2`, so that the label tracks the current video.
3. As a visitor in participant mode (paused or playing), I want the numbered name on the button, so that the label stays informative without needing to read the Vimeo title.
4. As a visitor on a neutral playlist while a video is playing, I want the button to show that video’s `Name N` (gray, not green), so that reflection mode matches participant mode formatting.
5. As a visitor on About or Participants after a participant-mode session, I want the same `Name N` label as on the player, so that pages do not disagree.
6. As a visitor, I want person names on the button in title-like casing (`Hamida`), not `HAMIDA`, so that they match video titles.
7. As a visitor, I want the idle “Participants” label to remain visually uppercase like other chrome buttons, so that only person names change casing.
8. As a visitor whose video title has no parseable sequence, I want the bare participant name, so that the UI never invents a number.
9. As a visitor landing with `?participant=…`, I want the first paint already to show `Name N` when the current video is known, so that there is no flash of bare name then numbered name.
10. As a visitor using the Participants grid, I want cards unchanged (one card per person, no per-video numbers), so that only the chrome button changes.

## Implementation Decisions

- **Sequence source**: Parse `N` from catalog/video `title` matching `…_Name_N_(HD|4K)` when building playlist items / SSR. Do not add `participant_sequence` to `catalog.json`.
- **Playlist enrichment**: Each playlist item exposes a numeric (or string) sequence field derived from its title (empty/absent when unparseable).
- **Single label rule**: Extend `resolveParticipantsNavState` (and PHP SSR equivalent) so any time the label is a person name, append ` ` + sequence when available. Same function for player, secondary chrome, and tests.
- **Session**: Persist participant sequence alongside participant name when saving playback session; secondary pages reuse it so label logic stays identical.
- **Filter identity**: Mode/filter continues to use bare participant name; sequence is display-only.
- **Casing**: Add a chrome modifier class (e.g. `--person-name`) with `text-transform: none` only when showing a person name; generic label keeps uppercase.
- **Scope**: Preview chrome/nav Participants button only.

### Modules

1. Participant sequence-from-title parser (PHP; JS only if needed for symmetry)
2. Playlist item enrichment with sequence
3. Shared participants nav label resolver (name + optional sequence)
4. Playback session fields for sequence
5. Chrome CSS/class toggle for person-name casing
6. SSR site-nav label resolution

## Testing Decisions

Good tests assert observable label/session behaviour through public helpers, not CSS file contents or private parse internals beyond the parser’s public API.

**Covered by automated tests:**

- Sequence parse from representative titles + missing-sequence fallback
- `resolveParticipantsNavState` returns `Name N` / bare name in participant mode, playing reflection, and secondary/session cases
- Session round-trip includes sequence used for secondary label

**Prior art:** `preview/tests/vimeo_playlist_logic.test.js`, `preview/tests/site_nav_test.php`, green-collection PHP tests.

**Light coverage:** SSR label and CSS class presence via existing PHP nav tests if practical; no new Studio tests.

## Out of Scope

- Participants grid card labels / one-card-per-video
- Studio Continguts UI
- Changing uppercase styling of other chrome buttons (Editions, Typology, etc.)
- Persisting sequence into `catalog.json` / sheets schema
- Renaming or reordering videos

## Further Notes

Extends behaviour from preview Toni feedback issue #04 (participant name on nav). Number comes from title sequence, not playlist position. Decisions locked in grill-me (Jul 2026): title sequence; person-name casing only; always current video; bare-name fallback; chrome only; SSR included; identical logic everywhere a name is shown; sequence stored in session; parse at playlist/SSR build time.
