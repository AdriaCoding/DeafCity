# ADR-0016 — SubRip is the primary caption format; WebVTT is upload input only

**Date**: 2026-08-09
**Status**: Accepted

Supersedes the format assumption in [ADR-0001](0001-server-hosted-subtitles.md) and
changes the wire format described in
[ADR-0011](0011-gemini-flash-master-subtitle-revision.md).

---

## Context

WebVTT was the canonical caption format everywhere in Studio: the files under
`data/captions/`, the files mirrored to Vimeo, the downloads offered by every job
editor, and the internal working format of the whole transcribe → revise →
translate pipeline (`draft.vtt`, `draft_{lang}.vtt`, the Gemini revision prompt,
the in-browser editor). SubRip was accepted only as an upload input and converted
to WebVTT on the way in.

The product owner asked for this to be flipped: SubRip becomes what the system
stores, serves, mirrors, and works in; WebVTT remains accepted as an upload input,
converted to SubRip immediately on intake.

Two findings determined that this was safe to do at all, and both were verified
rather than assumed:

1. **Nothing external requires WebVTT.** The public player does not use a native
   `<video><track>` element. Captions are rendered by custom JS fed from
   `captions-static.php`, which parses the caption file server-side into
   `{start, end, text}` JSON. There is no browser-imposed format requirement.
2. **Vimeo's texttrack API is format-blind.** `VimeoClient::uploadAndActivateTextTrack()`
   sends `type`, `language` and `name` and then raw-`PUT`s the file's bytes. No
   format parameter is sent anywhere, confirmed by reading the vendor SDK. Vimeo
   documents SubRip support, and publishing a migrated caption was verified
   against Vimeo's own UI before the migration was accepted.

## Decision

### 1. Full-pipeline conversion, not a boundary conversion

The alternative was to keep WebVTT as the pipeline's internal working format and
convert only at the publish boundary. That is smaller and safer, but leaves the
system permanently bilingual: two formats, two parsers, and a conversion at every
boundary that later readers must remember exists.

The full pipeline was converted instead — storage, job drafts, translations, bulk
output, downloads, and the revision prompt.

### 2. Readers became format-agnostic before any writer flipped

This is the load-bearing decision. `CaptionReader` sniffs WebVTT versus SubRip from
content and returns a common cue model; `CaptionWriter` serialises back to whatever
format was read.

**Why**: the migration necessarily moves data and code in separate deploys. Any
step that flips what is on disk while some reader still hardcodes a parser
produces silent corruption — `VttParser` given a SubRip file does not throw, it
returns zero cues and treats every block as opaque. With sniffing readers a
half-migrated `data/` is a valid state, so each milestone is independently
deployable and independently revertible.

The same argument applies to writing: the caption editors read a file, replace its
cues, and write it back. Format-agnostic reading alone would have had them rewrite
every `.srt` as WebVTT for the whole stretch between the storage flip and the
pipeline flip.

### 3. Uploads stay dual-format, decided by content

Both `.vtt` and `.srt` uploads are accepted everywhere they were before, and
detection is by file content rather than extension — a SubRip file arriving as
`.txt` is still accepted, as it always was. `CaptionIntakeNormalizer` is the single
place that decides this, replacing the same detect → convert → validate idiom that
had been implemented independently at five call sites.

### 4. The Gemini revision prompt states SubRip's structure explicitly

Changing the response schema key from `revised_vtt` to `revised_srt` is not
sufficient. WebVTT never required sequential numeric indices or comma millisecond
separators, so the old prompt never mentioned them; without saying so explicitly
the model emits WebVTT-flavoured output regardless of the schema. The prompt now
states the index, separator, and no-header rules as mandatory constraints.

Model output is normalised through `SrtParser::canonicalize()` rather than parsed
strictly, mirroring what `VttParser::canonicalize()` did for the previous prompt:
generative output drops blank-line separators and restarts numbering, and all of
that is recoverable without failing the revision.

## Consequences

- **WebVTT cue settings are lost.** `align:`, `position:` and similar have no
  SubRip equivalent. Verified across all 288 production captions: none used any.
  A master carrying them keeps its timings through translation but loses the
  settings.
- **Caption files with zero cues are now rejected on upload.** A bare `WEBVTT`
  header used to be stored as-is; it now converts to an empty SubRip document,
  which `SrtValidator` rejects. Uploaded `.srt` files previously received no
  structural validation at all — that gap is closed.
- **An uploaded `.srt` is stored byte-for-byte**, rather than being round-tripped
  through WebVTT as it was before. Programme clocks survive: DaVinci/Premiere
  timelines that start at `01:00:00:00` produce Caption files whose cues sit one
  hour ahead of Vimeo playback. Preview subtracts that hour when serving cues
  (`vpc_align_caption_cues_to_playback`). To rewrite the files on disk — needed
  for Studio editors and for Vimeo text-track mirrors — run
  `studio/scripts/migrate_caption_nle_hour_offset.php` (dry-run default;
  `--apply` to write). It only touches files whose content spans less than one
  hour, and aborts if any Caption looks like a genuine hour-plus Video.
- **`SrtToVttConverter` is gone.** Nothing needs to produce WebVTT any more.
  `VttParser` and `WebVttValidator` remain, scoped to reading upload input.
- **`data/` cannot be rolled back by git** — it is gitignored. The migration
  scripts are therefore dry-run by default, verify every conversion cue-by-cue in
  memory before writing anything, and are idempotent. Recovery depends on a
  filesystem backup, which was taken and verified before each data migration.

## Notes

Three latent bugs were found and fixed along the way, all of which would have
become visible only after the flip:

- `captions-static.php` matched timestamps with `/^([\d:\.]+)\s+-->\s+([\d:\.]+)/`,
  a character class containing no comma. A SubRip timestamp matched nothing, every
  cue hit `continue`, and the endpoint returned `[]` — captions would have silently
  disappeared from the player. Separately, `(float)"01,500"` is `1.0` in PHP, so
  casting a SubRip fraction discarded the milliseconds.
- `CaptionUploadHandler` unlinked the destination file before attempting
  conversion, so a malformed upload destroyed the caption it was meant to replace.
- The timestamp formatters could emit a four-digit millisecond field for a float
  landing just under a second boundary (`4.9999` → `00:00:04,1000`), producing
  SubRip that the parser then rejects. Fixed in both the PHP and Python writers.
- After the flip, newly uploaded SubRip from DaVinci still used `01:00:00,000`
  as the first cue. The Preview player never matched those cues against a short
  Video. Serve-time alignment and `migrate_caption_nle_hour_offset.php` are the
  two mitigations; the lasting fix is setting DaVinci's timeline start timecode
  to `00:00:00:00` before export.
