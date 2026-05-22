# Plan — Replace local NLLB with Gemini 2.0 Flash (PHP-only)

## Context

Translation Slice 4 shipped with a local NLLB-200-distilled-600M model loaded via the Blind Wiki venv. On the 8 GB CPU-only host (no swap), the model's ~2.3 GB RSS plus load-time transients exceeded available headroom and the kernel OOM-killed Python mid-job. A real translation job is currently stuck at `data/jobs/current/translation.json` with `status: running` and all five targets `pending`.

Antoni has decided to drop the local model entirely in favour of **Gemini 2.0 Flash via API**, called **directly from PHP** (no Python subprocess for translation). At Antoni's volume (~250K chars/month) cost is sub-cent monthly; the same API key doubles as his personal Google AI Studio key.

The job-state contract, loading screen, hub, retry endpoint, and `TranslationJobState` class all stay unchanged. The swap is below those layers: the script that PHP nohups is replaced (`run_translate.sh` → exec PHP CLI instead of Python venv), and the translation engine becomes a small PHP class calling Gemini's REST API.

## Architecture summary

```
studio/index.php  spawnTranslationJob()  (unchanged signature)
        │
        ▼
nohup run_translate.sh   (rewritten: no venv, no Python; just exec php)
        │
        ▼
php studio/scripts/translate.php   (NEW CLI entry, ~30 lines)
        │
        ▼
TranslationRunner             (NEW orchestrator, in studio/src/)
        │  reads master VTT via VttParser
        │  updates TranslationJobState per language
        │  writes per-lang VTT via VttParser
        ▼
GeminiTranslator              (NEW HTTP client, in studio/src/)
        │  length-aware system prompt + JSON-schema-constrained response
        │  cURL, 3-attempt exponential backoff, per-cue fallback on shape mismatch
        ▼
https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent
```

## Files to add

### `studio/src/GeminiTranslator.php`
Thin REST client. Constructor takes API key and an HTTP callable (defaults to a native cURL closure; lets tests inject a mock without adding Guzzle as an explicit dep). Single public method:

```php
public function translate(array $cues, string $srcLang, string $tgtLang): array
```

- Builds request: `systemInstruction` with the length-aware prompt, `contents` with a numbered JSON list of cue texts, `generationConfig.responseMimeType=application/json` and a `responseSchema` requiring `{"translations": ["...", ...]}`.
- 3 attempts with exponential backoff (1s/2s/4s); retries on 429 and 5xx, fails fast on other 4xx.
- On response-count mismatch, falls back to per-cue translation (one call per cue) before giving up.
- Returns `string[]` of the same length as input cues, or throws `GeminiTranslationException` with a message that bubbles into `TranslationJobState::markLanguageError()`.

Length-aware system prompt (per language):
```
You translate video subtitle cues from {SRC} to {TGT}.
Hard constraints:
- Return exactly N translations in the same order as the inputs
- Keep each translation within ~1.15× the source character count when natural
- Preserve speaker tags like [Music], (laughs) verbatim
- Preserve punctuation conventions of the target language
- Do not merge, split, or reorder cues
Return JSON only.
```

### `studio/src/TranslationRunner.php`
Orchestrator. Takes `JobManager`, `TranslationJobState`, `VttParser`, `GeminiTranslator`, and a logger callable. Single public method:

```php
public function run(string $masterVttPath, string $srcLang, array $targetLangs): void
```

- Calls `$state->markRunning()`.
- Parses master VTT via existing `Studio\VttParser`.
- For each target lang: marks language running (note: add `markLanguageRunning()` helper to `TranslationJobState` — small extension matching existing methods), calls `GeminiTranslator::translate()`, substitutes translated text back into the parsed cue structure, writes `draft_{lang}.vtt` via `VttParser::write()`, calls `$state->markLanguageDone($lang)` on success or `markLanguageError($lang, $msg)` on failure. Continues to next language on per-language error.
- Logs each step to `data/logs/studio.log` via a small file_put_contents-based logger (same line format as the existing shell-side logging in `run_translate.sh`).

### `studio/scripts/translate.php`
Thin CLI entry, ~30 lines. Parses `--master_vtt`, `--status_file`, `--source_lang`, `--job_dir`, `--target_langs` (same flags as today's Python script — keeps `studio/index.php` call site unchanged). Loads `config/config.php`, instantiates `JobManager`, `TranslationJobState`, `VttParser`, `GeminiTranslator`, `TranslationRunner`, calls `run()`. Wraps everything in try/catch that writes a fallback error to the status file before exiting non-zero (so `run_translate.sh`'s safety net stays compatible).

