# Implementation plan — SRT becomes the primary caption format

**Owner:** Claude. Supersedes `PRD.md` (kept for reference; its inventory has gaps —
see "Corrections carried over" at the bottom).

**Goal:** SRT is what Studio stores, serves, mirrors to Vimeo, downloads, and works
in internally. VTT remains accepted as an upload input only.

**Baseline at time of writing (2026-08-07):** `cd studio && vendor/bin/phpunit` →
OK, 458 tests, 1225 assertions. `data/captions/` = 288 files, 288 of them `.vtt`
(no mixed extensions). `data/caption-translation/` = 42 job dirs, 39 with
`current/`, 268 `draft_*.vtt` files. `data/` already backed up by the user.

---

## Governing principle

**Make every reader format-agnostic before flipping any writer.**

The PRD's phase-straddle bugs all share one root cause: a phase flips *what is on
disk* while some reader still hardcodes a parser. If readers sniff the format
first, every milestone is independently deployable and independently revertible,
and a half-migrated `data/` directory is a valid state rather than an outage.

This costs one extra class (`CaptionReader`) and buys the ability to stop at any
milestone boundary — which is what was asked for.

---

## Milestones

Legend: **[STOP]** = hand back for review/validation before proceeding.

### M0 — Safety net (no behavior change, no data change)

- `SrtParser::write(array $cues): string` — the single SRT serializer.
  `VttToSrtConverter::writeCues()` is refactored to delegate to it (it is already
  byte-identical today; do not ship two SRT writers).
- New `Studio\SrtValidator` — structural validation for SRT, the counterpart to
  `WebVttValidator`. Today an uploaded `.srt` gets **no** validation at all
  (`CaptionUploadHandler::validateCaptionFile()` returns early); after the flip
  that would be the primary stored format, so this gap gets closed before, not
  after.
- New tests: `VttToSrtConverterTest`, `SrtValidatorTest`, `SrtParser::write()`
  coverage.
- Strengthen `CaptionFormatParityTest` into the bidirectional regression net:
  VTT→SRT and SRT→VTT must agree cue-for-cue on both real fixtures
  (`production_sample.vtt`, `ALGER_FR_Hamida_1.srt`). **This is the one test that
  must never go red in any later milestone.**
- New read-only script `studio/scripts/verify_caption_conversion.php` — converts
  all 288 real `data/captions/*.vtt` in memory and asserts cue-for-cue
  `{start,end,text}` equality against the source. Writes nothing. This is the
  corpus-level proof that M4's data migration is lossless, run *before* M4.

**Exit:** suite green, `verify_caption_conversion.php` clean on all 288 files.
No stop.

---

### M1 — Format-agnostic readers + the public-player parser fix

Still no data change and no user-visible change — but this is where the two real
bugs in the current preview endpoint get fixed, ahead of any data moving.

- New `Studio\CaptionReader` — `read(string $path)` / `readString(string)` sniffs
  SRT vs VTT by content and delegates to `SrtParser` / `VttParser`. Returns the
  existing cue shape plus a `format` key.
- New `Studio\CaptionWriter` — serialises back to the format `CaptionReader`
  detected. **Added during implementation**, not in the original plan: reading
  format-agnostically is only half the fix. `SubtitleEditorHandler` reads a file,
  swaps its cues, and writes it back, so without format preservation the caption
  editor would rewrite every `.srt` as WebVTT for the whole stretch between M4
  (storage flips) and M5 (pipeline flips) — the same straddle bug this plan was
  built to avoid, one layer down.
- Swap every **read** site to it: `SubtitleEditorHandler` (both `handle()` and
  `handleForFilePath()`), `CatalogAction::captionReview()` (`CatalogAction.php:675,
  691`), `TranslationRunner::run()`, `VttToSrtConverter::convert()`.
