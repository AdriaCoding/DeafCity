Status: ready-for-agent

# PRD — Catalog sync from Toni’s Google Sheet

## Problem Statement

Toni maintains Video metadata (Edition / city, Participant, Vimeo ID, title Tags, Typology, DEAF+HEARING) in a Google Sheet. After he deleted and re-uploaded the Vimeo library, every Catalog `vimeo_id` went dead, while the Sheet was updated with the new IDs and rich metadata. Today the Catalog can only be repaired by fragile remapping or one-off CSV dumps. Producers need an idempotent way to pull the live Sheet into the Catalog without wiping Studio-only state (captions, Invisible).

## Solution

Add a Studio Continguts action **“Sync from sheet”** (and a CLI with the same logic plus an explicit `--replace` mode) that reads Toni’s Google Sheet via the Sheets API and a service account, upserts Catalog Videos by `vimeo_id`, and never touches captions or Invisible. Everyday sync is additive/upsert-only. A one-shot CLI `--replace` drops Catalog rows whose `vimeo_id` is not present in the Sheet, then upserts — used to clear the current dead Catalog after Toni’s reupload.

## User Stories

1. As a Producer, I want a Continguts control to sync from Toni’s Google Sheet so that Catalog metadata stays aligned with the Sheet without manual JSON edits.
2. As a Producer, I want sync to be idempotent so that running it twice with an unchanged Sheet leaves the Catalog unchanged.
3. As a Producer, I want sync to upsert by Vimeo ID so that existing Catalog entries are updated in place rather than duplicated.
4. As a Producer, I want sync never to clear or rewrite caption file references so that Master/translated Subtitles already on disk stay attached.
5. As a Producer, I want sync never to change Invisible so that curated hiding survives metadata refreshes.
6. As a Producer, I want Videos that exist in the Catalog but are absent from the Sheet to remain untouched on everyday Sync so that removing a Sheet row cannot silently delete Catalog work.
7. As a developer, I want a CLI `--replace` mode that removes Catalog Videos whose Vimeo IDs are not in the Sheet, then upserts, so that we can recover from a full Vimeo reupload in one shot.
8. As a Producer, I want Replace to be unavailable (or not exposed) in the Studio UI so that everyday use cannot accidentally wipe Catalog rows.
9. As a Producer, after Sync I want a clear summary of added, updated, skipped, and warning rows so that I can see what changed and what Toni must fix.
10. As a Producer, when the same Vimeo ID appears on multiple Sheet rows, I want those IDs skipped with warnings (other rows still sync) so that copy-paste errors do not corrupt the Catalog.
11. As a Producer, when a Sheet Vimeo ID returns 404/error from Vimeo, I want that row skipped with a warning so that dead IDs are not written into the Catalog.
12. As a Producer, I want Video titles taken from Vimeo (`GET /videos/{id}`) so that display names match Toni’s uploaded filenames.
13. As a Producer, I want Edition and Participant taken from the Sheet (with city/participant forward-fill as in Toni’s layout) so that messy Vimeo title casing does not drive Catalog identity.
14. As a Producer, I want Sign language derived from the Sheet Edition via the existing city→edition→sign-language map so that each Video gets the correct Sign language without Toni editing that field.
15. As a Producer, I want sheet TÍTOLS values like `#APPLAUSE` stored as Tag `APPLAUSE` (hash stripped) so that Tags stay consistent with Catalog conventions.
16. As a Producer, when the Sheet DEAF+HEARING column is set, I want Tag `DEAF&HEARING` included so that the Preview DEAF+HEARING filter keeps working.
17. As a Producer, when sheet-owned fields are empty, I want those Catalog fields cleared (tags → `[]`, typology removed) so that deletions in the Sheet actually take effect.
18. As a Producer, when typology is unrecognized, I want the Video still upserted with typology cleared and a warning so that a typo does not block the whole Sync.
19. As a Producer, when city/Edition is unrecognized, I want that row skipped with a warning so that we never invent Sign language or Edition ids.
20. As a Producer, I want known typology strings mapped to Studio ids (JOKES→acudits, ANECDOTES→anecdotes, MEMORIES→memories, MISUNDERSTANDINGS→malentesos, RIDDLES→endevinalles) so that Preview filters match Continguts Tipologies.
21. As a Producer, I want known city strings mapped to existing Edition ids (including 2026 Tunis → `2026-tunis` / tunisian-sl) so that new editions already configured in Studio work without code thrash for each city Toni already listed.
22. As a Producer, on first insert of a Video I want thumbnail and player embed URL fetched from Vimeo when available so that Preview can render without a second backfill pass.
23. As a Producer, on update of an existing Video I want thumbnail/embed preserved unless missing, so that Sync does not needlessly re-hit Vimeo for media URLs.
24. As a Producer, I want Sync to ignore Sheet STATS/EXPORTS/#REF! junk rows so that formula footers never become Catalog entries.
25. As a developer, I want the spreadsheet id in gitignored `config.php` and the service-account JSON gitignored under `config/` so that secrets never land in the repo.
26. As a Producer, I want Studio UI copy in Catalan so that Continguts stays consistent with the rest of Studio.
27. As a developer, I want Sync runnable from CLI without `--replace` mirroring the Studio button so that debugging matches production behaviour.
28. As a Producer, if Sheets API auth or network fails, I want Sync to abort with an error and leave the Catalog unchanged so that a partial write cannot corrupt state.
29. As a developer, I want catalog writes to go through CatalogEditor (or an equivalent locked writer) so that concurrent Studio edits do not race the sync file write.
30. As a Producer, I want rows without a numeric Vimeo ID skipped (not errors) so that incomplete Sheet drafts do not block Sync.
31. As a developer implementing recovery, after credentials are in place I want to run CLI `--replace` once, then thereafter use Studio Sync.

