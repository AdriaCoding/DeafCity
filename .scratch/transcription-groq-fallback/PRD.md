Status: ready-for-agent

# PRD — Subtitle Generation: Groq primary engine with local faster-whisper fallback

> Architectural decision recorded in [ADR-0006](../../docs/adr/0006-groq-primary-faster-whisper-fallback-transcription.md). Read it first — it carries the rationale behind every decision below.

## Problem Statement

When a Producer chooses to generate Subtitles from Interpreter audio at Intake, the Studio transcribes the audio with `openai/whisper-large-v3-turbo` via the HuggingFace `transformers` pipeline on CPU. On the production host (2-core CPU, 8 GB RAM, no GPU, no swap) this runs at roughly 11× real-time: a 57-second clip did not finish in 10 minutes. The feature is effectively unusable — the Producer cannot get a draft Caption file in a reasonable time, and the whole pipeline stalls at its first step.

## Solution

Make transcription fast by default and resilient when the fast path is unavailable.

The Studio sends the Interpreter audio to the **Groq** cloud transcription API (OpenAI-compatible Whisper endpoint), which returns in ~1 second. On success, the Studio writes the draft Caption file and takes the Producer straight to the Subtitle Editor — no background job, no loading screen, ~2 seconds end to end. When Groq is genuinely unavailable (network/outage/quota), the Studio automatically falls back to a **local faster-whisper** engine (CTranslate2, int8) that runs on-host in a dedicated Studio venv, using the existing asynchronous loading-screen flow. When the failure is one the local engine cannot fix (bad audio) or that an operator must fix (bad key), the Studio fails loudly instead of wasting minutes on a doomed fallback.

The Producer experiences a near-instant transcription almost always, an honestly-explained few-minute wait on the rare fallback, and a clear error when their audio or configuration is the problem.

## User Stories

1. As a Producer, I want transcription of Interpreter audio to finish in a couple of seconds instead of minutes, so that I can start reviewing the Master subtitle immediately.
2. As a Producer, when transcription succeeds via the cloud, I want to land directly in the Subtitle Editor with my draft cues, so that I am not shown an unnecessary loading screen.
3. As a Producer, I want the Subtitle language I selected at Intake to be used as the transcription language hint, so that the cues come back in the right language even for code-switched audio.
4. As a Producer, when the fast cloud engine is unavailable, I want the Studio to automatically transcribe locally instead, so that an outage or quota limit does not block my work.
5. As a Producer, when the Studio falls back to the local engine, I want a short message on the loading screen explaining the fast engine is unavailable and this may take a few minutes, so that the longer wait is understood and not mistaken for a fault.
6. As a Producer, when I upload audio the engines cannot read, I want a clear Catalan error telling me the format is not recognised, so that I can re-upload a usable file rather than wait pointlessly.
7. As a Producer, when transcription cannot run because the cloud engine is misconfigured (bad key), I want a clear error rather than a silent multi-minute local detour, so that the real cause is visible and an admin can fix it.
8. As a Producer, when transcription fails loudly, I want to be returned to a clean Intake form with the error, so that no half-finished Job blocks the Studio.
9. As a Producer, I want the rare local fallback to still produce good-quality cues, so that the fallback is a real safety net and not a broken path.
10. As a Producer, I want very large audio uploads to still work, so that a long or high-bitrate Interpreter recording does not exceed the cloud upload limit and fail.
11. As a Producer, I want the draft Caption file from either engine to open in the Subtitle Editor exactly like an uploaded one, so that my review workflow is identical regardless of which engine ran.
12. As a developer, I want the engine and model that produced each transcription recorded on the Job, so that I can tell after the fact whether the cloud or local engine was used.
13. As a developer, I want one structured log line per transcription (engine, model, language, duration, wall-time, and any fallback-trigger category), so that I can audit whether the cloud engine is healthy without adding instrumentation.
14. As an operator, I want the Groq model, base URL, timeout, and local model to be configurable, so that I can swap models, repoint at another OpenAI-compatible host, or tune the timeout without code changes.
15. As an operator, I want a blank Groq API key to mean "use the local engine only", so that a host that never provisioned Groq still transcribes without erroring.
16. As an operator, I want to verify the live Groq integration on demand, so that I can confirm the key and endpoint work before relying on them.
17. As a developer, I want the old `transformers` transcription engine and its Blind Wiki venv coupling removed, so that there is a single, maintained local engine.
18. As a Producer, I want only one Job processed at a time as today, so that the new synchronous cloud path does not change the single-Job model of the Studio.

