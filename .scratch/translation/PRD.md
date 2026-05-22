Status: ready-for-agent

# PRD — Slice 4: Translation

## Problem Statement

After a Producer has reviewed and saved the Master subtitle for a Video, the Video only has subtitles in one Subtitle language. DEAF.city aims to reach audiences across multiple languages (Spanish, English, Italian, French, Catalan, Portuguese), but generating those translations manually is prohibitively slow. The Producer needs a way to automatically generate translated caption files for every remaining Subtitle language, review and correct them if needed, and then proceed in the pipeline — all without leaving the Studio.

## Solution

Add a Translation step to the Studio pipeline, immediately after the Subtitle Editor. When the Producer is satisfied with the Master subtitle, they click **Save & Translate** in the Subtitle Editor. The Studio saves the Master subtitle, spawns a background translation script that translates to every Subtitle language except the Master's, and shows a loading screen while translation runs. Once done, a Translation Hub lists all translated languages with their status. The Producer may open any translation in the Subtitle Editor for optional review and correction, then proceed to the Tagging step when ready — without being required to review every translation.

## User Stories

1. As a Producer, I want a "Save & Translate" button in the Subtitle Editor, so that I can commit the Master subtitle and trigger automatic translation in one action.
2. As a Producer, I want a "Save draft" button in the Subtitle Editor, so that I can save my work in progress without triggering translation.
3. As a Producer, I want the Studio to automatically translate the Master subtitle into all other configured Subtitle languages, so that I do not have to choose or trigger each language manually.
4. As a Producer, I want a loading screen while translations are being generated, so that I know the process is running and can leave and return.
5. As a Producer, I want the loading screen to auto-redirect me to the Translation Hub when all translations are ready, so that I don't have to manually refresh or navigate.
6. As a Producer, I want to see a Translation Hub listing all target Subtitle languages with their generation status, so that I have a clear overview of which translations are available.
7. As a Producer, I want to click on any language in the Translation Hub to open its translation in the Subtitle Editor, so that I can review and correct machine-generated cues.
8. As a Producer, I want my corrections to a translation to be saved back to the correct caption file for that language, so that the reviewed translation is preserved without overwriting other files.
9. As a Producer, I want to return to the Translation Hub after reviewing a translation, so that I can track which languages I have reviewed.
10. As a Producer, I want to proceed to the Tagging step directly from the Translation Hub without reviewing any translation, so that I am not blocked by mandatory review when the generated translations are acceptable.
11. As a Producer, I want to see a clear error badge on any language that failed to translate, so that I know which translations need attention.
12. As a Producer, I want a Retry button on failed translations in the Translation Hub, so that I can re-run translation for a single language without restarting the whole job.
13. As a Producer, I want caption file integrity validation when saving a reviewed translation, so that I cannot save a corrupted or structurally invalid caption file.
14. As a Producer, I want the Subtitle Editor to show the Vimeo player alongside the translated cues when reviewing a translation, so that I can verify the translated text against the video.
15. As a Producer, I want the "Save & Translate" button to be inactive (or show a validation error) if the current Master subtitle has caption file integrity errors, so that I cannot trigger translation from a broken file.
16. As a Producer, I want the pipeline step indicator in the Studio shell to show "Traducció" when the Job is in the Translation step, so that I can see at a glance where the Job is.
17. As a Producer, I want to resume a Job that is in the Translation step after closing the browser, so that I can continue translation review across sessions.
18. As a Producer, I want to see the translation loading screen when I resume a Job that is still translating, so that I am not left on a blank or broken page.

## Implementation Decisions

### Translation engine
Gemini 2.0 Flash via Google AI REST API (`gemini-2.0-flash:generateContent`). The API key is stored as `GEMINI_API_KEY` in `config/config.php` (gitignored). All six configured Subtitle languages (es, en, it, fr, ca, pt) are well-supported by the model. No local model or venv required. See ADR-0005 for the decision record (replaces the original local NLLB/T2TT engine, which was OOM-killed on the production host).

### Translation granularity — structured JSON, one call per language
The translation script sends all cue texts for a language pair as a numbered JSON array in a single Gemini call, with a `responseSchema` constraining the response to `{"translations": [...]}`. Timestamps are not translated — they are carried through unchanged from the Master. If the response array length does not match the input cue count, the script falls back to one Gemini call per cue as a recovery strategy. A length-aware system instruction asks Gemini to keep each translated cue within ~1.15× the source character count and preserve speaker tags verbatim.

### Target language selection — automatic, all remaining languages
The translation script derives the set of target Subtitle languages by taking the full list from `studio-config.json` and removing the Master's `subtitle_language`. The Producer makes no language selection; all remaining languages are always translated.

### Caption file for each translation — `draft_{lang}.vtt`
Each translated language is written as its own caption file in the Job folder using the pattern `draft_{lang}.vtt` (e.g. `draft_en.vtt`, `draft_fr.vtt`), where `lang` is the ISO 639-1 id from `studio-config.json`. The Master caption file remains `draft.vtt`.

### Translation status tracking — `translation.json`
A `translation.json` file in the Job folder tracks translation state. Its schema:

```json
{
  "status": "pending | running | done",
  "languages": {
    "en": { "status": "pending | running | done | error | reviewed", "message": "" },
    "fr": { "status": "error", "message": "Translation error: …" },
    "it": { "status": "done" }
  }
}
```

Top-level `status` is `done` once every language has resolved (done or error). The PHP `TranslationJobState` class is the sole owner of reading and writing this file.