### `studio/tests/GeminiTranslatorTest.php`
Unit-style. Injects a fake HTTP callable returning canned JSON. Cases:
- Happy path returns array of correct length
- Retry on 429 (assert exponential backoff doesn't actually sleep in tests via injectable sleep callable)
- 4xx fails fast
- Count mismatch triggers per-cue fallback
- Schema-violating JSON triggers exception

### `studio/tests/TranslationRunnerTest.php`
Integration-style, matches existing test idiom (real temp filesystem, no global mocks). Injects a stub `GeminiTranslator` returning canned translations. Cases:
- Happy path writes one VTT per target lang and marks each done
- One language failing leaves others done and that language in error
- Master VTT parsing/writing round-trips via real `VttParser`

### `config/config.example.php`
Template documenting all required constants (`STUDIO_PASSWORD`, `VIMEO_*`, and the new `GEMINI_API_KEY`). Currently no template exists; add this so a fresh checkout has guidance.

### `docs/adr/0005-gemini-flash-for-subtitle-translation.md`
Short ADR superseding the implicit "T2TT only" decision in the Slice 4 PRD. Records the OOM incident, the decision drivers (cost ~free at our volume, Catalan support, removed local-Python dependency), and the rejection of alternatives (DeepL pending Catalan verification, OpenAI similar but slightly pricier, CTranslate2 quantization rejected as not worth the operational complexity).

## Files to modify

### `studio/scripts/run_translate.sh`
- Drop `LD_PRELOAD`, `OMP_NUM_THREADS`, `TRANSFORMERS_CACHE`, `HF_HOME` exports
- Drop venv activation
- Replace Python call with: `exec php /srv/www/deaf.city/public_html/studio/scripts/translate.php "$@"`
- Keep the existing error fallback that writes to `studio.log` and `translation.json` on non-zero exit (still useful as a belt-and-braces guard against PHP fatals)
- Expects `GEMINI_API_KEY` to be set in env (passed by PHP at spawn time)

### `studio/index.php` (`spawnTranslationJob()`, lines 53–86)
- Read `GEMINI_API_KEY` from config and inject into the spawn env: prefix the `nohup` command with `GEMINI_API_KEY=… ` (escapeshellarg'd). Argv flags stay identical — no change to call sites at lines 203–208 and 287–293.

### `config/config.php` (gitignored)
- Add `define('GEMINI_API_KEY', '…');` (Antoni provisions the key from Google AI Studio)

### `.scratch/translation/PRD.md`
- Append an "Engine update" note at the top: Slice 4's local-NLLB engine superseded by Gemini 2.0 Flash; pipeline shell, state contract, and views unchanged. Cross-reference ADR-0005.

### `studio/src/TranslationJobState.php`
- Add `markLanguageRunning(string $lang): void` (small symmetrical helper next to the existing `markLanguageDone`/`markLanguageError`). The Python script previously didn't write a "running" state per language; doing so in PHP gives the loading screen a tighter signal.

## Files to delete

- `studio/scripts/translate.py` — replaced
- (Keep `studio/scripts/studio_log.py` — still used by `transcribe.py`)

## Reused existing utilities

- `Studio\VttParser` (`studio/src/VttParser.php`) — cue parse/write; no reimplementation
- `Studio\JobManager` — `draftVttPath()`, `translationStatePath()`, target-VTT paths
- `Studio\TranslationJobState` — `markRunning`, `markLanguageDone`, `markLanguageError`, plus new `markLanguageRunning`
- `studio/index.php` `spawnTranslationJob()` signature — call sites at lines 203 and 287 stay byte-identical

## One-off operational steps

1. Antoni creates a Google AI Studio API key and sets a Google Cloud billing alert (recommended for sanity, not enforced in code).
2. Add `GEMINI_API_KEY` to `/srv/www/deaf.city/public_html/config/config.php`.
3. Reset the stuck job: overwrite `data/jobs/current/translation.json` with a fresh `pending` state (or delete `data/jobs/current/` if the job can be reissued from the editor). Do this manually after deploying the engine swap so the first run exercises the new path.

## Verification

End-to-end (golden path):
1. `cd studio && ./vendor/bin/phpunit` — full suite green, including new translator tests.
2. From the Studio UI, open a recent job, click **Save & Translate**. Loading screen should show each target language transition `En cua → Generant → Fet` (now driven by the new `markLanguageRunning` helper).
3. Confirm five `draft_{lang}.vtt` files appear in `data/jobs/current/` and each parses cleanly via `VttParser`.
4. Hub view loads; clicking through a per-language review screen shows reasonable Catalan/French/etc. translations with cues approximately aligned in length.
5. `tail data/logs/studio.log` shows the new PHP runner log lines, no Python tracebacks, no shell-fallback error.

Failure-mode probes:
6. Temporarily set `GEMINI_API_KEY` to an invalid value. Re-run translation. Expect each language to land in `error` with a clear message; hub shows error badges; retry button on a single language exercises the lines-287 retry path.
7. Restore the key. Use the per-language retry to recover and confirm the flow completes.

Operational:
8. `ps aux | grep translate` after a job completes — no lingering Python or PHP processes.
9. `du -sh /srv/www/blind.wiki/public_html/Tagger/cache` is no longer growing from deaf.city usage (and can eventually be pruned, but not as part of this slice).
