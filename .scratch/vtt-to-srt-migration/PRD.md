# Plan — Make SRT the primary caption format (VTT becomes upload-only input)

**Status:** researched + scoped, not yet implemented. Written as a handoff spec for
the implementing agent — verify file:line references still hold (this was written
2026-08-07) before trusting them, since they will drift as earlier phases land.

## Context

Studio treats WebVTT as the canonical caption format almost everywhere today: the
file stored under `data/captions/`, the file mirrored to Vimeo, the files downloaded
from the transcription/shorten job editors, and the internal working format of the
whole transcribe → AI-revise → translate pipeline (`draft.vtt` / `draft_{lang}.vtt`
job files, the Gemini revision prompt, the in-browser subtitle editor). SRT today is
only accepted as an *upload input* that gets converted to VTT on the way in.

The product owner wants this flipped: **SRT becomes the format everything
stores/serves/works in; VTT remains accepted only as an upload input**, converted to
SRT immediately on intake (the mirror image of today's conversion direction).

This was explicitly scoped as a **full-pipeline conversion**, not a boundary-only
one — see "Decision already made" below. `data/` has already been backed up by the
user (`cp -a data backups/data-backup-...`) before any of this work starts, so the
one-time data migration scripts described here are free to delete/overwrite
`data/captions/*.vtt` and `data/caption-translation/**/*.vtt` once conversion
succeeds.

### Key research findings

1. **Nothing external forces VTT.** The public site's video player
   (`preview/components/vimeo_caption_player.php`) does not use a native
   `<video><track>` element — captions are rendered by custom JS fed from a JSON
   endpoint, `preview/captions-static.php`, which parses the caption file
   **server-side** into `{start, end, text}` JSON. There is no browser-imposed format
   requirement.
2. **Vimeo's texttrack API is format-blind.** `Studio\VimeoClient::uploadAndActivateTextTrack()`
   (`studio/src/VimeoClient.php:35-53`) sends `type=captions`, `language`, `name` to
   Vimeo's `POST /videos/{id}/texttracks`, then raw-`PUT`s the file's bytes to a
   pre-signed URL via the vendor SDK's `uploadTexttrack()`
   (`studio/vendor/vimeo/vimeo-api/src/Vimeo/Vimeo.php:439-478`). No format
   parameter is sent at all, and Vimeo's own API accepts SRT directly (confirmed:
   Vimeo's texttrack endpoint documents support for SubRip `.srt`, WebVTT `.vtt`,
   SAMI, DFXP, and SCC). **`VimeoClient` needs zero code changes** — it already
   just streams whatever bytes are on disk.
3. **The "download as SRT" delivery mechanism already exists and is already wired up
   in parallel with VTT everywhere** — this refactor is not building new SRT output
   capability, it's *removing the VTT half* of an already-dual-format download
   surface and then, in later phases, making the *internal* storage match so the
   still-necessary on-the-fly conversions can be deleted. See Section E below — this
   was the single biggest revision from the first pass of this plan.

### Decision already made (do not re-litigate)

The user was asked: keep VTT as the pipeline's internal working format and convert
only at the publish boundary (smaller, safer), or convert the full pipeline
(transcription drafts, Gemini revision prompt/schema, translation runner, in-browser
editor) to SRT natively. **They chose full pipeline.** The Gemini revision prompt
rewrite (Phase 4) is accepted as the one place where output quality could plausibly
shift and needs manual benchmark comparison before merging — that risk was accepted
knowingly, not overlooked.

---

## Reusable building blocks (do not reimplement)

