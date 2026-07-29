Status: ready-for-agent

# PRD — Deepen Studio Publication, translation, Catalog, and Preview modules

## Problem Statement

DEAF.city’s Studio and Preview site preserve the correct domain decisions, but several important behaviours are split across shallow modules. Callers must currently know persistence ordering, state-file layout, raw Catalog shape, Preview-player data shape, or configuration reference rules in order to complete a domain action.

This weakens locality: the same Caption file publication behaviour, Subtitle translation run behaviour, Catalog invariants, Preview projection rules, and Studio configuration constraints must be understood and changed in multiple places. It also makes the interface, rather than the domain action, the test surface.

The Preview site additionally no longer implements the accepted muted-autoplay/unmute behaviour in ADR-0009. Its current playback behaviour and its test expectation conflict with that decision.

## Solution

Deepen six modules around the domain actions they already support:

1. Caption file Publication;
2. Subtitle translation run;
3. Catalog persistence;
4. Preview Catalog projection;
5. Studio configuration mutation; and
6. Preview muted-autoplay and unmute interaction.

Each module exposes a small, domain-oriented interface and absorbs its persistence, orchestration, conversion, and validation implementation. The server remains the master for Caption files and Subtitle languages; Vimeo remains a best-effort backup mirror. The Catalog remains authoritative for Studio and the Preview site.

## User Stories

1. As a Producer, I want a Caption file Publication to save the server Caption file and Catalog record before attempting Vimeo, so that the Website remains correct when Vimeo is unavailable.
2. As a Producer, I want every route that publishes or replaces a Caption file to follow the same Publication behaviour, so that retries, warnings, and language handling are consistent.
3. As a Producer, I want a Vimeo failure after a server write to be reported as a warning, so that I know the server copy remains usable.
4. As a Producer, I want the same Subtitle language information used for the server Caption file and Vimeo mirror, so that the mirror stays aligned with the server master.
5. As a Producer, I want bulk Vimeo synchronization to use the same Caption file Publication behaviour as individual publication, so that repair work does not diverge from normal work.
6. As a Producer, I want every Subtitle translation run to have one consistent lifecycle, so that starting, waiting, retrying, and finalizing behave the same for a Job and an existing Catalog Caption file.
7. As a Producer, I want a failed translation language to be retryable through the same run behaviour that created it, so that I do not need to understand worker or state-file details.
8. As a Producer, I want completed translations to become server Caption files and Catalog references through one finalization path, so that successful work is never lost between a Job and Publication.
9. As a Producer, I want automatic Subtitle translations to continue excluding the Master subtitle and Caption files that already exist, so that reviewed work is not overwritten.
10. As a Producer, I want translation failure states to remain understandable and recoverable, so that a transient provider failure does not require manual filesystem repair.
11. As a Producer, I want Catalog changes to preserve Video, Caption file, Master subtitle, visibility, Edition, Tag, Sign language, and Typology invariants, so that the Catalog remains authoritative.
12. As a Producer, I want Studio actions to express the Catalog change I intend, so that I do not need to depend on raw JSON layout or lock handling.
13. As a Producer, I want an Invisible Video to remain a preserved Catalog Video that is absent from public projections, so that restoring it does not require rebuilding metadata.
14. As a Preview visitor, I want all eligible Videos to be projected from the Catalog into player data consistently, so that the Website and Participant views show the same Video facts.
15. As a Preview visitor, I want Caption files, thumbnails, filters, participant information, and visibility treatment to be consistent in every Preview route, so that navigation does not change a Video’s meaning.
16. As a Preview visitor, I want new Catalog Video facts to appear consistently wherever the player consumes them, so that routes cannot silently disagree about a Video.
17. As a Producer, I want Studio configuration mutations to enforce reference integrity, so that I cannot remove an Edition, Sign language, Typology, or Subtitle language that an existing Catalog Video still needs.
18. As a Producer, I want Subtitle language configuration constraints to be enforced in one place, so that language selection and Caption file Publication stay valid.
19. As a Producer, I want Studio configuration writes to remain serialized and durable, so that simultaneous or interrupted changes cannot corrupt shared reference data.
20. As a hearing Preview visitor, I want a Video to autoplay muted on a cold visit, so that browser autoplay policy does not prevent playback.
21. As a hearing Preview visitor, I want a visible affordance to unmute a Video with one gesture, so that I know sound is available.
22. As a hearing Preview visitor, I want sound to remain enabled for the rest of my session after I unmute, so that subsequent Videos respect my choice.
23. As a keyboard or screen-reader user, I want a focusable mute/unmute control with stateful text, so that the sound interaction remains usable without the pointer shortcut.
24. As a maintainer, I want the server-master and Catalog-authority decisions to remain unchanged while modules are deepened, so that architectural work does not reverse accepted domain decisions.
25. As a developer, I want tests to cross each deep module’s interface, so that a regression is detected through behaviour a caller actually relies on.
26. As a developer, I want fixture-based tests for Catalog-to-Preview projection, so that one Catalog Video can be verified through the emitted player data without route-specific duplication.
27. As a developer, I want Publication and translation-run failure cases tested without a live Vimeo or translation provider, so that the tests are deterministic.
28. As a developer, I want the accepted muted-autoplay decision reflected in Preview tests, so that implementation drift is reported rather than preserved.

