# Preview player: DEAF+HEARING tag filter modeled as a generic facet

The Preview player's **DEAF+HEARING** chrome button filters the Playlist to Videos carrying the Catalog Tag `DEAF&HEARING` (curated deaf–hearing crossover humour). It is implemented as one slot of the existing `filterState` object, not a standalone boolean, and its label deliberately does not match its storage key.

## Why this came up

The chrome already rendered a **DEAF+HEARING** button as a disabled, always-"active"-looking stub — it did nothing, and the player playlist payload didn't even carry Tags to filter on. Wiring it up raised three questions with lasting consequences for how any future single-Tag chrome control should be built:

1. Model it as a one-off boolean, or reuse the existing Sign language / Edition / Typology facet machinery?
2. How should it compose with the mutually-exclusive **Participants** collection mode, especially when activated from secondary pages (About / Participants) that have no live player?
3. What happens on a cold load if a stale "on" flag is sitting in the session?

## Decision

1. **Generic single-slot tag facet, not a bespoke boolean.** `filterState` gains `tag: null | string`; the button pins the literal value `"DEAF&HEARING"`. Unlike the existing R2 facets (Sign language / Edition / Typology), which match by equality, `tag` matches by **array membership** (`item.tags` includes the value). Reusing `filterState` means participant mutual-exclusion, Reset-clears, and session persistence all fall out for free instead of being reimplemented; a second crossover Tag later is configuration, not re-plumbing.
2. **Display label and storage key intentionally differ.** The chrome always reads **DEAF+HEARING**; the Catalog Tag and every internal reference are exactly `DEAF&HEARING` (no `#`, `+`, `%`, or HTML entity). Branding stays readable while storage stays canonical.
3. **DEAF+HEARING and Participants collection mode are mutually exclusive**, either direction clearing the other — one legible Playlist story per screen. Composing them (e.g. "this participant's crossover videos") is deferred to a future multi-Tag browser; the facet model keeps that door open without rework.
4. **On the player it's a toggle that ANDs with R2 facets**; from About/Participants (no live player) a click force-activates it, clearing all R2 pins and Participant mode and jumping to the pure tagged Playlist — the button means "play that set" from secondary screens, not "intersect with a possibly-stale session." A visitor with R2 pins in session loses them on this path; accepted for the first slice, flagged for post-launch validation.
5. **Session is a per-visit handoff, not a sticky preference.** A stored "on" flag is applied only when the player boots by consuming a nav-intent (`play`/`prev`/`next`/`reset`/`deaf-hearing`); a bare cold-load URL always starts inactive regardless of session state.
6. **Chrome guard:** the control renders disabled when zero visible Catalog Videos carry the Tag, so it's never a live dead-end after a data slip. This is what makes the "always jump to a non-empty pure set" guarantee in (4) safe.

## Consequences

- Any future single-Tag chrome control can reuse the same `tag` slot and matching logic — adding one is a config change, not new filter machinery.
- The label/storage mismatch (`DEAF+HEARING` vs `DEAF&HEARING`) must be preserved deliberately; don't "fix" the button text to match the Tag, or vice versa, without revisiting this decision.
- The secondary-page force-ON path silently drops existing R2 pins — a known, accepted asymmetry versus the player-side toggle, not a bug. Worth checking real usage post-launch before hardening it either direction.
- Full detail (all 27 locked sub-decisions, acceptance fixtures, test plan) lives in [`.scratch/preview-deaf-hearing-filter/PRD.md`](../../.scratch/preview-deaf-hearing-filter/PRD.md); this ADR captures only the parts that should outlive that PRD.

**Considered options:**

- **Bespoke boolean flag (e.g. `deafHearingOn: boolean`)** — rejected. Would have required separately reimplementing participant mutual-exclusion, Reset-clears, and session persistence that the facet model gets for free from existing `filterState` handling.
- **Composing DEAF+HEARING with Participants collection mode** — rejected for this slice. The product wants one legible Playlist story per screen; deferred to a future multi-Tag browser.
- **Shareable/deep-link URL param (`?deaf_hearing=1`)** — rejected for this slice, to keep the session + button work small. Can mirror the existing `?participant=` pattern later.