| Need | Existing code |
|---|---|
| VTT → SRT content conversion | `Studio\VttToSrtConverter` (`studio/src/VttToSrtConverter.php`) — **exists, unused in production paths, has no dedicated test file**. `convert(string $vttFilePath): string` and `writeCues(array $cues): string`. |
| SRT → VTT content conversion (today's primary direction; becomes upload-intake-only) | `Studio\SrtToVttConverter` (`studio/src/SrtToVttConverter.php`) — `convert(string $srtFilePath): string`, delegates to `SrtParser::parse()` + `VttParser::write()`. |
| SRT parsing | `Studio\SrtParser` (`studio/src/SrtParser.php`) — `parse()`/`parseString()` only, **no `write()` method today** (needs adding, see Phase 0). |
| VTT parsing/writing | `Studio\VttParser` (`studio/src/VttParser.php`) — has both `parse()`/`parseString()` and `write(array $parsed): string`, plus `canonicalize()`/`parseLoose()` for Gemini's loose output. |
| Format sniffing | `Studio\IntakeSourceDetector` (`studio/src/IntakeSourceDetector.php`) — `looksLikeWebVtt()`, `looksLikeSubRip()`, `detect()`, `isSubRip()`. Detection logic itself doesn't need to change, only how callers interpret `isSubRip()`'s result (see Phase 2). |
| SRT-side client-side generation | `caption-utils.js`'s `generateSrt(cues)` / `formatTimeSrt(seconds)` (`studio/js/caption-utils.js:28-40,56-63`) — **already exists and correct**, just needs to become the only path (currently `generateVtt` is also called from two sibling functions that need deleting, not swapping — see Section E). |
| SRT filename construction | `Studio\SubtitleOutputBasename::srtFilename()` / `outputFilename(..., 'srt')` (`studio/src/SubtitleOutputBasename.php:39-54`) — already used by `ShortenBulkZipBuilder`. |
| On-the-fly VTT→SRT for downloads (to be deleted once storage is SRT-native) | `Studio\Actions\DownloadAction::downloadSrt()`, `Studio\Actions\ShortenAction::downloadSrt()`, `Studio\BulkZipBuilder`, `Studio\ShortenBulkZipBuilder` — all four currently call `(new VttToSrtConverter())->convert($vttPath)` at request time. Once Phase 3 makes the on-disk draft `.srt` natively, these become dead conversions and should read the file directly. |
| One-time catalog+disk migration script template | `studio/scripts/migrate_caption_filenames.php` + `Studio\CaptionMigrationPlanner` (`studio/src/CaptionMigrationPlanner.php`) — dry-run/`--apply` CLI flag, `flock()`-protected read-modify-write of `catalog.json`, per-item try/catch with abort-on-error semantics. Copy this exact shape for the new migration script (Phase 2). |

---

## Complete current-state inventory

### A. Format-detection & conversion primitives (studio/src/)
No file needs *new* conversion logic — `VttToSrtConverter` already exists for the
new primary conversion direction. `SrtParser` needs a `write()` method added
(Phase 0) so it's symmetric with `VttParser::write()`, and a new `SrtValidator`
class needs creating (Phase 0) as the SRT-side counterpart to `WebVttValidator`
(`studio/src/WebVttValidator.php`) — today, an uploaded `.srt` file gets **no
structural validation at all** (`CaptionUploadHandler::validateCaptionFile()`,
`studio/src/CaptionUploadHandler.php:119-126`, returns immediately on
`isSubRip() === true`); after the flip, the format receiving no validation would be
the *primary* stored format, so this gap needs closing, not carrying forward.

### B. The duplicated intake idiom (5 call sites, not 6 — verified by reading all of them)
Every one of these five files independently implements: *detect format → if SRT,
`$this->srtConverter->convert()` to VTT + build a `.vtt`-suffixed label for
validation → else validate directly as VTT → save*. All five construct their own
`IntakeSourceDetector`, `SrtToVttConverter`, `WebVttValidator` as constructor
defaults (`new IntakeSourceDetector()` etc.), so there's no shared instance to
retrofit — only a shared *shape* to extract.

1. `studio/src/CaptionUploadHandler.php:66-70,79-91,119-126` — the canonical
   catalog-caption upload path (also used transitively by `CaptionReplaceHandler`
   and `CatalogIntakeAddHandler`, which are *not* duplicate sites themselves — they
   just call this class).
2. `studio/src/TranscriptionIntakeHandler.php:111-127,161-178` (method
   `handleSubtitleUpload()` + `validateVttContent()`)
3. `studio/src/ShortenIntakeHandler.php:64-80,104-121`
4. `studio/src/BulkItemProcessor.php:143-158,183-200` (method
   `processSubtitleItem()` + `validateVttContent()`)
5. `studio/src/ShortenBulkItemProcessor.php:103-118,137-154` (method
   `processItem()` + `validateVttContent()`)

Each has an identical `validateVttContent(string $vttContent, string $label): void`
private helper (writes to a tempfile, validates, unlinks) — this too should
collapse into the shared class.

Recommended extraction target: `Studio\CaptionIntakeNormalizer` (new class,
`studio/src/CaptionIntakeNormalizer.php`) with one public method, e.g.:

```php
/** @throws \InvalidArgumentException on malformed input */
public function normalize(string $tmpPath, string $originalName): string // returns SRT content
```

Internally: `if ($detector->isWebVtt($tmpPath, $originalName)) { validate as VTT via WebVttValidator; return $vttToSrtConverter->convert($tmpPath); } else { validate as SRT via new SrtValidator; return file_get_contents($tmpPath); }`
(Note the renamed detector method — see Section C.)

**Not a duplicate site** (calls #1 above, doesn't reimplement it):
`studio/src/CatalogIntakeAddHandler.php:55-61` — constructs `CaptionUploadHandler`
directly.

### C. The boundary layer
- `studio/src/CaptionFilename.php:7-10` — `forVideo()` returns
  `stem($title) . '_' . strtoupper($lang) . '.vtt'`. Change literal `.vtt` → `.srt`.
  This is the single highest-leverage change: `CaptionPublication`,
  `CaptionMigrationPlanner`, and everything that calls `CaptionFilename::forVideo()`
  inherits the new extension automatically.
- `studio/src/IntakeSourceDetector.php:44-56` — `isSubRip()` currently means "should
  intake convert SRT→VTT." Under the flip its true/false meaning inverts (or rather,
  the *caller's* interpretation inverts: "is this the non-primary format needing
  conversion" now means "is this VTT"). Recommend renaming to `isWebVtt(string
  $filePath, string $originalName): bool` with body logic unchanged except it now
  returns what `looksLikeWebVtt()` today would (i.e. swap the SRT-detection body for
  the VTT-detection body — check `looksLikeWebVtt()` first / by extension `=== 'vtt'`).
  Update `IntakeSourceDetectorTest.php:38-48` (`test_is_subrip_for_fixture_and_content_without_extension`)
  accordingly — the assertions need inverting to match the renamed method's
  semantics, not just a search-replace of the name.
- `studio/src/WebVttValidator.php` (whole file, 39 lines) — **keep as-is**, unchanged
  in role: it validates that an *uploaded* `.vtt` file is structurally valid before
  conversion. Its tests (`WebVttValidatorTest.php`) don't need behavior changes,
  only re-scoping in intent (still validates upload input, no longer validates the
  stored/canonical format).
- `studio/src/CaptionPublication.php` — **no code change**. `mirror()` (lines 48-89)
  is already format-blind: it reads whatever `$caption['file']` says and streams it
  to `VimeoClient`. Once `CaptionFilename` emits `.srt`, this "just works."
- `studio/src/VimeoClient.php` — **no code change** (confirmed format-blind, see
  Key Research Finding #2 above).
- `studio/src/CaptionReplaceHandler.php`, `studio/src/CaptionDeleteHandler.php` — no
  logic changes; both are thin wrappers around `CaptionUploadHandler`/`CatalogEditor`
  and inherit the new extension automatically. Their tests need fixture-literal
  updates only.
- `studio/src/CaptionMigrationPlanner.php` — **no code change**, its rename logic
  (`plan()`/`applyRenames()`, `studio/src/CaptionMigrationPlanner.php:15-75`) is
  already extension-agnostic (operates on whatever `CaptionFilename::forVideo()`
  returns vs. the catalog's current `file` value). Reusable as-is by the new data
  migration script if that script is built around "what would `CaptionFilename`
  name this now" rather than a raw string-replace — but the new migration also needs
  to *convert content*, which `CaptionMigrationPlanner` doesn't do, so a dedicated
  migration script is still needed (see Section G).

### D. Internal job pipeline (transcription draft → Gemini revision → translation → editor)
- `studio/src/JobManager.php` — draft-file convention lives entirely in 4 methods +
  1 literal:
  - `draftVttPath()` → rename `draftPath()`, body `.../draft.vtt` → `.../draft.srt`
    (line 123-126)
  - `writeDraftVtt()` → rename `writeDraft()` (line 128-131)
  - `draftVttPathForLang($lang)` → rename `draftPathForLang()`, `draft_{lang}.vtt` →
    `draft_{lang}.srt` (line 133-136)
  - `writeDraftVttForLang()` → rename `writeDraftForLang()` (line 138-141)
  - `hasDraftVtt()` → rename `hasDraft()` (line 85-88)
  - `create()`'s hardcoded literal `$this->currentDir . '/draft.vtt'` (line 49) —
    note this method is for the *raw upload* path (`UploadedFile` moved directly,
    no content transform) and is called from `CaptionUploadHandler`-family sites
    when the upload is already-primary-format; under the flip, callers only reach
    `create()` when the upload is already SRT (VTT uploads now route through
    `createWithContent()` after conversion instead, mirroring today's opposite
    pattern) — the literal becomes `/draft.srt`.
  - **Every call site of these 5 renamed methods must be updated** — there is no
    shim/alias approach recommended here since PHP has no compiler to catch missed
    call sites and grepping for the old names post-rename is the actual safety net;
    do the rename and the call-site sweep in the same commit. Known call sites (from
    this research pass, re-grep before relying on this list):
    `TranscriptionOrchestrator.php` (3 sites: 109, 155/159/166-168), `SubtitleEditorHandler.php`
    (4 sites: 33-34, 41, 43, 73... `handleForFilePath` takes an explicit path param,
    not a JobManager call, so only 3 of its 4 JobManager touches apply), `TranslationRunner.php`
    (line 75), `BulkItemProcessor.php` (lines 67, 120, 174, 178, 211, 214, 222, 225),
    `ShortenBulkItemProcessor.php` (lines 67, 128, 132), `TranscriptionIntakeHandler.php`
    (lines 150, 154), `ShortenIntakeHandler.php` (lines 95, 99), `Actions/DownloadAction.php`
    (lines 21, 28 — see Section E, this whole method changes shape), `Actions/ShortenAction.php`
    (line 131 — same), `TranscriptionPipelineStatus.php` (`hasDraftVtt()` call —
    format-agnostic caller, only needs the rename applied).
- `studio/src/TranscriptionOrchestrator.php:104-109` — swap
  `$this->vttParser->write(['header'=>'WEBVTT','opaque_blocks'=>[],'cues'=>$cues])`
  for `(new SrtParser())->write($cues)` (the new Phase-0 method — SRT has no header/
  opaque-block concept, so the call simplifies). Swap `VttParser $vttParser`
  constructor dependency (line 28, 48, 60) for `SrtParser`.
- `studio/scripts/transcribe.py` — the **local/offline Whisper-fallback** engine,
  spawned via `BackgroundJobLauncher::launchTranscription()` /
  `launchTranscriptionPipeline()` (`studio/src/BackgroundJobLauncher.php:30-42,56-83`)
  through shell wrappers `run_transcribe.sh` / `run_transcription_pipeline.sh`. This
  is a **separate, non-PHP VTT-writing implementation** (not covered by
  `VttParser`/`SrtParser` at all) — it needs its own SRT-writing routine added.
  Not yet read in this research pass; read it fully before implementing Phase 3 to
  find its VTT-serialization function precisely. `studio/scripts/bench_transcription.py`
  is dev/benchmark-only, lower priority but should stay consistent.
- `studio/src/SubtitleEditorHandler.php` — constructor takes `VttParser $vttParser`
  (line 8); both `handle()` (lines 32-44) and `handleForFilePath()` (lines 73-79)
  call `$this->vttParser->parse()`/`->write()`. Swap dependency to `SrtParser`. Since
  SRT cues carry no header/opaque-blocks, the round-trip simplifies from
  `$existing = parse(...); $existing['cues'] = $cues; write($existing)` to
  `$cues` round-tripping directly (SRT `write()` only needs the cue array, not a
  wrapping structure — see Phase 0's `SrtParser::write(array $cues): string`
  signature, deliberately simpler than `VttParser::write(array $parsed)`).
- `studio/src/TranslationRunner.php` — constructor takes `VttParser $vttParser`
  (line 17) and `WebVttValidator $vttValidator` (line 21, default
  `new WebVttValidator()`). `run()` (lines 31-96): `parse($masterVttPath)` (line 36),
  builds `$outParsed = ['header'=>...,'opaque_blocks'=>...,'cues'=>$translatedCues]`
  (lines 69-73), `write($outParsed)` (line 76), `$this->vttValidator->validate($outPath,$outPath)`
  (line 82). Swap parser to `SrtParser` (drop the header/opaque_blocks wrapping
  entirely — just pass `$translatedCues` to `SrtParser::write()`), swap validator to
  the new `SrtValidator`. Rename param `$masterVttPath` → `$masterSrtPath` for
  clarity (cosmetic, but this file is short enough that renaming is cheap and
  removes a live footgun for future readers).
- `studio/src/GeminiReviser.php` — see Phase 4, isolated on purpose. Full file
  already read; exact rewrite targets:
  - `SYSTEM_PROMPT_TEMPLATE` line 33: `"...strictly formatted WebVTT file."` and
    line 65-68's output-format section (`{"revised_vtt": "..."}`, `"must be a valid
    WebVTT file beginning with WEBVTT"`) — full prompt rewrite needed, not just the
    JSON key. SRT has stricter structural requirements VTT doesn't (mandatory
    sequential numeric index before every timestamp line, comma millisecond
    separator, no header line) — the current prompt never has to mention these
    because VTT doesn't require them; the new prompt must state them explicitly or
    the model will likely emit VTT-flavored output regardless of the schema key name.
  - `buildPayload()` line 154: `'revised_vtt' => ['type' => 'STRING']` → `'revised_srt'`.
  - `parseResponse()` lines 178-186: three occurrences of `revised_vtt` → `revised_srt`.
  - `revise(string $vtt, string $sourceLang)` (line 100) — param rename to `$srt`,
    cosmetic.