## Implementation Decisions

### Caption file Publication module

- Introduce one deep Caption file Publication module for all paths that create, replace, finalize, or synchronize a Caption file.
- Its interface accepts a reviewed Caption file plus the Video and Subtitle language facts needed for Publication, and returns the persisted server result plus an optional Vimeo mirror warning.
- Its implementation owns server Caption file persistence, Catalog Caption recording, Vimeo text-track mirroring, and the ordering of those actions.
- The server write and Catalog update happen before the Vimeo mirror attempt.
- A Vimeo mirror failure is non-fatal after server persistence and is represented as a warning for the caller to display or record.
- Existing direct mirror implementation is removed or delegated so that no second module owns the same mirror loop.
- This reinforces ADR-0001, ADR-0003, and ADR-0007. The server remains master; Vimeo is never read as a replacement source for Caption files or Subtitle languages.

### Subtitle translation run module

- Introduce one deep Subtitle translation run module for starting, observing, retrying, and finalizing translation work.
- Its interface expresses a source Master subtitle or Caption file, the eligible configured Subtitle languages, and the destination context; callers do not construct state files, choose working paths, or finalize individual output files.
- Its implementation owns run-state persistence, worker launch, status transitions, retry eligibility, output validation, and finalization into the appropriate server Caption files and Catalog records.
- Reuse the existing translation state and runner implementation internally where it already earns depth; move route-specific orchestration behind the run interface.
- Preserve the active rule that every configured Subtitle language is eligible except the Master subtitle and languages with an existing Caption file.
- Preserve accepted Gemini translation and Master-subtitle revision behaviour, including their distinct failure semantics. This work does not alter translation provider, prompt, or retry policy.

### Catalog persistence module

- Deepen Catalog persistence around named Video mutations rather than raw arrays and JSON mechanics.
- Its interface owns Catalog reads needed by Studio and the invariant-preserving mutations used by Video intake, metadata changes, Caption file changes, visibility changes, and Publication.
- Its implementation owns locking, decoding, writing, and validation of Video facts including Caption files, Master subtitle, visibility, Edition, Tags, Sign language, and Typology.
- Preview read models may remain separate, but they consume a stable Catalog representation rather than reimplementing mutation invariants.
- Retain the Catalog as the authoritative registry for Studio and the Preview site under ADR-0002. No legacy-homepage migration is part of this work.

### Preview Catalog projection module

- Introduce one deep Preview Catalog projection module that turns an eligible Catalog Video into the complete player-facing Video representation.
- Its interface accepts Catalog Videos and route-specific selection facts, then returns the exact collection and active Video data required by the Preview player.
- Its implementation owns filtering Invisible Videos, Caption file selection data, thumbnail data, participant data, and the shared player-data representation.
- The main Preview view and Participant view delegate to this module instead of maintaining parallel Video-to-playlist and playlist-to-player conversions.
- Player presentation remains responsible for runtime Vimeo aspect-ratio discovery as specified by ADR-0008; aspect-ratio fields are not added to the Catalog.

### Studio configuration mutation module

- Deepen Studio configuration mutation so its interface expresses safe changes to Editions, Sign languages, Typologies, and Subtitle languages.
- Its implementation owns serialized configuration persistence, validation of configuration facts, and reference-integrity checks against the Catalog.
- Catalog inspection required for safe removal becomes an internal seam of configuration mutation rather than a caller-supplied dependency.
- Keep the current Subtitle-language policy and canonical identifier rules intact. This work must integrate with the active Subtitle-language simplification rather than reintroducing a secondary Vimeo mapping.
- Configuration data beneath `data/` must retain `www-data:www-data` ownership after any root-initiated write.

### Preview muted autoplay and unmute interaction