## Implementation Decisions

**Orchestration shape (ADR-0006): hybrid.** The cloud call is native PHP curl, run synchronously on the Intake POST request; the local fallback stays asynchronous, reusing the existing `run_transcribe.sh` → `transcribe.py` → `transcription.json` → loading-screen machinery. The Intake POST calls a new orchestrator instead of directly launching the background job.

**New deep modules (PHP, mirroring `GeminiTranslator`):**

- **`GroqTranscriber`** — given an audio file path, model, language hint, and config, performs the multipart Groq request (`temperature=0`, `response_format=verbose_json`, `language=<code>`) and returns a clamped cue array (`start`, `end`, `text`, `opaque=''`), applying the same monotonic clamp as the Python path (`start ≥ prev_end`; drop `start ≥ end`). On failure it throws a single exception type tagged with a **category** (`transport` / `auth` / `bad_input` / `empty`) so callers branch with a clean switch. Retry policy: 2 attempts total (1 retry, 1 s backoff) on 429/5xx/timeout; 20 s per-request timeout. The HTTP transport is an injected seam (no real network in tests).
- **`AudioPreprocessor`** — one method, `toGroqUpload(srcPath): tempFlacPath`, shelling ffmpeg to 16 kHz mono FLAC into the Job folder. The exec seam is injected. Non-zero ffmpeg exit raises.
- **`TranscriptionOrchestrator`** — the decision-maker called by the Intake POST. It transcodes, calls `GroqTranscriber`, and routes per the fallback matrix: success ⇒ write `draft.vtt` (via `VttParser::write` + `JobManager::writeDraftVtt`), stamp `transcription_engine` on the Job, return `editor`; `transport`/`empty` ⇒ spawn the async local job, return `loading`; `auth`/`bad_input` ⇒ destroy the Job, return `error` with a Catalan message; blank `GROQ_API_KEY` ⇒ skip Groq entirely, go straight to local. It performs no superglobal/header I/O so it is unit-testable. Returns a small result value the thin `index.php` branch maps to a redirect or re-render.

**Fallback matrix (authoritative):**

| Failure class | Examples | Action |
|---|---|---|
| transport / availability | network, connect/read timeout, 5xx, 429 after retries, residual 413 | fall back to local |
| empty result | 200 OK but no usable cues | fall back to local |
| auth / config | 401/403 | fail loud (destroy Job, clean Intake + error) |
| blank key | `GROQ_API_KEY === ''` | silently use local |
| bad input | 400 unsupported format / unreadable | fail loud (destroy Job, clean Intake + error) |

**Local fallback rewrite.** `transcribe.py` is rewritten to faster-whisper (CTranslate2, int8, `vad_filter=True`, `min_silence_duration_ms=500`), reading models from `studio/models/` in the dedicated `studio/.venv`. `run_transcribe.sh` is repointed at that venv and no longer sources the Blind Wiki venv or `transformers` cache. A `--model` argument is plumbed through `BackgroundJobLauncher::launchTranscription` from `STUDIO_LOCAL_TRANSCRIBE_MODEL`. The `transcription.json` status contract (`pending → running → done|error`) and the existing Catalan messages are preserved. The old `transformers` engine is deleted (confirmed: only `run_transcribe.sh`, `transcribe.py`, `BackgroundJobLauncher`, and one test reference it).

**Configuration** (all `defined()`-guarded so an un-updated `config.php` still boots), documented in `config.example.php`:

- `GROQ_API_KEY` (default `''` ⇒ local-only)
- `GROQ_TRANSCRIBE_MODEL` (default `whisper-large-v3-turbo`)
- `GROQ_BASE_URL` (default `https://api.groq.com/openai/v1`)
- `GROQ_TIMEOUT_SECONDS` (default `20`)
- `STUDIO_LOCAL_TRANSCRIBE_MODEL` (default `whisper-large-v3-turbo`)