- `studio/src/BulkItemProcessor.php:76,81` — hardcoded output suffixes
  `$item['id'] . '_EN.vtt'` and `$item['id'] . '_SRC.vtt'` → `_EN.srt` / `_SRC.srt`.
  Also swap the `SrtToVttConverter $srtConverter` constructor dependency (line 11,
  26, 32) for `VttToSrtConverter`, and flip `processSubtitleItem()`'s branch
  (lines 144-151) the same way as the Section B normalizer.
- `studio/src/ShortenBulkItemProcessor.php:76` — hardcoded `$item['id'] . '.vtt'` →
  `.srt`. Same `SrtToVttConverter`→`VttToSrtConverter` swap (lines 23, 36, 42) and
  branch flip (lines 104-111).
- `studio/scripts/batch_translate_captions.php:200` — hardcoded
  `$jobDir . '/current/draft_' . $langStr . '.vtt'` → `.srt`; destination path
  already derives from `CaptionFilename::forVideo()` (line 201) so auto-updates once
  Section C's change lands.
- `studio/scripts/revise.php` — CLI wrapper around `GeminiReviser` +
  `WebVttValidator` + `VttParser::canonicalize()`; inherits Phase 4's changes,
  rename `--vtt_path` flag if doing the optional CLI-flag cleanup (see below).
