Status: shipped

# Studio — Subtitle Editor (Slice 2)

## Problem Statement

After Intake, the Producer has a Job containing a `draft.vtt` caption file and a Vimeo ID, but no way to review or correct the subtitle cues against the actual video. The cues may have timing errors, transcription mistakes, or structural problems (overlapping ranges, stray cues) that must be resolved before the caption file can become the Master subtitle and the pipeline can advance.

## Solution

A full-page Subtitle Editor at `?action=subtitle-editor` that shows a sticky Vimeo player on the left and a scrollable, fully editable cue list on the right. The Producer can edit cue text and timestamps, add and delete cues, and receive real-time feedback on caption file integrity errors. A **Save & Done** action validates the cue list, writes the reviewed caption file back to `draft.vtt`, and advances the Job to the `translation` step. A **Skip to Tagging** action is available for Productions that do not require additional Subtitle languages. In Slice 4, "Save & Done" is renamed to "Save & Translate" and a "Save draft" button is added alongside it; the editor toolbar in this slice implements the Slice 2 variant only.

The Subtitle Editor is built as a **universal caption file editor** — the same surface will be reused in Slice 3 (correcting auto-generated cues) and Slice 4 (reviewing translated cues), with no duplication of editor logic.

## User Stories

1. As a Producer, I want to open the Subtitle Editor from the Studio shell when a Job is active, so that I can begin reviewing the subtitle cues.
2. As a Producer, I want to see the Vimeo player and the cue list side by side, so that I can cross-reference each cue against the video without switching views.
3. As a Producer, I want the Vimeo player to remain visible as I scroll through a long cue list, so that I can always see the video while working on any cue.
4. As a Producer, I want to click a cue in the list to seek the player to that cue's start time, so that I can instantly verify any cue against the video without scrubbing manually.
5. As a Producer, I want the cue currently playing in the video to be highlighted in the list, so that I can track which cue is on screen during playback.
6. As a Producer, I want to edit the text of any cue, so that I can correct transcription errors.
7. As a Producer, I want to edit a cue's start and end timestamps using text fields, so that I can make precise timing adjustments.
8. As a Producer, I want a "set from playhead" button next to each timestamp field, so that I can align a cue boundary to the exact video position without typing timestamps by hand.
9. As a Producer, I want to add a new cue at any position in the list, so that I can insert missing subtitles.
10. As a Producer, I want to delete any cue from the list, so that I can remove erroneous or duplicate cues.
11. As a Producer, I want overlapping cue time ranges to be flagged in real time as I edit, so that I can see and fix integrity errors before attempting to save.
12. As a Producer, I want the "Save & Done" button to be blocked with a clear error message if the cue list contains integrity errors, so that I cannot accidentally save a corrupt caption file.
13. As a Producer, I want "Save & Done" to succeed silently and redirect me to the next pipeline step when the cue list is clean, so that the transition to the next step is frictionless. (Slice 4 renames this button to "Save & Translate" and adds a "Save draft" button; see the Translation PRD.)
14. As a Producer, I want my browser to warn me if I try to navigate away with unsaved changes, so that I do not accidentally lose edits.
15. As a Producer, I want a "Skip to Tagging" action that bypasses the translation step, so that I can proceed directly to Tagging when no additional Subtitle languages are needed.
16. As a Developer, I want PHP to be the authoritative integrity gate on every save, so that the integrity invariant holds even if client-side validation is bypassed.
17. As a Developer, I want the VTT parser and integrity checker to be independent PHP modules with no HTTP dependencies, so that they can be fully exercised in unit tests.
18. As a Developer, I want the Subtitle Editor view and JS to be structured so that Slices 3 and 4 can reuse them with minimal change, so that the same editing experience is delivered across all subtitle-authoring steps without code duplication.

## Implementation Decisions

### Modules

**VttParser** (new PHP class)
Owns all VTT read/write logic. Two responsibilities:
- `parse(string $filePath): array` — reads a `.vtt` file and returns an array of cue objects. Each cue carries: `start` (seconds as float), `end` (seconds as float), `text` (multi-line string), and `opaque` (any unparsed attributes on the timestamp line, preserved verbatim for round-trip). Cue identifiers and NOTE/REGION/STYLE blocks are preserved as opaque fragments in a separate top-level key.
- `write(array $cues): string` — reconstructs a valid WebVTT string from the cue array, round-tripping all opaque content.

Only start time, end time, and text are parsed into discrete fields. Everything else is preserved verbatim.

**CaptionFileIntegrityChecker** (new PHP class)
Accepts a cue array (output of `VttParser::parse`) and returns a flat array of error messages, each including the offending cue's position. Checks:
- Well-formed timestamps (start < end, no negative values)
- No overlapping time ranges between any two cues

Returns an empty array when the cue list is clean. No HTTP dependencies.

**SubtitleEditorHandler** (new PHP class)
Handles the `POST ?action=subtitle-editor` request. Responsibilities:
- Decodes the JSON cue array from the request body
- Runs `CaptionFileIntegrityChecker`; returns errors as JSON if the check fails
- Calls `VttParser::write` to reconstruct the VTT string
- Overwrites `data/jobs/current/draft.vtt`
- Calls `JobManager::update(['step' => 'translation'])`
- Returns a JSON success response for the JS save handler to act on

**Subtitle Editor view** (new PHP template)
Renders the full-page editor. PHP loads `draft.vtt` via `VttParser::parse` and injects the cue array as a JSON-encoded JavaScript variable into the page. Also injects the Vimeo player ID from `job.json`. No fetch required on page load.