### Pipeline step for translation
When the Producer clicks "Save & Translate", the `step` field in `job.json` is updated to `translation`. The `?action=translation` route then determines what to render based on `translation.json`:
- If `translation.json` is absent or top-level status is `pending | running` → show translation loading screen (polls `?action=translation-status` every 3 seconds, auto-redirects when `done | error`).
- If top-level status is `done | error` → show Translation Hub.

### Subtitle Editor — dual save buttons on Master, single save on translation review
When the Subtitle Editor is opened without a `lang` query parameter (Master mode), the toolbar shows three actions: **Save draft** (saves `draft.vtt`, no step change), **Save & Translate** (saves `draft.vtt`, spawns the translation script, advances step to `translation`), and **Skip to Tagging** (advances step directly to `tagging` without translation, prompts the unsaved-changes guard if edits are pending). "Skip to Tagging" is carried over from Slice 2 unchanged — it is the path for Productions that do not require additional Subtitle languages. When opened with `?lang=en` (translation review mode), only a single **Save** button is shown, which saves to `draft_en.vtt` and returns the Producer to the Translation Hub.

### Subtitle Editor — `lang` query parameter routing
`?action=subtitle-editor` (no `lang`) loads and saves `draft.vtt`. `?action=subtitle-editor&lang=en` loads and saves `draft_en.vtt`. The `SubtitleEditorHandler` receives the resolved file path and behaves identically otherwise (integrity check, VTT write). In "Save" mode for a translation, the step is not changed.

### Translation Hub
The Translation Hub is rendered at `?action=translation` when `translation.json` reports `done | error`. It shows one card per target Subtitle language containing: the language label, a status badge (Reviewed / Generated / Error), and for errored languages a Retry button. A **Proceed to Tagging** button is always visible and enabled — review is optional. Clicking a language card opens `?action=subtitle-editor&lang={lang}` in the same window. After saving a translation in the editor, the Producer is redirected back to the Translation Hub (`?action=translation`).

### Translation Retry
`POST ?action=translation-retry` with a `lang` body field re-runs `run_translate.sh` for a single language. The `translation.json` entry for that language is reset to `pending`, and the overall top-level status is reset to `running`. The Hub poll loop resumes automatically.

### Background process pattern
The translation script is spawned via `nohup` exactly as transcription is (`run_transcribe.sh` → `transcribe.py`). `run_translate.sh` execs `php studio/scripts/translate.php` with the same argv flags — no Python venv required. `GEMINI_API_KEY` is injected into the subprocess environment by the PHP spawner. Shell exit-code fallback writes an error status to `translation.json` if the PHP script exits non-zero before doing so itself.

### New PHP class: `TranslationJobState`
Encapsulates all reads and writes to `translation.json`. Does not touch `job.json`. Interface (approximate):
- `initiate(array $targetLangs): void` — writes initial `{ status: pending, languages: { lang: pending, … } }`
- `getTopLevelStatus(): string`
- `getLanguageStatus(string $lang): array`
- `markRunning(): void`
- `markLanguageDone(string $lang): void`
- `markLanguageError(string $lang, string $message): void`
- `resetLanguage(string $lang): void` — used by Retry

### JobManager additions
- `draftVttPathForLang(string $lang): string` — returns path to `draft_{lang}.vtt`
- `translationStatePath(): string` — returns path to `translation.json`

## Testing Decisions

Tests should exercise observable behaviour through the module's public interface — not implementation internals. They should not assert on private methods, file layout choices, or intermediate state not exposed by the API.

### `TranslationJobState`
Full PHPUnit coverage of all status transitions: initiation, marking languages done/error, top-level status resolution (done only when all languages have resolved), and the reset-language (Retry) path. Prior art: `JobManagerTest.php`.

### `SubtitleEditorHandler` (modified)
Cover the new routing behaviour: saving to a language-specific path (via `draftVttPathForLang`) vs the Master path; confirm that integrity errors are rejected identically in both modes; confirm that "Save & Translate" returns `ok: true` and that the `translate` flag is passed through correctly. Prior art: `SubtitleEditorHandlerTest.php`.

### `JobManager` (new path helpers)
Unit-test that `draftVttPathForLang('en')` returns the expected path relative to the job folder, and that `translationStatePath()` returns the expected path. Prior art: `JobManagerTest.php`.

## Out of Scope

- Choosing a subset of target languages — all remaining languages are always translated.
- Gemini API or any external translation service — T2TT is the only engine for this slice.
- Re-translation of a language after the Producer has edited it in the Subtitle Editor — Retry only applies to languages that errored at generation time.
- Re-running translation after the Master subtitle has been edited post-translation — a Producer who edits the Master must Cancel and restart the Job.
- Translating from a translation (only the Master is the translation source).
- Progress indication per-language during the translation run — the loading screen shows overall status only.
- Publication of translated caption files to Vimeo — that is Slice 6.

## Further Notes

- The Translation loading screen should reuse the visual pattern of the Slice 3 transcription loading screen for consistency.
- The Translation Hub "Proceed to Tagging" button advances `job.json` `step` to `tagging`.
- The Subtitle Editor, when opened in translation-review mode (`?lang=en`), should indicate in its header which language is being reviewed (e.g. "Editor de subtítols — English") so the Producer is not confused about which file they are editing.
- T2TT's `translate_text` method accepts a `source_lang` ISO 639-1 code and a `target_languages` dict. The translation script should pass each target language individually to avoid one slow language blocking others — or pass all at once and rely on T2TT's internal loop, accepting sequential execution.
- Models are already cached at `/srv/www/blind.wiki/public_html/Tagger/cache`; no download step is needed at runtime.