- `studio/scripts/benchmark-revision.php` — dev-only benchmarking tool, hardcodes
  fixture paths `revision_input.vtt`/`revision_expected.vtt`/
  `revision_actual_{model}.vtt` (lines ~383-384, 424, 446 per earlier research pass —
  re-verify exact lines before editing). Needs SRT-fixture counterparts generated
  via `VttToSrtConverter` for the Phase 4 manual benchmark comparison.
- Optional/cosmetic CLI flag renames in `studio/src/BackgroundJobLauncher.php`
  (`--vtt_output`→`--srt_output` etc. across `launchTranscription()` line 33,
  `launchTranscriptionPipeline()` line 68-69, `launchTranslation()` line 95,
  `launchRevisionAndTranslation()` line 125) and the corresponding `run_*.sh`
  wrapper scripts (`studio/scripts/run_transcribe.sh`, `run_transcription_pipeline.sh`,
  `run_translate.sh`, `run_revise.sh` — not yet read, verify their arg-parsing before
  renaming flags) — mechanical, low risk, purely for readability; skip if time-boxed.

### E. Download surfaces — bigger than first assumed, mostly *removal* not *addition*
**Correction from an earlier draft of this plan**: every download surface already
offers VTT and SRT side by side, with SRT already correctly implemented via
on-the-fly conversion. The user's ask ("no more VTT downloads") means **deleting the
VTT half** of each pair, not building new SRT support.

1. Route table, `studio/index.php`:
   - line 78: `'shorten-download-vtt' => (new ShortenAction($container))->downloadVtt()` — **delete this route**.
   - line 79: `'shorten-download-srt' => ...->downloadSrt()` — keep.
   - line 83: `'download-vtt' => (new DownloadAction($container))->downloadVtt()` — **delete**.
   - line 84: `'download-srt' => ...->downloadSrt()` — keep.
2. `studio/src/Actions/DownloadAction.php`:
   - `downloadVtt()` (lines 13-31) — **delete the whole method**.
   - `downloadSrt()` (lines 33-51) — once Phase 3 makes the draft file `.srt`
     natively, delete the `(new VttToSrtConverter())->convert($vttPath)` call
     (line 49) and replace with `readfile($srtPath)` (mirroring what `downloadVtt()`
     used to do) — no more on-the-fly conversion needed. Rename the local
     `$vttPath` variable to `$srtPath` throughout for clarity (lines 21, 22, 28, 41-44).
3. `studio/src/Actions/ShortenAction.php`:
   - `downloadVtt()` (lines 124-141) — **delete**.
   - `downloadSrt()` (lines 143-160) — same simplification as above (line 158's
     `VttToSrtConverter` call → direct `readfile()`).