- `preview/captions-static.php`:
  - Extract `timestampToMsStatic()` / `parseVttStatic()` into
    `preview/lib/caption_cues.php` (PHP 5.6-compatible, matching that file's
    existing `function_exists` guard style), renamed to a format-neutral
    `vpc_parse_caption_cues()` / `vpc_timestamp_to_ms()`.
  - **Fix the timing regex** — today `/^([\d:\.]+)\s+-->\s+([\d:\.]+)/` has no
    comma in its character class, so an SRT timestamp matches *nothing* and every
    cue is silently dropped (verified: `preg_match` returns 0 matches on
    `00:00:01,500 --> 00:00:03,250`). Accept both `.` and `,` decimals.
  - **Fix the float cast** — `(float)"01,500"` is `1.0`, losing the milliseconds.
    Replace `,` with `.` before casting, as `SrtParser::parseTime()` already does.
  - Widen the `?f=` allowlist to accept `.srt` **or** `.vtt` (both must work
    during and after migration).
  - Delete the now-meaningless `substr($block,0,6) === 'WEBVTT'` header skip — the
    existing loop already ignores any pre-timing line, so SRT's numeric index needs
    no special handling.
- New `preview/tests/captions_static_parse_test.php` following the existing
  self-asserting `preview/tests/` convention, with a `,500`-style fixture that
  would fail silently without the fix.

**Exit:** suite green; new preview test green; public player still renders VTT
captions in sync (nothing has moved yet). No stop — but I'll load one real video
page as a smoke check and report.

---

### M2 — Consolidate the 5-site intake idiom (pure refactor)

- Extract `Studio\CaptionIntakeNormalizer` — one method that detects, validates,
  and returns normalized content. **Still emits VTT at this milestone.**
- Rewire all five call sites: `CaptionUploadHandler`, `TranscriptionIntakeHandler`,
  `ShortenIntakeHandler`, `BulkItemProcessor`, `ShortenBulkItemProcessor`
  (each currently duplicates the detect→convert→validate shape plus an identical
  private `validateVttContent()` helper).

**Exit:** the existing test suite passes **unmodified**. That is the proof of zero
behavior change and gives a clean bisect point before anything flips. No stop.

---

### M3 — [STOP] Remove the VTT download surfaces

Purely user-visible removal, and fully independent of storage format — the SRT
download buttons already work today via on-the-fly conversion. Done before the
data flip so it can be reviewed and reverted entirely on its own.

Three route pairs, not two — the PRD missed the catalog one:
- `studio/index.php` — delete `download-vtt` (83), `shorten-download-vtt` (78),
  `continguts-download-caption-vtt` (96).
- `DownloadAction::downloadVtt()`, `ShortenAction::downloadVtt()`,
  `CatalogAction::downloadCaption()`'s VTT branch (375-378) and its route (84).
- Views: `translation-review.php:204` + CSS at 53/65/66;
  `continguts-caption-editor.php:260` + CSS at 118/130/131;
  `transcription-loading.php:290,293`; `shorten-loading.php:178`.
- JS: `continguts-caption-editor.js` `downloadVtt()` (167-171) + its var/busy-array/
  getElementById/listener wiring; `translation-review.js` the same (129-133, 12,
  180, 186); `caption-table.js:170` — the `['vtt','srt']` loop becomes `['srt']`.

Upload affordances stay untouched: `.vtt` remains accepted everywhere
(`transcription-intake.php`, `shorten-intake.php`, `continguts-video.php:626`,
`caption-table.js:76`).

**Validation:** every remaining download button produces a file that opens with a
numeric index and comma timestamps; no dead buttons or 404 routes anywhere in
Studio. This milestone alone satisfies the literal "no more VTT downloads" ask.

---

### M4 — [STOP] Published captions become SRT

The first milestone that changes what is on disk and what the public site serves.

- `CaptionFilename::forVideo()` → `.srt`. This cascades automatically to
  `CaptionPublication`, `CaptionMigrationPlanner`, `CatalogEditor`, and the Vimeo
  mirror (all already extension-agnostic).
