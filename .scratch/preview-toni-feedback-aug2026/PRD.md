# Preview & Studio — Toni feedback (Aug 2026)

Consolidated decisions from grill session over Toni's Jul/Aug 2026 feedback dump (preview website + Studio).

## Scope

Nine implementation issues below. Two items were dropped outright, four were found already implemented during the grill session, and two are parked pending further discussion with Toni. A small content/ops backlog (subtitle uploads) is tracked separately — it is Toni's own follow-up action, not dev work.

## Decisions

### Base playlist (#1)

The unfiltered/neutral state ("ALL") permanently retires the full-catalog shuffle. It is always built as: 1 random participant per city (edition), 1 random video of theirs, with the resulting per-city entries shuffled into random order. This applies to cold entry, Reset, and clearing all filters — there is no path left that shows the old full-catalog shuffle.

Regeneration: fresh only on a true cold start (the existing `PLAYBACK_SESSION_KEY` sessionStorage check is absent — new tab, or after Reset). A same-tab refresh mid-video continues to restore via the existing session-restore path, unchanged — no new bypass logic, no regression to the resume-intent contract in `CONTEXT.md`.

Out of scope: thumbnail selection/fallback logic — Toni withdrew this requirement; it is no longer needed anywhere in the product.

### Redundant filter marking (#2)

Generalizes the existing "active" (green) picker styling. Today `data-active` is only set `true` when a facet is explicitly `fixed` by the user (`resolveFilterPickerReadout`). New rule: also mark a facet's live (non-fixed) readout as active whenever other currently-active filters have narrowed its possible values to a strict subset of the full unconstrained set — using the existing `distinctFacetValuesInSubset` computation, compared against the full facet value set. This covers both LSC→Barcelona (narrowed to exactly one value) and LSE→Bilbao/València (narrowed to two values) with one mechanism, satisfying Toni's requirement that the city button show as selected for any sign-language pick, not just single-city ones.

Clicking the clear/"all" option on such a redundant-but-not-fixed field is a **true no-op by construction**: `filterState[facet]` was already `null`, so `applyFilterChange` sets it to the same value, recomputes an identical filtered set, and the badge stays green. No new guard code needed. Confirmed acceptable as-is — no additional feedback required on click.

Filter intersection (e.g. ACUDITS + LSC) already works today via `videoMatchesFilterState`/`recomputeFilteredMasterIndices` — no work needed, Toni was describing existing behavior.

### Keyboard shortcuts (#3)

Space/`<`/`>` currently firing on Prev/Play/Next are **Vimeo Player's own native embed keyboard shortcuts** (no app-level keydown handling exists today) — an uncoordinated second control path, not our code. New plan: disable Vimeo's native keyboard shortcuts on the embed, and implement one consistent site-wide mapping — Space toggles the app's Play/Pause action (not raw video pause), Left/Right move Prev/Next — active on every preview page (About/Participants included), consistent with the transport cluster always being visible per the Jul 2026 PRD. Drop `<`/`>` entirely. Add native `title` attributes to the three transport buttons so the shortcut is discoverable on hover.

### Button reorder (#4)

Final left-to-right order: `LANGUAGE, DEAF+HEARING, TIPOLOGIES | Prev Play Next | SIGNES, CIUTATS`. The Language picker (interface + subtitle language selector) moves out of its current slot (last item on the left, adjacent to the transport cluster) to become the first/outermost button on the left.

### Mobile top-only height crop (#5)

Today's mobile width-crop works via a narrower `.video-shell` `aspect-ratio` at `≤650px`, with the video scaled to `height:100%` and centered — width overflow is what gets clipped. A top-only height crop needs a different mechanism: scale/shift the video so it overflows vertically too, biased so cropping only ever removes from the top edge. Build one global, easily-tunable CSS custom property (not per-video) at the same `≤650px` breakpoint. **HITL**: the actual crop amount must be visually tuned and approved by Toni against Mahieddine's and Mustapha's videos (the tall participants) before this can be considered done — ship with a conservative default and treat tuning as a follow-up gate, not a blocker to landing the mechanism.

### Human-readable caption filenames (#6)

