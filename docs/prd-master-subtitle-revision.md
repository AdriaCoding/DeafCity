# PRD: Master Subtitle Revision Pipeline Step

## Problem Statement

After transcription, the master subtitle draft contains systematic Whisper/Groq artefacts: run-on cues spanning multiple sentences, unnatural mid-sentence breaks, missing or inconsistent punctuation, timing overlaps between consecutive cues, and lines exceeding readable character limits. Because every translated language is derived from this master, these defects propagate to all outputs. A Producer currently has no automated correction step — they must notice and fix these issues manually in the Subtitle Editor, or accept degraded quality in the pipeline (no-editor) flow.

## Solution

Insert a Gemini Flash revision call between transcription and translation in the pipeline (no-editor) flow. The revision pass receives the raw master VTT, applies professional broadcast subtitle standards — single-line cues, 60-character limit, natural segmentation boundaries, overlap correction, comedic timing optimisation — and writes the corrected VTT back before translation begins. The loading screen gains a visible "Revisant subtítols…" step. If revision fails, the pipeline halts with a clear error state.

## User Stories

1. As a Producer, I want the transcribed subtitle draft to be automatically cleaned and re-segmented before translation, so that the final subtitle files meet broadcast quality without manual editing.
2. As a Producer, I want the loading screen to show a distinct revision step, so that I know the system is working and can distinguish a long revision from a hung pipeline.
3. As a Producer, I want cues that exceed 60 characters to be automatically split at natural linguistic boundaries, so that subtitles are readable during playback.
4. As a Producer, I want consecutive short cues that form a single sentence to be merged into one cue, so that the subtitles are not unnecessarily fragmented.
5. As a Producer, I want cue lines to never end on a hanging conjunction, relative pronoun, or preposition, so that viewers are not left mid-thought across a cue break.
6. As a Producer, I want timestamp overlaps between consecutive cues to be detected and corrected automatically, so that the caption file passes integrity checks without manual intervention.
7. As a Producer, I want punctuation and capitalisation to be normalised across all cues, so that the transcript reads consistently.
8. As a Producer, I want punchlines and comedic beats placed on their own dedicated cues when timing allows, so that the humour lands correctly for the audience.
9. As a Producer, I want merged cues to use the start timestamp of the first original cue and the end timestamp of the last, so that timing continuity is preserved.
10. As a Producer, I want split cues to have the end timestamp of the first part match exactly the start timestamp of the second, so that there are no gaps or overlaps introduced by the split.
11. As a Producer, I want proportional time allocation when a cue is split, based on character count of each part, so that reading time is distributed fairly.
12. As a Producer, I want the revision step to never change any spoken words, so that the transcript remains an accurate record of what was said.
13. As a Producer, if revision fails, I want the pipeline to stop immediately with a clear error message, so that I am not waiting for a translation that will be based on a corrupt draft.
14. As a Producer, I want to cancel a job that is stuck in the revising state, so that I can restart from scratch if something goes wrong.
15. As a Producer, I want the translated English subtitle to be derived from the revised master, so that translation quality benefits from the clean source.
16. As a Producer running a job with an English source language, I want the revision step to still apply to the master, so that the downloadable VTT is clean even when no translation is produced.

## Implementation Decisions

### Modules to build

**`GeminiReviser`** (new class)
Takes a full VTT string and a source language code. Sends the VTT to Gemini Flash using the revision system prompt (see below), receives a JSON-wrapped response `{"revised_vtt": "..."}`, and returns the revised VTT string. Throws `GeminiRevisionException` on any API or parse error. Uses the same 3-attempt exponential backoff (1 s / 2 s / 4 s) on HTTP 429 and 5xx as `GeminiTranslator`. Injectable HTTP callable and sleep callable for testability.

**`GeminiRevisionException`** (new exception class)
Mirrors `GeminiTranslationException`.

**`revise.php`** (new CLI script, `studio/scripts/`)
Entry point for the background revision process. Args: `--vtt_path`, `--revision_status`, `--source_lang`, `--job_dir`. On start: writes `revision_status.json: {status: "running"}`. Calls `GeminiReviser`. Validates the response through `WebVttValidator`. On success: overwrites `draft.vtt` with revised content, writes `{status: "done"}`, exits 0. On failure: writes `{status: "error", "message": "..."}`, exits 1. Includes a shutdown handler (same pattern as `translate.php`) to mark `error` on unexpected crash.