4. Views — remove the VTT button/link, keep the SRT one:
   - `studio/views/translation-review.php:204` (`#download-vtt-btn` button) — delete;
     line 205 (`#download-srt-btn`) stays. Also drop `#download-vtt-btn` from the
     shared CSS selectors at lines 53, 65, 66 (just the `-vtt-` half of each
     comma-joined selector).
   - `studio/views/continguts-caption-editor.php:260` — delete; line 261 stays. Same
     CSS selector cleanup at lines 118, 130, 131.
   - `studio/views/transcription-loading.php:290,293` (`?action=download-vtt` links,
     master + `&lang=en` variant) — delete both; lines 291, 294 (`download-srt`
     variants) stay.
   - `studio/views/shorten-loading.php:178` (`?action=shorten-download-vtt`) — delete;
     line 179 stays.
5. Client-side (in-memory cue → file) download buttons:
   - `studio/js/continguts-caption-editor.js` — delete `downloadVtt()` (lines
     167-171) and its wiring: the `downloadVttBtn` var (line 12), its inclusion in
     the busy-state array (line 40), the `getElementById('download-vtt-btn')` (line
     225), and its `addEventListener` (line 232). Keep `downloadSrt()` (173-177) and
     its wiring as-is — it already calls `CaptionUtils.generateSrt()` correctly.
   - `studio/js/translation-review.js` — identical deletions: `downloadVtt()`
     (129-133), `downloadVttBtn` var (line 12), `getElementById` (180), listener
     (186). Keep `downloadSrt()` (135-139).
   - `studio/js/caption-utils.js` — `generateVtt()` (lines 42-54) becomes unused
     once the above lands; leave it in place until Phase 5 cleanup confirms nothing
     else calls it (grep `generateVtt` repo-wide first — `CaptionUtils.generateVtt`
     is also referenced by name in this same inventory pass only at the two sites
     just deleted, but re-verify before removing).