On-disk caption files are currently named `{vimeo_id}.{lang}.vtt` (set ad hoc by each caller — `batch_translate_captions.php` and others — there's no shared naming function). New convention: `{video title, with trailing resolution/quality suffix stripped}_{LANGCODE}.vtt`, reusing the catalog's existing `title` field with its full 4-digit year (e.g. `2020_VALENCIA_Aurora_1_EN.vtt`). This task includes renaming every already-existing caption file on disk and updating each video's `captions[].file` reference in `catalog.json` to match.

### Anti-clustering shuffle (#8)

Generalizes to every shuffled playlist (not just LSE), replacing the plain Fisher-Yates shuffle used today. Two-phase algorithm, justified by a domain fact: a participant belongs to exactly one city in this catalog, so grouping by city first makes both of Toni's complaints (same participant back-to-back, long runs of one city) fall out of one mechanism:

1. Group candidates by city. Within each city's own bucket, order videos with a small round-robin-by-participant pass (pick a random participant different from the last one picked, take a random unplayed video of theirs).
2. Interleave the city buckets round-robin: reshuffle the order of currently non-empty cities each round, take one video off the front of each city's queue in turn, repeat until empty.

Same-participant adjacency can then only happen at the unavoidable tail once a single city bucket is all that's left — handled by the within-city ordering from step 1. Toni confirmed the "one dominant participant/city" edge case doesn't occur in practice (uploads are kept roughly balanced), so no backtracking/constraint-solving fallback is needed — a plain shuffle fallback for genuinely unsatisfiable edge cases is enough.

### No code change — already done, explain to Toni

| Topic | Explanation |
|---|---|
| Filter intersection (ACUDITS + LSC) | Already works via existing `videoMatchesFilterState`/`recomputeFilteredMasterIndices`. |
| Batch-translate button for a new Studio language | Already shipped: "Tradueix subtítols pendents" in `continguts.php`, backed by `batch_translate_captions.php`. Also already confirmed to sync only the newly-translated language(s) to Vimeo, never re-touching existing ones. |
| About page responsive paragraph text | Already done. |
| Credits bold formatting | Current state (only the two numbers bold on the participants/messages line) is correct as-is — no further bolding needed. |

### Dropped — will not do

- Thumb selection/fallback per participant (search other videos for a thumb if the initial pick lacks one). Superseded by Toni's own guarantee and then withdrawn entirely as a requirement.
- Skip-first-3-seconds-of-translation (protecting the title-card cue from AI translation). Decided against.

### Parked — needs more discussion with Toni before scoping

- **Responsive button text on MacBook Air**: report too vague to act on (unclear whether the symptom is wrapping, clipping, or overlap). Needs a concrete repro/screenshot from Toni.
- **New subtitle languages** (Portuguese Brazil, Valencian, Mexican Spanish): straightforward to add to `studio-config.json`'s global `subtitle_languages` list, but doing so would also make the existing batch-translate button apply them to every edition, not just São Paulo/Mexico/Valencia respectively, since the list isn't edition-scoped today. Needs a decision from Toni on whether that's acceptable or whether subtitle languages should become edition-scoped (a bigger change).

### Content/ops — not dev work

Translations backlog: Mexico and Marseille need French + English subtitles uploaded (no new languages needed, can proceed now via existing Studio tools); São Paulo needs Portuguese Brazil (blocked on the parked new-languages decision above). This is Toni's own producer workflow using the existing Studio, not a coding task.

## Issues

1. `01-base-playlist-one-per-city.md` — ready-for-agent
2. `02-redundant-filter-green-marking.md` — ready-for-agent
3. `03-sitewide-keyboard-shortcuts.md` — ready-for-agent
4. `04-reorder-bottom-bar-buttons.md` — ready-for-agent
5. `05-mobile-top-only-crop.md` — ready-for-agent (HITL tuning gate)
6. `06-human-readable-caption-filenames.md` — ready-for-agent
7. `07-investigate-infinite-loading-spinner.md` — needs-triage (recommend a live-reproduction grill-me/investigation session before implementation — root cause unconfirmed)
8. `08-anti-clustering-shuffle.md` — ready-for-agent
9. `09-fix-participants-button-stale-on-shuffle.md` — ready-for-agent
