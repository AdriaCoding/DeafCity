Status: shipped

# PRD — Studio Slice 1: Intake

## Problem Statement

The Producer (Antoni) needs to start the subtitle pipeline for a new Video. Today there is no way to do this inside the Studio — the auth gate was built, but the Studio shell is an empty placeholder. The Producer has no way to register a Video, attach a subtitle file, or progress through any pipeline step.

## Solution

Add an Intake flow to the Studio. The Producer pastes the Vimeo URL or ID for a Video they have already uploaded to Vimeo directly, selects the Sign language, Edition, and Subtitle language from curated dropdowns, and uploads the existing WebVTT subtitle file. Submitting the form creates a Job on the server. The Studio shell then reflects the active Job and lets the Producer resume or cancel across sessions.

## User Stories

1. As a Producer, I want to paste a Vimeo URL or numeric ID into the intake form, so that I don't have to manually strip the URL to find the ID.
2. As a Producer, I want the Studio to validate the Vimeo ID immediately at intake, so that I catch a wrong ID before investing time in the subtitle workflow.
3. As a Producer, I want the Video title fetched automatically from Vimeo at intake, so that I can recognise the Job by name without typing it myself.
4. As a Producer, I want to select the Sign language from a dropdown, so that the Job is tagged with the correct language without free-text errors.
5. As a Producer, I want to select the Edition from a dropdown, so that the Video is associated with the correct city chapter.
6. As a Producer, I want to select the Subtitle language from a dropdown, so that the caption file is tagged with the correct written language.
7. As a Producer, I want to upload a WebVTT subtitle file at intake, so that the Subtitle Editor receives a ready-to-review draft immediately.
8. As a Producer, I want the form to reject non-WebVTT files with a clear error, so that I don't discover a format problem later in the pipeline.
9. As a Producer, I want a single intake form (not multiple steps), so that I can create a Job in one submission.
10. As a Producer, I want the Studio shell to show me an active Job's Video title, Edition, and current pipeline step when I return after a session expires, so that I can immediately recognise what I was working on.
11. As a Producer, I want a "Resume" button on the shell when a Job is active, so that I can continue from where I left off.
12. As a Producer, I want a "Cancel Job" option on the shell with a confirmation dialog, so that I can discard a Job without accidentally losing it with a single misclick.
13. As a Producer, I want confirmation that cancelling deletes the uploaded subtitle file, so that I understand what will be lost before I confirm.
14. As a Producer, I want the shell to offer a "New Job" button when no Job is active, so that Intake is always reachable from the main Studio view.
15. As a Producer, I want only one Job to exist at a time, so that I am never confused about which Video is in progress.
16. As a Producer, I want a clear error message if the Vimeo ID does not correspond to a video on Antoni's account, so that I know immediately if I pasted the wrong ID.

## Implementation Decisions

### Architecture: Producer uploads Video to Vimeo directly

The Studio does not upload Videos to Vimeo. The Producer uses any Vimeo-native method (web UI, batch tool) before opening the Studio. At Intake, the Studio takes a Vimeo URL or ID, validates it via the Vimeo API, and stores the `vimeo_id` in the Job. See ADR-0003.

### Job storage

A single active Job lives in `data/jobs/current/`. The folder's existence is the canonical signal that a Job is active. There is no database or lock file — the presence of the directory is the state.

`job.json` inside that folder holds:
- `vimeo_id` — numeric string
- `video_title` — fetched from Vimeo at Intake
- `sign_language` — key matching `studio-config.json`
- `edition` — key matching `studio-config.json`
- `subtitle_language` — IETF language tag (e.g. `es`, `en`)
- `step` — current pipeline step name (set to `subtitle-editor` after Intake)

The uploaded WebVTT file is saved alongside `job.json` with a fixed name (e.g. `draft.vtt`) so subsequent slices know where to find it.

### Reference data

`data/studio-config.json` holds the three curated lists. Example shape:

```json
{
  "sign_languages": [
    { "id": "lse", "label": "LSE Spanish Sign Language" },
    { "id": "lsm", "label": "LSM Mexican Sign Language" }
  ],
  "editions": [
    { "id": "valencia-2020", "label": "Valencia 2020" },
    { "id": "mexico-2021",   "label": "Mexico City 2021" }
  ],
  "subtitle_languages": [
    { "id": "es", "label": "Spanish" },
    { "id": "en", "label": "English" },
    { "id": "it", "label": "Italian" },
    { "id": "fr", "label": "French" },
    { "id": "ca", "label": "Catalan" },
    { "id": "pt", "label": "Portuguese" }
  ]
}
```

This file is not a secret and must not live in `config.php`.

### Module: VimeoIdParser

Pure function. Accepts a raw string (URL or numeric ID). Extracts and returns the numeric Vimeo ID, or throws/returns an error for unrecognisable input.