## Implementation Decisions

### Modules

1. **GoogleSheetsClient** — Authenticates with the service-account JSON and returns raw rows for `SPREADSHEET_ID` using the hardcoded range `Full 1!A:H`. Encapsulates HTTP/token details; callers see plain row arrays.

2. **SheetCatalogParser** — Pure transform: forward-fill city and participant; parse Vimeo ID, sequence, TÍTOLS, typology, DEAF+HEARING; map edition/typology/tags; stop before STATS; return structured row DTOs plus a list of duplicate Vimeo IDs to skip. No I/O.

3. **CatalogSheetSync** — Orchestrates parse → Vimeo lookups → Catalog upsert. Modes: default (upsert only) and `replace` (delete Catalog entries whose `vimeo_id` ∉ sheet set, then upsert). Preserves `captions` and `invisible` on update. Clears sheet-owned fields when Sheet cells are empty. Returns a result object: added / updated / removed / skipped / warnings.

4. **Studio Continguts Sync action** — Catalan button calling CatalogSheetSync without replace; renders the result summary.

5. **CLI script** — Same sync; `--replace` for one-shot recovery; `--dry-run` optional if cheap.

### Sheet → Catalog field ownership

| Field | Source on sync |
|-------|----------------|
| `vimeo_id` | Sheet |
| `title` | Vimeo API |
| `edition`, `participant`, `sign_language` | Sheet city/participant (+ edition→SL map) |
| `tags` | Sheet TÍTOLS + DEAF+HEARING column (full replace of array) |
| `typology` | Sheet (mapped); unknown → unset + warn |
| `captions`, `invisible` | Never modified by sync |
| `thumbnail_url`, embed URL | Fetch on create or if missing; else keep |

### Duplicate Vimeo IDs

Skip all rows sharing a duplicated ID; warn with the conflicting Sheet identities. Do not last-write-wins.

### Vimeo failures

Skip row on non-success Vimeo fetch for title (and for create-time media URLs). Warn; continue.

### Unknown city vs typology

- Unknown city → skip row + warn.
- Unknown typology → upsert, clear typology, warn.

### Access method

Google Sheets API + service account (not published CSV). Spreadsheet shared with the service-account client email.

### Config (locked)

| Item | Where | Notes |
|------|--------|--------|
| Service-account JSON | `config/google-sheets-service-account.json` | Gitignored; owned `www-data`, mode `640` |
| Spreadsheet id | `config.php` → `SPREADSHEET_ID` | Gitignored; document empty placeholder in `config.example.php` |
| Read range | **Hardcoded** in code as `Full 1!A:H` | Not configurable — column/schema changes require a code change anyway |

Operator setup already done for production: JSON on disk, Sheet shared with the service account, `SPREADSHEET_ID` defined.

### ADR alignment

Respects producer-uploads-to-Vimeo-directly: Studio never uploads video files; it only reads Vimeo metadata for IDs Toni already uploaded.

### Typology map (locked)

- JOKES → acudits  
- ANECDOTES → anecdotes  
- MEMORIES → memories  
- MISUNDERSTANDINGS → malentesos  
- RIDDLES → endevinalles  

### Edition map (locked from current Sheet cities)

Include at least: 2020 València, 2021 Mexico, 2023 Bilbao, 2023 São Paulo, 2026 Alger, 2026 Barcelona, 2026 Marsella, 2026 Roma, 2026 Tunis → existing Studio edition ids / sign languages (Tunis → `2026-tunis` / `tunisian-sl`).

### Tag normalisation

- Strip leading `#` from TÍTOLS.  
- DEAF+HEARING column set → ensure Tag `DEAF&HEARING` (Preview filter contract).

### UI language

Studio control and messages in Catalan.

## Testing Decisions

Good tests assert external behaviour of parser and sync (inputs → Catalog/result), not Google/Vimeo HTTP internals. Mock Sheets client and Vimeo client.

**Must test:**

- SheetCatalogParser: forward-fill, tag/typology/edition maps, STATS cutoff, duplicate ID detection, empty Vimeo skip.
- CatalogSheetSync: upsert preserves captions + invisible; empty Sheet fields clear tags/typology; duplicate + Vimeo 404 skip; unknown city skip; unknown typology warn+clear; replace removes only IDs absent from Sheet; idempotent second run; abort leaves Catalog unchanged on Sheets auth failure (if practical at this layer).

**Prior art:** CatalogEditorTest, CatalogVideoAddHandlerTest, VideoTitleMetadataTest, bulk_import_vimeo patterns.

**Optional:** thin Studio action test if the project already smoke-tests Continguts POSTs similarly.

## Out of Scope

- Pushing Catalog changes back to Google Sheets.
- Cron / scheduled sync.
- Replace mode in the Studio UI.
- Migrating or re-pushing caption files to new Vimeo IDs (separate follow-up if needed).
- Auto-creating Editions or Typologies in Studio config.
- Fixing Toni’s duplicate Sheet IDs automatically.
- Published-CSV fetch path.
- Changing Preview filter behaviour beyond expecting `DEAF&HEARING` Tag as today.

## Further Notes

- Current Catalog (~66 rows) is entirely dead Vimeo IDs; first recovery after implementation: CLI `--replace`.
- Credentials + Sheet share + `SPREADSHEET_ID` are already provisioned on this host — implementing agent should not re-ask for them.
- Sheet currently has 11 duplicated Vimeo IDs that Sync will skip until Toni fixes them (listed in the grilling session).
- Extend `VideoTitleMetadata` city map for Tunis if sync reuses it; prefer a single shared edition/SL map used by parser/sync.
- PHP 8.4 for Studio code; keep `data/` ownership as `www-data` after any root writes.
