# ADR-0011 — Gemini Flash for master subtitle revision, with hard failure and full-VTT input

**Date**: 2026-06-11
**Status**: Accepted

---

## Context

After transcription the master subtitle draft frequently contains Groq/Whisper artefacts: run-on cues, unnatural mid-sentence breaks, missing punctuation, and timing overlaps. These defects propagate to every translated language. A Gemini revision pass on the master VTT before translation starts corrects them at the source.

Two design choices in this pass are non-obvious and warrant recording.

## Decision

### 1. Hard failure — revision error stops the pipeline

Unlike `GeminiTranslator`, which marks individual languages as `error` and lets the loading screen offer a per-language retry, `GeminiReviser` treats any failure as terminal: the pipeline halts and the Job shows a `revision_error` state with no retry button.

**Why**: a revision failure almost always means the API key is invalid, the quota is exhausted, or the prompt contract is broken — not a transient timeout. Retrying would keep the Producer waiting for no reason. If the master VTT is corrupt the translated files would also be corrupt; it is better to surface the failure early.

**Alternative considered**: soft fallback — proceed with the unrevised master. Rejected because it silently degrades quality for every translation and makes it impossible to notice systematic revision failures in production.

### 2. Full VTT blob as input and output (not per-cue array)

Translation sends an ordered array of cue texts and receives the same-length array back, preserving all timestamps exactly. Revision instead sends the complete WebVTT string and receives a complete WebVTT string, because the revision prompt is permitted to merge, split, and retime cues for comedic and linguistic correctness.

**Why**: per-cue revision would force the model to correct each cue in isolation, preventing it from merging a fragmented sentence across cue boundaries — which is the most important fix the revision pass makes.

**Consequence**: the response is validated as well-formed WebVTT (header present, no timestamp overlaps, no empty cues) but cue count is not checked. The `WebVttValidator` provides this check. Timestamps in the revised VTT are trusted; the revised file overwrites `draft.vtt` in place.

## Consequences

- A new `revision_status.json` file in the Job directory tracks revision state (`pending → running → done | error`), mirroring `translation.json`. `TranscriptionPipelineStatus` gains `revising` and `revision_error` states.
- `BackgroundJobLauncher` gains a `launchRevisionAndTranslation()` method. `run_transcription_pipeline.sh` chains `run_revise.sh` instead of `run_translate.sh` directly.
- The loading screen gains a "Revisant subtítols…" step visible between transcription and translation.