- `CaptionIntakeNormalizer` gains an explicit target format; wire in
  `SrtValidator`; `IntakeSourceDetector::isSubRip()` → `isWebVtt()` (rename +
  invert, and invert `IntakeSourceDetectorTest:38-48`'s assertions accordingly).

  **Refined during implementation:** flipping the normalizer wholesale was
  wrong. It feeds two stores — published captions under `data/captions/` (SRT
  from this milestone) and the job pipeline's drafts (VTT until M5) — so a
  single flip would have put SRT bytes into `draft.vtt` while `revise.php` and
  `TranscriptionOrchestrator` still expected VTT, and would have left subtitle
  uploads and audio transcription producing different formats in the same job
  directory. The target is a parameter instead: `CaptionUploadHandler` asks for
  SRT, the four job-intake sites pass `FORMAT_VTT`. M5 drops those arguments and
  the parameter can go in M7. This also means an uploaded `.srt` is stored
  byte-identical rather than round-tripped through VTT.
- **Bridge the two finalize copy sites** that would otherwise write VTT bytes into
  a `.srt` filename: `CatalogAction::finalizeCaptionTranslation()` (line 988) and
  `batch_translate_captions.php` (line 200) both `copy()` a job draft straight into
  `CaptionFilename::forVideo()`. Convert on copy here. M5 deletes these bridges
  once the drafts are natively SRT.
- Data migration `studio/scripts/migrate_captions_vtt_to_srt.php`, modeled on
  `migrate_caption_filenames.php` (dry-run default, `--apply`, `flock()`ed catalog
  write, abort-all-on-error): converts `data/captions/*.vtt` content, renames, and
  rewrites `catalog.json`'s `captions[].file`.
- Literal-only test fixture updates across the boundary tests.

The surviving SRT download paths keep working across this flip for free: they all
route through `VttToSrtConverter::convert()`, which became format-sniffing in M1,
so handing it an already-SRT file is a lossless no-op rather than the silent
zero-cue failure it would be today.

**Validation to run together before I proceed:**
1. `verify_caption_conversion.php` clean, then dry-run the migration and review.
2. After `--apply`: diff `preview/captions-static.php` JSON output against the
   pre-migration output for a sample of videos — cue starts/ends/text identical.
3. Load real video pages: captions render, in sync, language switching works.
4. Open the Studio catalog caption editor for a video: cues load, edit + save
   round-trips.
5. Publish captions for one test video and confirm in Vimeo's own UI that they
   still display — this is the live proof that Vimeo accepts SRT bytes (the API is
   format-blind and documents SubRip support, but it has not been tested here).

**Rollback:** restore `data/` from the backup and revert the deploy. Nothing in
M0–M2 needs unwinding.

---

### M4 — [STOP] Remove the VTT download surfaces

Purely user-visible removal, deliberately separated so it can be reviewed on its
own and reverted without touching data.

Three route pairs, not two — the PRD missed the catalog one:
- `studio/index.php` — delete `download-vtt` (83), `shorten-download-vtt` (78),
  `continguts-download-caption-vtt` (96).
- `DownloadAction::downloadVtt()`, `ShortenAction::downloadVtt()`,
  `CatalogAction::downloadCaption()`'s VTT branch (375-378) and its route (84).
- Views: `translation-review.php:204` + CSS at 53/65/66;
  `continguts-caption-editor.php:260` + CSS at 118/130/131;
  `transcription-loading.php:290,293`; `shorten-loading.php:178`.
- JS: `continguts-caption-editor.js` `downloadVtt()` (167-171) + its var/busy-array/
  getElementById/listener wiring; `translation-review.js` the same (129-133, 12,
  180, 186); `caption-table.js:170` — the `['vtt','srt']` loop becomes `['srt']`.

Upload affordances stay untouched: `.vtt` remains accepted everywhere
(`transcription-intake.php`, `shorten-intake.php`, `continguts-video.php:626`,
`caption-table.js:76`).

**Validation:** every remaining download button produces a file that opens with a
numeric index and comma timestamps; no dead buttons or 404 routes anywhere in
Studio.

---

### M5 — [STOP] Internal job pipeline becomes SRT

Touches live job state, so it needs a quiet window.

- `JobManager`: `draftVttPath()`→`draftPath()`, `writeDraftVtt()`→`writeDraft()`,
  `draftVttPathForLang()`→`draftPathForLang()`, `writeDraftVttForLang()`→
  `writeDraftForLang()`, `hasDraftVtt()`→`hasDraft()`, plus `create()`'s hardcoded
  `/draft.vtt` literal (line 49). Rename + full call-site sweep in one commit — no
  shims, since grep is the actual safety net in PHP.
- `TranscriptionOrchestrator` and `SubtitleEditorHandler` write via `SrtParser`
  (they already *read* via `CaptionReader` from M1); `TranslationRunner` writes SRT
  and validates with `SrtValidator`.
- `studio/scripts/transcribe.py` — the local Whisper fallback, a separate non-PHP
  VTT writer. `words_to_vtt()` (lines 56-69) becomes `words_to_srt()`; note its
  empty-output sentinel `if vtt.strip() == "WEBVTT"` (line 123) must become a plain
  emptiness check, since SRT has no header. Keep `bench_transcription.py`
  consistent.
- **`revise.php` bridge:** the draft is now SRT but `GeminiReviser` still speaks
  VTT until M6. `revise.php` converts SRT→VTT on the way in and VTT→SRT on the way
  out, validating with `SrtValidator`. This is the reason `SrtToVttConverter`
  stays alive until M6 — it is not dead code yet.
- `BulkItemProcessor` (`_EN.vtt`/`_SRC.vtt` → `.srt`, line 76/81) and
  `ShortenBulkItemProcessor` (line 76); rename the `enVttPath`/`srcVttPath` queue
  keys in `BulkIntakeQueue::markDone()`/`doneEntries()`.
- Delete the now-dead on-the-fly conversions: `BulkZipBuilder:40`,
  `ShortenBulkZipBuilder:39`, `DownloadAction::downloadSrt():49`,
  `ShortenAction::downloadSrt():158`, `CatalogAction::downloadCaption():374`, and
  M4's two finalize bridges. Drop the now-unused `VttToSrtConverter` constructor
  deps.
- `batch_translate_captions.php:200` literal.
- Data migration for `data/caption-translation/*/current/draft_*.vtt` (268 files
  across 39 dirs), shipped in the **same deploy** as the `JobManager` rename.
  `translation.json` records only per-language status, no filenames, so no JSON
  edits needed.
- Optional CLI flag renames (`--vtt_output` → `--srt_output` etc. in
  `BackgroundJobLauncher` + the four `run_*.sh` wrappers). These are pure strings —
  the wrappers pass values through — so they're cosmetic and skippable.

**Pre-flight:** check `data/jobs/` and every `current/` dir for recent mtimes to
confirm no job is mid-edit in someone's browser. Re-scan `data/` for stray `.vtt`
outside the two known locations.

**Validation:** reopen an in-progress translation job — editor loads the migrated
`draft_{lang}.srt` and a save round-trips; run a full transcription end-to-end
(upload audio → Groq → `draft.srt` appears and parses), and the local-fallback
branch too if it can be forced; run one bulk job and open its ZIP.

---

### M6 — Gemini revision prompt goes native SRT

Isolated last because it is the only place where output *quality* can shift.
Evaluated automatically against a real-caption corpus — no human review needed,
because the existing benchmark already scores the two things that actually matter
(cue length discipline and text fidelity) as numbers.

- `GeminiReviser`: rewrite `SYSTEM_PROMPT_TEMPLATE` (line 33 and the output-format
  section at 65-68). The prompt must state SRT's structural requirements
  explicitly — mandatory sequential numeric index, comma millisecond separator, no
  header line — because VTT never required them and the model will otherwise emit
  VTT-flavored output regardless of the schema key. Schema key `revised_vtt` →
  `revised_srt` (line 154, and 178-186 in `parseResponse()`).
- Remove `revise.php`'s M5 double-conversion bridge; `VttParser::canonicalize()`
  usage there is replaced by SRT-side normalization.
- Rewrite `GeminiReviserTest`'s mocked payload/response fixtures.

**Automated A/B evaluation.** Extend `benchmark-revision.php` into a paired runner:
the same input revised twice on the same model (production `gemini-3.5-flash`),
once through the M5 state (VTT prompt + SRT↔VTT bridging) and once through the new
SRT-native prompt.

Corpus: the existing `revision_input` fixture plus ~10 real caption files sampled
from `data/captions/` across different languages and lengths (read-only; outputs go
to a scratch dir, never back into `data/`).

Gates I check myself, all mechanical:

1. **Structural validity — hard gate, must be 100%.** Every output parses via
   `SrtParser`, has strictly sequential `1..N` indices, comma-decimal timestamps,
   and no `WEBVTT` header. This is the failure mode the prompt rewrite actually
   risks, and it is binary.
2. **Cue length discipline.** Count of cues exceeding the repo's own display limit
   (`vpc_caption_display_max_length()` = 60 + 5 tolerance) must be ≤ the VTT-prompt
   arm on the same input. The current baseline for `gemini-3.5-flash` is 0.
3. **Text fidelity.** `textMatches` against `revision_expected` must be ≥ the
   recorded VTT-era baseline (22/27 for `gemini-3.5-flash`), tolerance of 1 cue.
4. **Cross-arm agreement.** Both arms revise identical input, so their outputs
   should closely agree: normalized full-text similarity and cue count within a
   narrow band. A wide divergence means the prompt changed behavior, not just
   format, and is the signal to iterate on prompt wording.
5. **Timing envelope.** First start and last end preserved vs input; cues monotonic
   and non-overlapping. (Cue *boundaries* may legitimately move — revision
   re-merges — so per-cue timing is deliberately not gated.)

Results land in a regenerated `revision_benchmark_RESULTS.md` with both arms side
by side, and I report the table. **Budget note:** ~22 live Gemini calls on
`gemini-3.5-flash` at roughly $0.10 each ≈ **$2–3** against your API key. Say the
word if you'd rather I run the cheaper `gemini-2.5-flash-lite` arm for iteration
and only spend the production model on the final confirmation.

If a gate fails, I iterate on the prompt and re-run — this milestone stays
independently revertible, so a regression here never blocks M0–M5.

---

### M7 — Cleanup (non-blocking)

- `SrtToVttConverter` is genuinely dead only after M6 — confirm by grep, then
  remove it and `SrtToVttConverterTest`.
- `caption-utils.js`: remove `generateVtt()` only. **Keep `formatTime()` and
  `parseTime()`** — they are the caption editors' on-screen timestamp formatter and
  input parser (`continguts-caption-editor.js:76,81,106`,
  `translation-review.js:36,41,67`), not VTT-serialization helpers. The editors'
  displayed timestamps stay dot-decimal; that is a UI format, unrelated to storage.
- `studio/scripts/test_vimeo_publish.php` (globs `*.vtt` at 34/36/54) and
  `test-translate-integration.php:15` — dev scripts, update or delete.
- Docs + a new ADR `docs/adr/00XX-srt-primary-caption-format.md` recording the
  decision, the full-pipeline scope, and the Vimeo-format-blind /
  no-native-`<track>` findings that made it safe.
- Final sweep: `grep -rn "\.vtt" studio/src studio/js studio/views preview` — every
  remaining hit must be either upload-input-facing (`VttParser`,
  `WebVttValidator`, `CaptionReader`, intake copy) or a comment.

---

## Corrections carried over from the PRD validation

These were verified against the code and are already folded into the milestones
above:

1. `CatalogAction::captionReview()` (the catalog caption editor) reads and writes
   `data/captions/*` with `VttParser` — absent from the PRD entirely. Fixed in M1
   via `CaptionReader`, before M4 moves the data.
2. `CatalogAction.php:988` and `batch_translate_captions.php:200` copy job drafts
   into `CaptionFilename::forVideo()` destinations — would have written VTT bytes
   into `.srt` files between the PRD's Phase 2 and Phase 3. Bridged in M4, removed
   in M5.
3. A third download pair exists (`continguts-download-caption-vtt|srt` →
   `CatalogAction::downloadCaption()`, driven by `caption-table.js`'s
   `['vtt','srt']` loop). Covered in M3.
4. `preview/captions-static.php`'s real blocker is the line-69 timing regex (no
   comma in the character class → **all cues silently dropped**), not the `(float)`
   cast alone. Both fixed in M1.
5. `caption-utils.js`'s `formatTime()` is live editor UI code, not dead VTT
   plumbing — M7 keeps it.
6. `VttToSrtConverter` is used in five production paths today (the PRD's
   building-blocks table called it unused).
7. `SrtParser::write()` must not duplicate `VttToSrtConverter::writeCues()` — one
   writer, delegated. Handled in M0.
8. Job-directory count is 42 (39 with `current/`), not 82.