**Provenance.** `transcription_engine` (e.g. `groq:whisper-large-v3-turbo`, `local:whisper-large-v3-turbo`) written to `job.json`; one structured line per run appended to `data/logs/studio.log`. On fallback the loading screen shows a short Catalan notice that the local engine is being used and may take a few minutes.

**Reuse.** Draft Caption files are serialised with the existing `VttParser::write()` and written via `JobManager::writeDraftVtt()`, identical to Subtitle Editor saves. The temp FLAC lives in the Job folder and is deleted after upload. `www-data` must be able to read/execute `studio/.venv` and `studio/models`.

**Catalan copy** is drafted by the implementer (idiomatic, `lang="ca"`), to be tweaked by the Producer during review — no pre-approval gate.

## Testing Decisions

A good test asserts external behaviour through a module's public interface, not its internals: feed inputs and injected seams, assert the returned value / written file / spawned command — never reach into private state. All automated tests run with mocked seams; none hit the network, ffmpeg, or a real model. Prior art: `BackgroundJobLauncherTest` (injected `$exec`, asserts the command string), the existing `VttParser`/`WebVttValidator`/`JobManager` PHPUnit tests, and the env-gated `test_vimeo_publish.php` live smoke script.

Modules to test (PHPUnit):

- **`GroqTranscriber`** — injected transport returns canned responses: `verbose_json` body ⇒ correct clamped cues; 401 ⇒ `auth`; 400 ⇒ `bad_input`; 429/500 ⇒ `transport`; 200-but-empty ⇒ `empty`; malformed JSON ⇒ `transport`. Assert the single retry fires only on 429/5xx/timeout.
- **`AudioPreprocessor`** — injected exec; asserts the ffmpeg command shape (16 kHz, mono, flac, Job-folder temp path) and that a non-zero exit raises.
- **`TranscriptionOrchestrator`** — mocked `GroqTranscriber` + mocked launcher + real `JobManager` on a temp dir; assert each matrix branch: success ⇒ `draft.vtt` written + `transcription_engine` stamped + result `editor`; `transport`/`empty` ⇒ launcher spawned + result `loading`; `auth`/`bad_input` ⇒ Job destroyed + result `error` + no spawn; blank key ⇒ straight to local, no Groq call.

Python (pytest in the Studio venv): the segments→VTT clamp helper only — deterministic conversion, not model inference.

Live coverage: an env-gated smoke script alongside `test_vimeo_publish.php` that performs one real Groq transcription when a flag/key is present. Model inference quality stays covered by the existing benchmark script (`studio/scripts/bench_transcription.py`) and its committed outputs under `studio/audio_samples/benchmark/`, not the automated suite.

## Out of Scope

- Producer-facing manual engine override ("use the local engine") — auto-fallback only for v1.
- Any engine badge or provenance shown in the Producer UI — provenance lives in `job.json` + the log.
- Interpreter-consent UI or data-egress controls — owner accepts egress; blanking the key is the no-egress lever.
- Diarization, per-segment language auto-detection, or transcribing in a language other than the chosen Subtitle language.
- Changes to Translation (Slice 4), Tagging, Publication, or the Catalog.
- Concurrency / multi-Job changes — the single-Job model is unchanged.

## Further Notes

- Benchmark evidence (this host, 3 real samples fr/it/ca incl. code-switched Catalan): transformers `large-v3-turbo` >10 min/unfinished; local faster-whisper int8 56–191 s; Groq 0.4–1.5 s round-trip. Outputs committed under `studio/audio_samples/benchmark/`.
- `GROQ_BASE_URL` keeps the OpenAI-compatible door open (OpenAI, self-host) without code changes.
- The lighter inline retry (vs ADR-0005's 3×/1-2-4 s ladder) is deliberate: the call is inline and the local fallback is strong, so fail toward the fallback fast.
- Update `docs/studio-pipeline-features.md` Slice 3 section to describe the Groq-primary / faster-whisper-fallback flow once shipped.