**`run_revise.sh`** (new shell wrapper, `studio/scripts/`)
Calls `revise.php`. On exit 0: chains `run_translate.sh` with the same arguments it already receives (master VTT, translation status, source lang, job dir, target langs). On non-zero exit: stops without chaining translation (hard failure honoured at the shell level).

### Modules to modify

**`JobManager`**
Add `revisionStatePath()` returning `{jobDir}/revision_status.json`.

**`TranscriptionPipelineStatus`**
Extend `getState()` with two new return values: `'revising'` and `'revision_error'`.

New state machine (evaluated in order):
```
no draft.vtt                                           → 'transcribing'
revision_status.json absent (legacy job)               → skip revision check, continue
revision_status.json status = 'pending' or 'running'   → 'revising'
revision_status.json status = 'error'                  → 'revision_error'
// revision done or absent (legacy) — continue to translation check
EN source language                                     → 'download_ready'
translation.json absent or status pending/running      → 'translating'
EN language status = 'error'                           → 'translation_error'
EN language status = 'done' and draft_en.vtt exists    → 'download_ready'
                                                       → 'translating'
```

Backwards compatibility: a `revision_status.json` that is absent is treated as "revision not applicable" (legacy job created before this step was added) so existing in-flight jobs are not broken.

**`BackgroundJobLauncher`**
Add `launchRevisionAndTranslation()`. Accepts the same parameters as `launchTranslation()` plus a `revisionStatusPath` argument. Spawns `run_revise.sh` with all necessary arguments.

**`TranscriptionIntakeHandler`**
When `pipeline_transcribed`:
- Write `revision_status.json: {status: "pending"}` before spawning (eliminates a race window where `TranscriptionPipelineStatus` would see no status file).
- Call `launchRevisionAndTranslation()` instead of `launchTranslation()`.
- This applies to both the English-source path (no translation, revision only) and the non-English path (revision then translation).

**`run_transcription_pipeline.sh`**
Step 2 changes from spawning `run_translate.sh` directly to spawning `run_revise.sh`. `run_revise.sh` chains `run_translate.sh` on success.

**`transcription-loading.php`**
Add two new rendering branches:

- `revising`: spinner + label "Revisant subtítols…" + cancel button. Polling: `window.location.reload()` on a timer (no dedicated status endpoint needed — the PHP view re-evaluates `TranscriptionPipelineStatus` on each page load).
- `revision_error`: error panel with message "No s'ha pogut revisar el fitxer de subtítols." + cancel button only (no retry — hard failure per ADR-0011).

### Gemini API contract

**Endpoint**: `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent`

**System instruction** (the source language name is substituted at call time using the same `PROMPT_LANGUAGE_NAMES` map as `GeminiTranslator`):

```
# Role & Context
You are an expert subtitle editor. Your task is to clean, re-segment, and format a [LANGUAGE] subtitle file to meet professional broadcasting standards.

The content is humorous in nature. You must optimize for comedic timing—ensure punchlines land on their own dedicated cues whenever the timing allows.

---

# Objective
Review the provided subtitle text and output a perfectly timed, semantically coherent, and strictly formatted WebVTT file.

---

# Strict Constraints

### 1. Formatting & Length
* **Single Line Only:** Each subtitle cue MUST consist of a single line of text (absolutely no internal line breaks/newlines within a cue).
* **Character Limit:** Each line MUST be less than or equal to 60 characters (<= 60 chars).

### 2. Linguistic Segmentation
* **Avoid Fragmentation:** Do not keep unnatural, mid-sentence breaks. Merge consecutive cues if they form a single coherent sentence or idea, provided they stay under the 60-character limit.
* **Natural Splits:** If a caption exceeds 60 characters and must be split, the split must occur only at natural linguistic boundaries (e.g., clauses, commas, or phrase boundaries).
* **No Hanging Connectors:** NEVER end a subtitle line with a conjunction (e.g., and, but, or), a relative pronoun/adverb (e.g., that, when, who, which), or a preposition (e.g., about, with, for) if the phrase or clause continues onto the next line. Either push the connector word to the beginning of the next line, or pull enough text up to complete the grammatical thought.

### 3. Timing & Chronology
* **Merged Cues:** New Start Timestamp = Start of first cue | New End Timestamp = End of last cue.
* **Split Cues:** The End timestamp of the first part MUST perfectly match the Start timestamp of the next part.
* **Proportional Splitting:** Divide the time duration proportionally based on the character count of each split relative to the total character count of the original cue.
* **Overlap Correction:** Scan for, flag, and eliminate any chronological timestamp overlaps between consecutive cues.

### 4. Text Integrity (CRITICAL)
* Do NOT change any wording.
* Do NOT paraphrase.
* Do NOT add, omit, or remove any spoken words.
* Correct missing or inconsistent punctuation (periods, commas, colons) and ensure the first word of every cue is properly capitalized.

---

# Output Format
Return JSON only. Use the following schema:
{"revised_vtt": "<the complete corrected WebVTT file as a string>"}
The value of revised_vtt must be a valid WebVTT file beginning with WEBVTT.
```