Handles at minimum:
- Raw numeric string: `"123456789"`
- Standard URL: `"https://vimeo.com/123456789"`
- URL with trailing slug: `"https://vimeo.com/123456789/some-title"`
- URL with query string
- Vimeo manage URL: `"https://vimeo.com/manage/videos/639494119"`

### Module: VimeoClient

Thin wrapper around the Vimeo REST API. Uses the access token from `config.php`. Exposes one method for Intake: `getVideo(string $id)` — calls `GET /videos/{id}`, returns the video title on success, throws a typed exception if the video is not found or the account lacks access.

### Module: StudioConfig

Reads `data/studio-config.json` once and exposes typed accessors: `getSignLanguages()`, `getEditions()`, `getSubtitleLanguages()`. Returns plain arrays of `{id, label}` pairs for rendering dropdowns.

### Module: JobManager

Manages the `data/jobs/current/` lifecycle:
- `exists(): bool`
- `create(array $fields, UploadedFile $vtt): void` — creates the directory, writes `job.json`, moves the uploaded WebVTT file to `draft.vtt`
- `read(): array` — reads and decodes `job.json`
- `update(array $fields): void` — merges fields into `job.json` (used by later slices to advance `step`)
- `cancel(): void` — recursively deletes `data/jobs/current/`

All paths are relative to the configured data directory, not hardcoded, so tests can inject a temp directory.

### Intake handler (thin)

`IntakeHandler` plus routes in `studio/index.php` (`?action=intake`). On POST: parse the Vimeo ID, call `VimeoClient::getVideo()`, validate the subtitle file (`.vtt` extension and `WEBVTT` header via `WebVttValidator`), call `JobManager::create()`. Renders errors inline on the form on failure. On success, redirects to the active-Job shell view.

### Shell view

Two states:
- **No Job** — renders a "New Job" button linking to the intake form.
- **Active Job** — reads `JobManager::read()`, renders Video title + Edition + pipeline step label, a "Resume" button (links to the step's route), and a "Cancel" button (posts to a cancel action with a JS confirm dialog before submission).

### WebVTT validation

Server-side: check that the uploaded file has a `.vtt` extension and that its first non-empty line is `WEBVTT`. Reject with a clear error message otherwise. No client-side-only validation.

### Vimeo API credentials

`VIMEO_ACCESS_TOKEN` is already defined in `config.php`. The `VimeoClient` module reads it from the constant. No new secrets are needed.

## Testing Decisions

Good tests for this slice verify behaviour at module boundaries — inputs and outputs — without asserting on implementation details (file layout internals, private methods, HTTP client internals).

**VimeoIdParser** — unit test all input variants: plain numeric ID, standard URL, URL with slug, URL with query string, manage URL, empty string, non-Vimeo URL. This module is pure and has no dependencies; tests run instantly.

**StudioConfig** — unit test with a fixture `studio-config.json`. Assert that `getSignLanguages()` etc. return the expected array shape. Covers the JSON parsing and any missing-key handling.

**JobManager** — integration test against a real temp directory (no mocking). Test: create a Job and assert the directory and `job.json` exist; read returns the saved fields; update merges correctly; cancel removes the directory; `exists()` reflects each state. Prior art: `AuthGuardTest.php` in `studio/tests/` uses PHPUnit with no mocking.

**VimeoClient** — not unit-tested in isolation (network dependency). If a PHPUnit HTTP mock is introduced later, add coverage then. For now, the validation path (invalid ID) is exercised through an integration smoke test or manual QA.

**Intake controller and shell view** — not unit-tested. Covered by manual QA: submit the form with valid and invalid inputs, verify Job state in `data/jobs/current/`, verify shell renders both states correctly.

## Out of Scope

- Interpreter audio upload path — deferred to Slice 3.
- Subtitle Editor — Slice 2.
- Video upload from within the Studio — the Producer uploads to Vimeo directly (ADR-0003).
- Multiple concurrent Jobs — one Job at a time by design.
- Vimeo privacy enforcement — the video is assumed to already be on Vimeo in whatever state the Producer left it.
- Tagging at Intake — deferred to Slice 5.
- Any Catalog writes — Publication only (Slice 6).

## Further Notes

- `data/jobs/` is protected from direct web access via `.htaccess` (`Deny from all`) at `data/jobs/`.
- **Resume** links to `?action=subtitle-editor`; Slice 2 will replace the placeholder view with the Subtitle Editor.
- Studio loads `vimeo/vimeo-api` from `src/vendor/autoload.php` (shared with the main site); `studio/composer.json` declares the dependency for when Composer is available on the server.
- PHP's `upload_max_filesize` (currently 2 MB) is not a concern for WebVTT files, which are typically a few kilobytes.
- The `step` field in `job.json` is the hook later slices use to know where the Producer left off. Intake sets it to `subtitle-editor`; each subsequent slice updates it on completion.
- `data/studio-config.json` should be seeded with all Editions and Sign languages known as of the Marseille 2026 planning document, so the Producer does not encounter an empty dropdown on first use.
