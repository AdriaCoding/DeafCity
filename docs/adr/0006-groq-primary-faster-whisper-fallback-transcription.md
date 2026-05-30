# ADR-0006 — Groq API primary for transcription, local faster-whisper fallback

**Date**: 2026-05-30
**Status**: Accepted
**Relates to**: [ADR-0005](0005-gemini-flash-for-subtitle-translation.md) (same cloud-offload motivation, for translation)

---

## Context

Subtitle Generation (Slice 3) transcribes Interpreter audio to a draft Caption
file with `openai/whisper-large-v3-turbo`, run via the HuggingFace
`transformers` pipeline on CPU through the Blind Wiki venv. On the production
host (2-core CPU, 8 GB RAM, no GPU, no swap) this path runs at roughly **11×
real-time** — a 57-second clip did not finish in 10 minutes. It is effectively
unusable for the Producer.

Benchmarks on three real Interpreter-audio samples (fr, it, ca — including a
code-switched Catalan/Spanish recording) compared three engines:

| Engine | Wall time (46–108 s audio) | Notes |
|---|---|---|
| transformers `large-v3-turbo` (current) | >10 min, often unfinished | unusable on this host |
| local **faster-whisper** CT2 int8 `large-v3-turbo` | 56–191 s (RTF ~1.0–1.8) | ~7–10× faster, good quality, fully local |
| **Groq** API `whisper-large-v3` / `-turbo` | **0.4–1.5 s round-trip** | ~100–300× faster wall-clock; quality on par or better |

Constraints and facts:
- Same host limits as ADR-0005: local neural inference is slow/fragile here.
- A strong, fully-local engine (faster-whisper) now exists as a genuine
  fallback — something the translation slice never had.
- Groq exposes an OpenAI-compatible `audio/transcriptions` endpoint
  (`Authorization: Bearer`, multipart `-F`), reachable directly from PHP curl.
- Groq caps upload size (free tier ~25 MB); raw interpreter wavs can exceed it.
- Interpreter audio is the *interpreter's* spoken translation, not Participant
  data; the project owner accepts sending it to Groq (and text to Google).

## Decision

Make **Groq the primary transcription engine, with local faster-whisper as an
automatic fallback.**

Key design choices:

- **Cloud call in PHP, synchronously, on the intake request.** Mirrors the
  ADR-0005 "cloud = native PHP curl" pattern. On success the happy path writes
  `draft.vtt` and redirects straight to the Subtitle Editor — **no background
  job, no loading screen, ~2 s.** New seams: `GroqTranscriber`,
  `AudioPreprocessor`, `TranscriptionOrchestrator` (mirroring `GeminiTranslator`).
- **Local fallback stays async**, reusing the existing
  `run_transcribe.sh` → `transcribe.py` → `transcription.json` machinery and
  loading screen — but **`transcribe.py` is rewritten** to faster-whisper
  (CTranslate2, int8) in a dedicated **`studio/.venv`** with models under
  `studio/models/`. This severs transcription's dependency on the Blind Wiki
  venv and deletes the transformers path.
- **FLAC preprocessing.** Before upload, ffmpeg transcodes to 16 kHz mono FLAC
  (≈11× smaller; 31 MB wav → ~3 MB) into the Job folder. Removes the size cap
  as a normal-case failure and speeds the inline upload. Whisper resamples to
  16 kHz mono internally, so no quality loss.
- **Fallback matrix** — fall back to local only when the cloud is unavailable
  but the work is still doable here; fail loud when local would fail the same
  way or an operator must intervene:

  | Failure class | Examples | Action |
  |---|---|---|
  | transport / availability | network, timeout, 5xx, 429 (after retries), residual 413 | **fall back to local** |
  | empty result | 200 OK, no usable cues | **fall back to local** |
  | auth / config | 401/403, **blank `GROQ_API_KEY`** | blank key ⇒ silently go local; 401/403 ⇒ **fail loud** |
  | bad input | 400 unsupported format | **fail loud** (local would reject too) |

- **Lighter retry than ADR-0005.** Because the call is inline *and* the local
  fallback is strong, fail toward the fallback fast: 2 attempts (1 retry, 1 s
  backoff) on 429/5xx/timeout, 20 s per-request timeout. This deliberately
  diverges from ADR-0005's 3×/1-2-4 s ladder, which was tuned for a background
  call with no fallback.
- **Loud Groq failure destroys the just-created Job** and returns the Producer
  to a clean intake form with a Catalan error.
- **Provenance:** `transcription_engine` (e.g. `groq:whisper-large-v3-turbo` /
  `local:whisper-large-v3-turbo`) is written to `job.json`, plus one structured
  line per run in `data/logs/studio.log` (engine, model, lang, duration,
  wall-time, and the fallback-trigger category). On fallback the loading screen
  shows a short Catalan "using the local engine, may take a few minutes" notice.
- **Configurable** via `config.php` (all `defined()`-guarded): `GROQ_API_KEY`,
  `GROQ_TRANSCRIBE_MODEL` (default `whisper-large-v3-turbo`), `GROQ_BASE_URL`
  (default `https://api.groq.com/openai/v1`), `GROQ_TIMEOUT_SECONDS` (20),
  `STUDIO_LOCAL_TRANSCRIBE_MODEL` (default `whisper-large-v3-turbo`).

## Consequences

**Benefits**:
- Happy-path transcription drops from minutes/never to ~2 s, with no background
  job or polling.
- A real, fully-local fallback covers Groq outages, quota exhaustion, and the
  no-egress case (blank the key ⇒ 100 % local).
- Removes the unusable transformers engine and the Blind Wiki venv coupling.
- `GROQ_BASE_URL` keeps the OpenAI-compatible door open (OpenAI, self-host).

**Trade-offs**:
- The intake request now does bounded network + ffmpeg I/O on the FPM thread
  (acceptable: one Job at a time, 20 s ceiling).
- Two transcription code paths (PHP cloud + Python local) instead of one.
- Interpreter voice audio egresses to Groq (US cloud) — accepted by the owner;
  the local engine remains a standing no-egress alternative.

## Alternatives considered

| Alternative | Reason rejected |
|---|---|
| Keep transformers, add swap / smaller model | Host swap is an ops risk; smaller models lose quality and still run on CPU. |
| faster-whisper local as the *primary* | Works (RTF ~1–1.8) but ~100× slower than Groq and ties up the 2-core host; better as the fallback. |
| Groq call in Python (inside `transcribe.py`) | Spawns a subprocess for a 1 s HTTP call and contradicts ADR-0005's PHP-direct cloud pattern; the venv is only unavoidable for the local fallback. |
| Everything async (Groq via background job too) | Keeps an unnecessary loading screen/poll for a ~1 s call; loses the big UX win. |
| Mirror ADR-0005 retry ladder exactly | Over-retries inline when a fast, strong local fallback already exists. |