**User content**: the raw VTT string.

**Response schema**:
```json
{
  "type": "OBJECT",
  "properties": {
    "revised_vtt": { "type": "STRING" }
  },
  "required": ["revised_vtt"]
}
```

**`responseMimeType`**: `application/json`

### Validation after revision

The revised VTT is validated through `WebVttValidator` before `draft.vtt` is overwritten. If validation fails, `GeminiRevisionException` is thrown (hard failure). No cue-count check is performed — the model is permitted to merge and split cues freely (ADR-0011).

## Testing Decisions

Good tests verify observable behaviour through the public interface, not internal implementation. They should not assert on the exact text of Gemini prompts — only on the inputs and outputs of each class.

### Modules to test

**`GeminiReviser`** — unit tests with injected HTTP callable (prior art: `GeminiTranslatorTest`):
- Happy path: valid JSON response containing `revised_vtt` is returned as a string.
- Retry on 429: first call returns 429, second returns 200 — result is returned, sleep is called once.
- Retry on 500: same pattern.
- Exhausted retries: three consecutive 429s throw `GeminiRevisionException`.
- Fast-fail on 400: single 400 throws immediately without retrying.
- Malformed JSON in response body throws `GeminiRevisionException`.
- Missing `revised_vtt` field in response JSON throws `GeminiRevisionException`.
- Empty `revised_vtt` string throws `GeminiRevisionException`.

**`TranscriptionPipelineStatus`** — unit tests writing state files to temp dirs (prior art: `TranscriptionPipelineStatusTest`):
- `revising` when `revision_status.json` is `{status: "pending"}` and draft VTT exists.
- `revising` when `revision_status.json` is `{status: "running"}` and draft VTT exists.
- `revision_error` when `revision_status.json` is `{status: "error"}` and draft VTT exists.
- Legacy job backwards compat: no `revision_status.json` and translation state exists → `translating` (not `revising`).
- Full happy path: `revision_status.json` done + `translation.json` done + `draft_en.vtt` exists → `download_ready`.

**`TranscriptionIntakeHandler`** — existing test file (`TranscriptionIntakeHandlerTest`) should be extended:
- `pipeline_transcribed` outcome calls `launchRevisionAndTranslation`, not `launchTranslation`.
- `revision_status.json` is written with `{status: "pending"}` before the launcher is called.

## Out of Scope

- Revision in the interactive (Subtitle Editor) flow. The editor pipeline is being deprecated; revision is only added to the pipeline (no-editor) flow.
- A retry button on the `revision_error` state. Hard failure is intentional (ADR-0011); the Producer must cancel and restart.
- Exposing the revision diff or change log to the Producer. The changelog was used during prompt development and is not needed in production.
- Applying revision to translated languages. Revision runs once on the master; translation quality is expected to be sufficient for the target languages without a second revision pass.
- Configurable revision prompt. The prompt is hardcoded in `GeminiReviser`. If a different prompt is needed in future, a new class or a config-driven override can be added at that point.
- Revision for jobs in the bulk intake flow.

## Further Notes

- The revision prompt was developed iteratively with a changelog output for evaluation; the changelog is removed from the production prompt as it serves no automated purpose.
- The same `GEMINI_API_KEY` used for translation is reused for revision. No additional credential management is required.
- Gemini 2.5 Flash is used (same model as translation). If the model identifier changes, update `GeminiReviser::ENDPOINT` in one place.
- At typical monologue lengths (~20–80 cues), the full VTT blob fits comfortably within Gemini's input token window.
- The `run_revise.sh` script passes `GEMINI_API_KEY` as an environment variable in the same way `run_translate.sh` does.