**subtitle-editor.js** (new vanilla JS file)
All editor interactivity — no framework, no build step:
- Renders the cue list from the injected JSON
- Vimeo Player JS SDK: `timeupdate` event → highlight active cue; cue click → `setCurrentTime()`
- "Set from playhead" buttons capture `getCurrentTime()` and write the timestamp field
- Add/delete cue operations update the in-memory cue array and re-render
- Client-side overlap detection on every edit — highlights offending rows
- On "Save & Done": POSTs the cue array as JSON; handles server error responses; on success, follows the redirect to `?action=translation`
- `beforeunload` guard when the in-memory cue array has been modified since last save

**PipelineSteps** (update existing PHP class)
Add labels and routes for `translation` and `tagging` steps.

### Layout

Side-by-side: Vimeo player on the left at a fixed width (~50%), cue list scrollable on the right. The player is sticky so it remains in view regardless of how far the Producer has scrolled into the cue list.

### Data flow

Load: PHP parses `draft.vtt` → JSON array injected into page as `window.__cues`. JS renders the list from this value. No additional HTTP request.

Save: JS POSTs `application/json` body `{"cues": [...]}` to `?action=subtitle-editor`. PHP validates, writes the file, updates the step, returns `{"ok": true}` or `{"errors": [...]}`.

### Save semantics

"Save & Done" is one action in this slice. It persists the reviewed caption file and advances the pipeline. There is no intermediate draft-save in this slice. The file stays named `draft.vtt` throughout the Job; "Master subtitle" is a pipeline status, not a filename.

Slice 4 (Translation) modifies this: "Save & Done" is renamed to "Save & Translate" and a "Save draft" button (saves `draft.vtt` without step advancement) is added alongside it. An agent building Slice 2 should implement the single-button variant; the Slice 4 agent will extend it.

### Step advancement

On successful save, `job.step` is set to `translation`. A "Skip to Tagging" button on the editor page POSTs a separate action that sets `job.step` to `tagging` without saving cue edits (prompts the unsaved-changes guard if edits are pending). This button persists in the toolbar through Slice 4 — it is the path for Productions that bypass translation entirely, distinct from the Translation Hub's "Proceed to Tagging" (which is reached after translation has run).

### Caption file integrity

Client-side: overlap detection runs on every timestamp edit and flags offending cues in real time. The Save button is visually disabled while errors are present, though the server remains the authoritative gate.

Server-side: `CaptionFileIntegrityChecker` re-validates the submitted cue array before any write. A failed check returns HTTP 422 with a JSON error list; no file is written.

### VTT cue attributes

Only `start`, `end`, and `text` are parsed into discrete fields. Optional cue identifiers, positioning settings (`align`, `position`, `line`, `size`), inline formatting tags, and STYLE/REGION/NOTE blocks are preserved verbatim and round-tripped on save. The Producer cannot edit these from the UI; they pass through unchanged.

## Testing Decisions

A good test verifies externally observable behaviour — what a module returns or what state it leaves behind — not how it achieves it internally. Tests should not assert on intermediate variables, private methods, or implementation choices that could change.

The pattern established in this codebase: write each test class against a single PHP class; use `sys_get_temp_dir()` for file fixtures; assert on return values and filesystem state.

**VttParserTest**
- Parses a single-cue VTT into the expected cue array
- Preserves multi-line cue text as a single field
- Round-trips opaque attributes on the timestamp line unchanged
- `write()` output is valid WebVTT with the correct header
- `write()` → `parse()` round-trip is idempotent for all parsed fields

**CaptionFileIntegrityCheckerTest**
- Returns empty array for a clean, non-overlapping cue list
- Returns errors for cues whose time ranges overlap
- Allows adjacent cues (end of cue N equals start of cue N+1)
- Flags a cue whose start time equals or exceeds its end time
- Returns an error for each offending pair, not just the first

**SubtitleEditorHandlerTest**
- A valid cue array overwrites `draft.vtt` and sets `job.step` to `translation`
- A cue array with overlapping ranges does not write the file and returns errors
- Malformed JSON input does not write the file

No tests are needed for the Subtitle Editor view (PHP template), `subtitle-editor.js`, or `PipelineSteps` — these are shallow dispatch/render modules.

## Out of Scope

- Subtitle generation from Interpreter audio (Slice 3)
- Translation into additional Subtitle languages (Slice 4)
- Tagging (Slice 5)
- Publication (Slice 6)
- Cue split / merge operations (may be added in Slice 3 when reviewing auto-generated output)
- Inline formatting controls (`<b>`, `<i>`, `<ruby>`) in the cue text editor
- Cue positioning / layout settings (align, position, line, size)
- Keyboard shortcuts for the editor
- Autosave without step advancement
- Multiple simultaneous Jobs

## Further Notes

- The Subtitle Editor is the first instance of a universal caption file editor pattern; see ADR-0004 for the rationale. When Slices 3 and 4 extend it, all new cue-editing behaviour must be added to the shared editor components — not forked into separate views.
- `draft.vtt` is the single in-progress caption file throughout the Job. It is the Master subtitle by pipeline status once `job.step` advances past `subtitle-editor`, not by filename.
- The Vimeo Player JS SDK (`player.vimeo.com/api/player.js`) is loaded from Vimeo's CDN; no local copy needed.
- `data/jobs/current/` is not web-accessible. The PHP page load (not a direct file fetch) is the only way for JS to receive the VTT content.
