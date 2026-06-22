Status: ready-for-agent

# PRD — Preview player: filters & playback rework

Parent spec: [preview/tasks.md](../../preview/tasks.md) · builds on [preview-site-v3 PRD](../preview-site-v3/PRD.md) (decisions D1–D20).

## Problem Statement

The v3 build (issues #1–#7, merged) shipped the three-row chrome, category pickers, and a Spoken Language selector — but the filters don't behave as intended. The pickers read as static labels rather than reflecting what's playing, the green accent doesn't distinguish "I chose this" from "this is what's on," composing filters can lead nowhere useful, Reset restarts a video instead of clearing filters, playback stays needlessly paused after the visitor has already interacted, and the Spoken Language selector sticks by filename so it never actually sticks.

This rework redefines the filter and playback model so the home player is the seamless, self-explanatory playlist player Antoni wants — **audio plays whenever the browser allows, the pickers always tell you what's playing, and green always means "you pinned this."**

## Mental model (the rules a visitor learns without being told)

- **Grey picker = what's playing.** The three R2 pickers (Sign language, City/Edition, Typology) always display the **current video's** category, in neutral colour, updating as the playlist advances.
- **Green = you pinned it.** A picker turns green when the visitor **fixes** it as a filter; the R3 Participants/Tags button turns green when that collection is active. Green everywhere means "user-chosen context."
- **Audio whenever allowed.** The player plays with sound the moment the browser permits — i.e. after the visitor's first interaction. It's paused only before that first gesture.
- **Reset = back to neutral.** Reset clears everything and returns to a fresh, paused, unfiltered playlist.

## Decisions

These **revise** v3 decisions; the revised statement here is authoritative.

| # | Decision |
|---|----------|
| **D1′** | **Reset clears filters.** Reset clears all fixed R2 filters **and** any active participant/tag collection, returning to the unfiltered random ALL Playlist, **paused** on a fresh random poster. The previous "restart current Video from t=0" behaviour is **dropped**. |
| **D12′** | **Audio whenever the browser permits; paused only before the first gesture.** A click/tap/keypress grants sticky activation for the whole page view. Cold load (no gesture yet) = **paused poster**. The first gesture — Play, fixing a filter, clicking the video, or Prev/Next — unlocks audio; thereafter playlist advances **and** filter/collection changes **auto-play with sound**, no extra clicks. The mute/unmute control stays removed (§1.6). |
| **D14′** | **Pickers are a live readout.** The three R2 pickers show the **current video's** category value (from `studio-config.json` labels) in **neutral** colour, and turn **green only when fixed** as a filter. There is no separate Playlist-name label. |
| **D16′** | **Spoken Language = available-tracks selector.** Track selector, **never green**. The dropup lists only the spoken languages the **current video** has caption tracks for, mapped from each track's `lang` to `subtitle_languages` (region variants collapsed: `es-MX`/`es-ES` → "Spanish"). When the current video has **no** captions, the control is **present but disabled** ("No subtitles"). Captions are **on by default** (the chosen sticky language if this video has it, else the video's first available track). Stickiness is keyed by **spoken language, not filename label**; when the sticky language is absent on a video, fall back to its first available track and **snap back** to the sticky language when a later video has it. |
| **D17′** | **Cascading composition.** R2 facets compose with **AND**. Each time a filter is fixed, every **other** dropup repopulates to only the values present in the **currently-filtered** Playlist — a visitor can never compose into an empty result. Each dropup carries an **"All [category]"** entry that clears just that one filter (widening, see D22′); global **Reset** clears all (D1′). |
| **D18′** | **Filters and collections are mutually exclusive.** R2 filters compose with each other, but a participant/tag pick and the R2 filters are **one-or-the-other**: fixing any R2 filter **clears** the active participant/tag, and picking a participant/tag **clears** all R2 filters. (Collections hold 1–2 Videos; cascading filters into them is pointless.) |
| **D21** | **Green semantics.** Green = "user-pinned context" everywhere: a fixed R2 filter **or** an active participant/tag (green R3 button). Neutral/grey = passively reflecting what's currently playing. |
| **D22** | **Fix-a-filter playback (keep-if-matches).** When a filter is fixed: if the **current video satisfies** the new fixed set, **keep it playing** and rebuild the upcoming queue (respecting the shuffle toggle, current video as head); only **jump** to the filtered Playlist's first Video when the current video doesn't match. Fixing a filter, or clearing one via its **"All"** entry, **auto-plays** the resulting Playlist (post-gesture, with sound) — the widened/narrowed current video keeps playing where it still matches. **Reset is the sole exception that pauses.** |
| **D23** | **Carry play-state across navigation.** A participant click on `/preview/participants` is a gesture; landing back on `/preview/` should **auto-play** that participant's Playlist with sound rather than starting paused. |

## Out of scope

- Tags page (next sprint) — but tag selection must follow the same collection rules (D18′, D21) when it ships.
- Any change to caption typography/layout (shipped, v3 issue 02).
- Replacing the live homepage.

## Known root cause to fix

The current sticky-caption logic matches on track **label**, and labels are filenames (`Veronica_03.srt`), so stickiness never matches across Videos. D16′ requires keying on spoken language instead.

## Issue index (vertical slices)

| # | Slice | Type | Blocked by |
|---|-------|------|-----------|
| R1 | Gesture-gated audio playback (D12′, D23) | AFK | — |
| R2 | Filter pickers: live readout, green-when-fixed, cascading composition, keep-if-matches (D14′, D17′, D18′, D21, D22) | AFK | R1 |
| R3 | Reset clears all filters & collections → paused ALL (D1′) | AFK | R2 |
| R4 | Spoken Language by available tracks + sticky-by-language (D16′) | AFK | — |
| R5 | Green active state for participant/tag collections (D21) | AFK | R2 |

All slices edit the shared player component (`vimeo_caption_player.{php,css,js}`, `vimeo_playlist_logic.js`) — **serialize** them in the dependency order above even where blockers don't force it.
