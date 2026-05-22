# ADR-0005 — Use Gemini 2.0 Flash for subtitle translation

**Date**: 2026-05-22
**Status**: Accepted
**Supersedes**: implicit "T2TT/NLLB-200 only" decision in Slice 4 PRD

---

## Context

Translation Slice 4 shipped with a local NLLB-200-distilled-600M model loaded
via the Blind Wiki Python venv. On the production host (8 GB RAM, CPU-only,
no swap), the model's ~2.3 GB RSS plus load-time transients exceeded available
headroom and the kernel OOM-killed the Python process mid-job, leaving the
translation state stuck at `status: running`.

Constraints:
- Host has 8 GB RAM, no GPU, no swap — local neural models are not viable.
- Target languages include Catalan (ca), which some translation APIs handle
  poorly or do not support at all.
- Antoni's translation volume is approximately 250 000 characters per month.

## Decision

Replace the local NLLB Python subprocess with a direct PHP call to the
**Gemini 2.0 Flash** REST API (`gemini-2.0-flash:generateContent`).

Key design choices:
- **No new Composer dependencies**: the HTTP call uses native PHP `curl_*`.
- **JSON-schema-constrained response** (`responseMimeType: application/json`,
  `responseSchema`) to guarantee a `{"translations": [...]}` envelope.
- **3-attempt exponential backoff** (1 s / 2 s / 4 s) on HTTP 429 and 5xx;
  fast-fail on other 4xx errors.
- **Per-cue fallback**: if the batch response count mismatches the input cue
  count, the runner retries each cue individually before giving up.
- **`markLanguageRunning()`** added to `TranslationJobState` so the loading
  screen can show a tighter `En cua → Generant → Fet` signal per language.
- The job-state contract, loading screen, hub, retry routing, and
  `spawnTranslationJob()` call sites are unchanged.

## Consequences

**Benefits**:
- Zero RAM overhead on the host — all inference runs in Google's cloud.
- Estimated cost < $0.01/month at current volume (Gemini Flash input/output
  pricing as of 2026-05).
- Catalan supported natively; quality superior to the distilled NLLB model.
- Removes the Python venv dependency for translation (Python is still used for
  transcription via Whisper/Blind Wiki venv — unaffected by this change).

**Trade-offs**:
- External API dependency; translation fails gracefully (per-language error
  state) when the API is unreachable or quota is exhausted.
- API key must be provisioned and kept in `config/config.php` (gitignored).
  A billing alert on Google Cloud is recommended.

## Alternatives considered

| Alternative | Reason rejected |
|---|---|
| CTranslate2 quantized NLLB | Operational complexity; still requires ~1 GB RAM; marginal quality gain over baseline; not worth maintaining. |
| DeepL | Catalan support not confirmed at time of decision; pricing similar. |
| OpenAI GPT-4o mini | Similar cost, slightly pricier at our volume; no structured JSON-schema output guarantee without function-calling. |
| Keep local Python + swap | Adding swap to a shared host is an ops risk; model load time alone exceeds acceptable UX for a 30-second job. |