- Correct Preview playback so every Video begins muted and attempts autoplay, matching ADR-0009.
- The Video surface is reserved for mute/unmute. Play/pause remains on its dedicated transport control.
- The visible mute/unmute affordance is a real focusable control with stateful accessible text.
- After the first unmute, the sound preference persists for the browser session and applies to subsequent Videos.
- Retain the accepted hover-capable and touch-device affordance behaviour from ADR-0009.
- Update the existing cold-load test expectation to assert the accepted behaviour rather than the superseded implementation.

### Delivery order

- Establish characterization tests for current server-master, Catalog, and Preview data behaviours before moving implementations.
- Deepen Catalog persistence and Studio configuration mutation first, because Publication and translation finalization depend on their invariant-preserving behaviour.
- Build Caption file Publication next and route individual, replacement, finalization, and bulk synchronization through it.
- Build the Subtitle translation run module and migrate each translation entry point to it.
- Build Preview Catalog projection and migrate all Preview routes that create player data.
- Correct muted autoplay/unmute behaviour and test it independently of the Catalog projection work.
- Remove obsolete duplicate implementation only after its callers cross the new module interface and regression tests pass.

## Testing Decisions

- Good tests cross each module’s interface and assert observable persistence, returned outcome, emitted player data, or accessible interaction state. They do not assert private helper calls, internal directory layout, JSON lock mechanics, or incidental ordering not promised by the interface.
- Add or expand deterministic Studio PHPUnit tests for:
  - Caption file Publication server-first ordering, Catalog result, direct Vimeo success, and non-fatal Vimeo failure;
  - the same Publication behaviour for normal Publication, replacement, translation finalization, and bulk Vimeo synchronization;
  - Subtitle translation run start, status, retry, eligibility filtering, successful finalization, and recoverable per-language failure;
  - Catalog persistence mutation invariants for Caption files, Master subtitle, Invisible Videos, Edition, Tags, Sign language, and Typology;
  - Studio configuration mutation add/remove validation, locked persistence, and Catalog reference-integrity rejection.
- Use fake adapters at real seams for Vimeo, translation execution, filesystem persistence, and Catalog inspection. A seam receives a second adapter only where the test or production variability is genuine; do not add hypothetical seams merely to mock implementation details.
- Add Preview fixture tests that pass a Catalog Video through the projection interface and assert the player-facing data for normal, Invisible, Caption-bearing, filtered, and Participant-scoped Videos.
- Extend existing Preview transport and gesture tests to assert muted autoplay, the initial unmute affordance, pointer interaction, keyboard interaction, touch feedback, re-mute behaviour, and session-sticky sound across Video changes.
- Preserve PHP 5.6-compatible Preview runtime source. Run Studio tests with PHP 8.4 and Preview test scripts with their documented compatible runner.
- Reuse the existing test prior art for Catalog mutations, Caption upload, Vimeo synchronization, translation state and runner behaviour, Studio configuration mutations, Preview visibility, Participant routes, transport sessions, and gesture-gated audio.
- Run the relevant Studio PHP, Studio JavaScript, and Preview PHP/JavaScript suites, then perform browser smoke tests of a Caption file Publication, a translation retry, Catalog visibility, Preview caption selection, and muted autoplay/unmute.

## Out of Scope

- Changing the authoritative roles of the Catalog, server Caption files, or Vimeo.
- Pulling Caption files, Subtitle language data, tags, or Video metadata from Vimeo to overwrite server state.
- Changing Vimeo upload ownership, Subtitle translation provider, Master-subtitle revision provider, translation prompts, or provider retry policy.
- Migrating the legacy homepage, removing the transitional legacy data source, or changing the Preview site’s route set.
- Adding Catalog aspect-ratio fields or changing ADR-0008’s runtime Vimeo aspect-ratio decision.
- Changing Subtitle-language policy, reintroducing `vimeo_code`, or expanding the configured Subtitle-language set.
- Changing public Website design except the already accepted Preview sound affordance behaviour.
- Rewriting historical PRDs or ADRs that do not conflict with these implementation decisions.

## Further Notes

- This PRD combines every candidate from the 29 July 2026 architecture review and includes the ADR-0009 implementation drift found during that review.
- The chosen work deepens existing modules; it is not a rewrite of Studio, Catalog, Publication, or the Preview site.
- The highest-leverage first implementation is Caption file Publication because it removes duplicate server/Vimeo mirror implementation while directly preserving the server-master rule.
- The active Subtitle-language simplification work may be in progress concurrently. Any implementation must begin from its resulting canonical language model and avoid editing its active files until that work has landed.