6. Bulk ZIP builders — currently produce SRT-only output already (good), but via
   on-the-fly conversion that becomes unnecessary once storage is SRT-native
   (Phase 3):
   - `studio/src/BulkZipBuilder.php:33-41` — `$this->converter->convert($entry['enVttPath'])`
     → once the bulk item processor writes `_EN.srt`/`_SRC.srt` directly (Section D),
     replace with `file_get_contents($entry['enVttPath'])` (rename the array key
     from `enVttPath` to something like `enSrtPath` for clarity — touches
     `BulkItemProcessor`'s `markDone()` call at line 87 and `BulkIntakeQueue`'s
     storage of that key too, re-check `BulkIntakeQueue.php` before renaming keys
     since `doneEntries()`/`markDone()` signatures aren't in this research pass yet).
     Drop the `VttToSrtConverter $converter` constructor dependency (lines 7, 10)
     entirely once unused.
   - `studio/src/ShortenBulkZipBuilder.php` (not yet read in full — same pattern
     expected per fork research, verify before editing) — same treatment.
   - Update `BulkZipBuilderTest.php` and `ShortenBulkZipBuilderTest.php` accordingly
     (fixture content + dropped converter mock).

### F. Public preview player
- `preview/captions-static.php` (93 lines, fully read):
  - Line 13: `preg_match('/^[a-zA-Z0-9_\-\. ]+\.vtt$/', $basename)` → `.srt$`.
  - Line 19: `$captionsDir = dirname(__DIR__) . '/data/captions'` — unchanged (same
    directory, files just have new extension after Phase 2's data migration).
  - Lines 34-40 `timestampToMsStatic($ts)` — currently splits on `:` and casts the
    seconds part with `(float) $parts[2]`. **Correctness hazard**: SRT timestamps
    use a comma millisecond separator (`00:00:01,000`), and PHP's `(float)` cast
    truncates at the first non-numeric character, so `(float)"01,000"` silently
    evaluates to `1.0`, not `1.000` (which would actually be numerically identical
    here by coincidence, but `(float)"01,500"` → `1.0`, **losing the milliseconds
    entirely** — a real, silent caption-desync bug if not handled). Fix: replace `,`
    with `.` before casting, exactly like `SrtParser::parseTime()` already does
    (`studio/src/SrtParser.php:102-104`).
  - Lines 42-89 `parseVttStatic($vtt)` — rename to `parseSrtStatic()`. Line 48's
    block-skip check `substr($block, 0, 6) === 'WEBVTT'` (skip header block) must
    become "skip the leading numeric index line" instead — SRT blocks are
    `index\ntiming\ntext`, not `WEBVTT\n\ntiming\ntext`. The existing loop already
    only extracts the `-->` timing line and text lines and ignores anything before
    the timing line within a block (lines 56-63), so in practice the numeric index
    line is *already silently skipped* by the existing loop structure — the header-
    skip at line 48 is the only place needing an actual rewrite (SRT has no
    document-level header to skip, only per-cue index lines already handled).
    Re-verify this reasoning against the real file when implementing; write a
    quick manual test with a real SRT sample before trusting it.
  - Line 91-92: `$cues = parseVttStatic($vtt); echo json_encode($cues);` → rename
    call.
  - No test file exists for this endpoint today (it's a raw script, not a class).
    Add one following the `preview/tests/catalog_projection_test.php` convention
    (self-asserting script, no PHPUnit binding in `preview/`) — e.g.
    `preview/tests/captions_static_srt_parse_test.php`. Since the parsing functions
    are currently private to the script (not extracted), either extract them to a
    small includable lib file first (matching `preview/lib/caption_cues.php`'s
    existing pattern, which this very script already `require`s at line 8 — check
    whether `parseVttStatic`/`timestampToMsStatic` should actually live there
    instead of inline in the endpoint script) or test via an HTTP-level fixture
    (write a temp `.srt` into a test captions dir and curl/include the script) —
    decide based on what `caption_cues.php` already contains.
- `preview/components/vimeo_caption_player.php` — format-blind pass-through, only
  docblock examples reference `.vtt` (lines 14, 24, 40, 42) — cosmetic doc update
  only, e.g. `'file' => 'foo.en.vtt'` → `'foo.en.srt'`.
- `preview/tests/catalog_projection_test.php:25` — fixture uses `'file' => '111.es.vtt'`
  — cosmetic literal update, the projection logic itself doesn't inspect the
  extension.
- `preview/tests/vimeo_playlist_logic.test.js` — multiple fixtures use `file: 'a.vtt'`
  etc. (lines 427-513 per earlier grep) — cosmetic literal updates, this test
  exercises playlist/track-matching logic that doesn't branch on extension.

### G. Data files to migrate (already backed up)
1. **`data/captions/*.vtt`** — 288 files (measured 2026-08-07), referenced by
   `catalog.json`'s `videos[].captions[].file`. Sample entry today:
   `{"lang":"ca","label":"Catalan","file":"2020_VALENCIA_Aurora_1_CA.vtt"}`
   (`data/catalog.json`). Must convert content (`VttToSrtConverter`) **and** rewrite
   the `file` field to the `.srt` name, atomically per Phase 2's deploy.
2. **`data/caption-translation/<vimeoId>/current/draft_{lang}.vtt`** — 268 files
   across 82 job-snapshot directories (measured 2026-08-07), e.g.
   `data/caption-translation/1197992193/current/draft_en.vtt` +
   `draft_ca.vtt`/`draft_fr.vtt` + a sibling `translation.json` (which only records
   per-language `status`, e.g. `{"status":"saved","languages":{"en":{"status":"done"},...}}`
   — it does **not** hardcode filenames, so no JSON content needs editing, only the
   files themselves need converting+renaming). Must convert+rename in lockstep with
   Phase 3's `JobManager` path-method rename, since after that rename the code will
   look for `draft_{lang}.srt` and orphan any leftover `.vtt` files.
3. Re-scan for any other `.vtt` under `data/` (e.g. `data/jobs/`, `data/logs/`) right
   before running each migration script — `data/jobs` was seen empty at the time of
   this research pass but re-verify at implementation time since job state changes
   frequently.

**New one-time script(s)**, modeled directly on
`studio/scripts/migrate_caption_filenames.php`'s structure:
- `studio/scripts/migrate_captions_vtt_to_srt.php` — walks `catalog.json`, converts
  `data/captions/*.vtt` content + renames + rewrites catalog, dry-run by default,
  `--apply` to execute, `flock()`-protected catalog write, abort-all-on-any-error
  semantics (same safety posture as the existing script). Ship with Phase 2.
- Extend the same script (or add
  `studio/scripts/migrate_translation_drafts_vtt_to_srt.php`) to walk
  `data/caption-translation/*/current/draft_*.vtt` and convert+rename those too.
  Ship with Phase 3 (must land in the same deploy as `JobManager`'s rename).

### H. Test inventory — per-file required changes
Source: two independent research passes (source-code reading + dedicated test-suite
research fork) plus this document's own file reads. Test runner:
`cd studio && vendor/bin/phpunit` (config `studio/phpunit.xml`, PHPUnit 11, no
`composer test` script defined). JS tests are plain Node scripts run directly
(`node tests/foo.test.js`, self-asserting via `assert` + console output) — no
jest/vitest config exists. Python tests
(`test_cue_chunker_parity.py`, `test_transcribe_clamp.py`) are standalone scripts,
no pytest config exists.

**New tests to add (Phase 0):**
- `studio/tests/VttToSrtConverterTest.php` — mirrors
  `studio/tests/SrtToVttConverterTest.php`; must include a real-fixture round trip
  using `studio/tests/fixtures/production_sample.vtt` (convert it, assert cue
  count/text/timing survive).
- `studio/tests/SrtParserWriteTest.php` (or fold into `SrtParserTest.php`) — for the
  new `write()` method.
- `studio/tests/SrtValidatorTest.php` — for the new `SrtValidator` class.
- Strengthen `studio/tests/CaptionFormatParityTest.php` (currently only asserts
  VTT-vs-SRT→VTT-converted share cue shape, `studio/tests/CaptionFormatParityTest.php:22-49`)
  to also assert the *new* primary direction: VTT→SRT via `VttToSrtConverter`
  produces cues matching a direct `SrtParser::parse()` of a real production SRT
  sample. **This becomes the central regression net for the whole refactor** — treat
  it as the one test that must never go red across any phase.

**Literal-only fixture/content updates (mechanical, no logic change):**
`CaptionFilenameTest.php` (all assertions hardcode `.vtt` output, e.g. `..._EN.vtt`),
`CaptionMigrationPlannerTest.php` (fixture filenames), `CaptionUploadHandlerTest.php`,
`CaptionReplaceHandlerTest.php`, `CaptionDeleteHandlerTest.php`,
`CaptionPublicationTest.php`, `VimeoPushSyncTest.php`/`VimeoClientWriteTest.php`
(add/flip an assertion proving `.srt` paths flow to `uploadAndActivateTextTrack`
identically — no `VimeoClient` code change needed, just new coverage),
`CatalogEditorTest.php`, `CatalogSheetSyncTest.php`, `CatalogIntakeAddHandlerTest.php`,
`VideosCatalogVisibilityTest.php`, `DataDirZipBuilderTest.php`,
`StudioConfigMutationTest.php`, `preview/tests/catalog_projection_test.php`,
`preview/tests/vimeo_playlist_logic.test.js`.

**Logic changes required (not just literals):**
- `WebVttValidatorTest.php` — needs a design decision, not a mechanical edit: the
  class's *role* narrows to "validate uploaded .vtt input only," so tests should
  stay conceptually similar but the surrounding context (who calls it, when) changes
  — re-read against Section C/B before editing.
- `IntakeSourceDetectorTest.php:38-48` — `test_is_subrip_for_fixture_and_content_without_extension`
  asserts `isSubRip()` is `true` for the SRT fixture and `false` for a VTT sample;
  once renamed to `isWebVtt()` these assertions **invert**, not just rename.
- `JobManagerTest.php`, `TranscriptionPipelineStatusTest.php`,
  `BackgroundJobLauncherTest.php`, `SubtitleEditorHandlerTest.php`,
  `TranslationRunnerTest.php`, `BulkItemProcessorTest.php`,
  `ShortenBulkItemProcessorTest.php`, `TranscriptionIntakeHandlerTest.php`,
  `ShortenIntakeHandlerTest.php` — all assert internal job-pipeline `.vtt` paths;
  since "full pipeline" was chosen, all of these need updating in Phase 3 (would be
  skippable if boundary-only had been chosen — it wasn't).
- `SubtitleOutputBasenameTest.php` — verify current coverage of
  `transcriptionDownloadFilename(..., 'vtt')` against Section E's route deletions;
  once `download-vtt`/`shorten-download-vtt` routes are deleted, confirm whether any
  *remaining* call site still requests the `'vtt'` extension (Section E's inventory
  suggests no — re-verify at implementation time) and drop the now-dead test case if so.
- `GeminiReviserTest.php` — full rewrite of mocked payload/response fixtures for the
  `revised_srt` schema key and SRT-shaped content (Phase 4).
- `BulkZipBuilderTest.php`, `ShortenBulkZipBuilderTest.php` — once the on-the-fly
  `VttToSrtConverter` call is deleted (Section E point 6), tests must stop mocking/
  asserting that conversion step and instead assert direct content pass-through.

**No changes needed:** `SrtParserTest.php` (parse-only tests unaffected by adding
`write()`), `VttParserTest.php` (VTT parsing/writing logic itself doesn't change —
`VttParser` is kept indefinitely for reading upload input), `TranscriptionIntakeFileKindTest.php`
(already accepts both extensions), `TranslationJobStateTest.php` (incidental `.vtt`
var naming, doesn't assert format), `test_cue_chunker_parity.py` (confirmed
format-agnostic — `CueChunker.php`/`cue_chunker.py` operate purely on
`{start,end,text}` cue arrays, no I/O), `caption-table.test.js` (already mixes
`.vtt`/`.srt` upload fixtures — upload-side, unaffected),
`transcription-intake-bulk.test.js`, `transcription-intake-language.test.js`
(`isSubtitleFile()`-equivalent already accepts both — upload-side, unaffected).

### I. Docs referencing `.vtt` as canonical (Phase 5 cleanup, non-blocking)
`docs/prd-caption-track-management.md`, `docs/prd-master-subtitle-revision.md`,
`studio/README.md`, `docs/requirements.md`, `docs/studio-pipeline-features.md`,
`docs/adr/0006-groq-primary-faster-whisper-fallback-transcription.md`,
`docs/adr/0011-gemini-flash-master-subtitle-revision.md`,
`docs/adr/0013-preview-language-switch-resume-intent.md`. Consider a short new ADR
(`docs/adr/00XX-srt-primary-caption-format.md`) recording this decision, its
scope (full pipeline), and the Vimeo-format-blind / no-native-`<track>` findings
that made it safe — matching this repo's existing ADR convention (see
`.scratch/translation/engine-swap-plan.md:93-94` for the precedent of adding an ADR
alongside an engine-swap-shaped refactor).

---

## Phased implementation plan

Each phase should land as one deploy, be fully covered by the phase's own test
updates, and leave the app in a working state (no orphaned files, no dangling
routes) before the next phase begins.

### Phase 0 — Safety net (zero behavior change)
Add: `SrtParser::write()`, `SrtValidator` (+test), `VttToSrtConverterTest.php`,
strengthened `CaptionFormatParityTest.php` bidirectional assertions. Run full suite
green. No production code path changes yet.

### Phase 1 — Consolidate the 5-site intake idiom (behavior-preserving)
Extract `CaptionIntakeNormalizer` (Section B), still targeting `.vtt` output at this
point. Rewire all 5 call sites. Existing tests must stay green with zero behavior
change — this is a pure refactor commit, verified by the unmodified test suite.

### Phase 2 — Boundary cutover: published captions, downloads, public player, data
Ship atomically:
- `CaptionFilename` → `.srt` (Section C)
- `CaptionIntakeNormalizer` flips target to SRT; `IntakeSourceDetector::isSubRip()`→`isWebVtt()`
  rename+invert (Section C)
- `preview/captions-static.php` SRT parsing rewrite, including the comma-decimal
  correctness fix (Section F)
- Delete the VTT halves of every download route/button (Section E, items 1-5 — NOT
  item 6 yet, since bulk output is still internally `.vtt` until Phase 3)
- Run `studio/scripts/migrate_captions_vtt_to_srt.php --apply` against `data/captions/`
  + `catalog.json` (Section G item 1) — after code deploys and tests pass, dry-run
  first, review, then apply
- Update all "literal-only" tests from Section H that touch this boundary

This phase alone already satisfies the user's literal top-level request: uploads
still accept both formats, storage/downloads/Vimeo sync are SRT-only. Phases 3-4
extend the same change into the internal AI pipeline per the "full pipeline" scope
decision already made.

### Phase 3 — Internal job pipeline cutover
Ship atomically:
- `JobManager` method renames + extension flip (Section D)
- `TranscriptionOrchestrator`, `SubtitleEditorHandler`, `TranslationRunner` swap
  `VttParser`→`SrtParser` (Section D)
- `transcribe.py` local-fallback engine gets SRT-writing support (Section D — read
  this file fully before starting this phase, it wasn't read in this research pass)
- `BulkItemProcessor`/`ShortenBulkItemProcessor` literal suffix flips +
  `SrtToVttConverter`→`VttToSrtConverter` swap (Section D)
- Delete Section E item 6's on-the-fly conversions in `BulkZipBuilder`/
  `ShortenBulkZipBuilder`/`DownloadAction::downloadSrt()`/`ShortenAction::downloadSrt()`
  now that the source files are natively SRT
- `batch_translate_captions.php` literal flip (Section D)
- Run the translation-drafts migration script against
  `data/caption-translation/*/current/draft_*.vtt` (Section G item 2) — must land in
  the same deploy as the `JobManager` rename
- Update all Section H "logic changes required" tests for the internal pipeline

### Phase 4 — Gemini revision prompt (isolated, highest AI-quality risk)
`GeminiReviser` prompt/schema rewrite (Section D). Manual verification required and
not automatable: regenerate SRT versions of `revision_input.vtt`/`revision_expected.vtt`
via `VttToSrtConverter`, re-run `studio/scripts/benchmark-revision.php`, and manually
compare output quality against the existing `revision_actual_*.vtt` baselines before
merging. Isolated deliberately so it can be reverted independently of every other
phase if quality regresses.

### Phase 5 — Cosmetic cleanup (non-blocking, do last)
- Evaluate whether `SrtToVttConverter` is now dead code (nothing should need to
  *produce* VTT anymore post-Phase-3, only *consume* it at upload intake — but intake
  still needs it! Re-check: `CaptionIntakeNormalizer` still calls
  `VttToSrtConverter` for VTT uploads, so this class is very much still alive — it's
  `SrtToVttConverter` that may be newly dead, since nothing should need SRT→VTT
  conversion once nothing downstream wants VTT output. Confirm via repo-wide grep
  before removing).
- Remove `caption-utils.js`'s `generateVtt()`/`formatTime()` (VTT's dot-decimal
  formatter) if confirmed unused (Section E item 5).
- Optional CLI flag renames (Section D, low priority).
- Docs updates (Section I).

---

## Risk register

| Risk | Where | Mitigation |
|---|---|---|
| Silent millisecond loss parsing SRT timestamps in PHP | `preview/captions-static.php`'s new `parseSrtStatic()` | Explicit `,`→`.` replace before `(float)` cast; add a unit/script test with a `,500`-style fixture that would fail silently otherwise |
| Gemini output quality regression | `GeminiReviser` prompt rewrite (Phase 4) | Isolated last phase; mandatory manual benchmark comparison before merge; easy to revert independently |
| Orphaned data files if code/data deploy order is wrong | Phase 2 & 3 data migrations | Each migration script ships in the *same deploy* as its corresponding code change, never before or after; dry-run first |
| Missed `JobManager` call site after method rename | Phase 3 | No shim/alias — rename + grep-sweep in the same commit; full test suite is the safety net since PHP has no compiler |
| `transcribe.py` (Python, separate implementation) silently left writing VTT while PHP side expects SRT | Phase 3 | Explicitly called out as needing its own read-through + SRT routine; do not assume PHP-side changes cover it |
| `data/caption-translation` draft files are in-flight job state, not just archival — a mid-migration job could be actively open in someone's browser | Phase 3 data migration | Recommend running this migration during a maintenance window or confirming no `job.json`/`current/` directories are mid-edit at migration time (check `data/jobs/` and any `current/` dirs for recent mtimes first) |

## Verification checklist (end-to-end, run after each relevant phase)

1. `cd studio && vendor/bin/phpunit` green after every phase.
2. Relevant JS/Python scripts re-run: `node tests/caption-table.test.js`,
   `node tests/transcription-intake-bulk.test.js`,
   `node tests/transcription-intake-language.test.js`,
   `python3 tests/test_cue_chunker_parity.py` (cheap, unaffected, run anyway).
3. After Phase 2's data migration: spot-check `preview/captions-static.php?f=<name>.srt`
   for a handful of real videos, diff cue start/end/text against the pre-migration
   VTT-derived JSON for the same videos. Load a real video page in a browser and
   confirm captions render in sync.
4. After Phase 2: exercise the Studio caption editor's remaining "Download SRT"
   button, open the file in a text editor, confirm numeric index + comma timestamps.
5. After Phase 2: manually publish captions for one test video, check Vimeo's own UI
   to confirm captions still display (proves Vimeo accepts the `.srt` bytes).
6. After Phase 3's data migration: reopen an in-progress translation job in the
   Studio UI, confirm the editor loads the migrated `draft_{lang}.srt` correctly and
   that a save round-trips cleanly.
7. After Phase 3: run a full transcription job end-to-end (upload audio → Groq
   transcribe → confirm `draft.srt` appears and parses) and confirm the local-fallback
   path too if feasible (may require forcing the Groq-unavailable branch).
8. After Phase 4: run `benchmark-revision.php`, manually eyeball revised output
   against the VTT-era baseline for quality regressions.
9. After all phases: `grep -rn "\.vtt" studio/src studio/js studio/views preview
   --include='*.php' --include='*.js'` and confirm every remaining hit is either (a)
   inside `VttParser`/`VttToSrtConverter`/`WebVttValidator`/`SrtToVttConverter`
   (intentionally still upload-input-facing) or (b) a comment/docblock, not live
   logic.
