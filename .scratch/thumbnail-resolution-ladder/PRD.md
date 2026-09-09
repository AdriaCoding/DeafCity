Status: ready-for-agent

# PRD — Thumbnail resolution ladder (Vimeo CDN)

## Problem Statement

Every Catalog Video stores a single Vimeo CDN thumbnail (today 960×540). The Website uses that same file for two very different canvases: the main player poster (~1800×1100 CSS pixels on a 4K screen) and the Participants grid (~213×120 CSS pixels). Grid visitors download a poster that is several times larger than the tile; large-screen visitors still get a frame that is softer than the player box, especially at devicePixelRatio 1.5–2. Producers and visitors should see a sharp frame at the size actually drawn, without the home page JSON growing by several URLs per Video.

## Solution

Keep serving images from Vimeo CDN. At sync time, record each Video’s Vimeo picture `base_link` (no size suffix). The site uses a fixed three-rung ladder — 640×360, 1920×1080, 3840×2160 — and native `srcset` so the browser picks the smallest stored width that covers the laid-out image × device pixel ratio. Always store the 4K rung, including the minority of 1080p source Videos whose 4K file is upsampled. The inline player playlist carries one `thumbnailBase` per Video plus a single site-wide ladder in config, not three full URLs per item.

## User Stories

1. As a Website visitor on a large display, I want the player loading poster to resolve near the player canvas (1920 at 1×, 3840 at 1.5×/2×) so the paused/cold-load cover is not softer than the Video.
2. As a Website visitor on the Participants page, I want each tile to download a ~640-wide frame so a page of small thumbs does not pull 960 or 4K files.
3. As a Website visitor on a retina phone, I want Participants tiles to still use 640 so 2× and 3× tiles stay sharp without jumping to 1920.
4. As a Website visitor, I want the browser to choose the file from the laid-out `<img>` size, not from `window.innerWidth`, so the player box and the grid tile can pick independently on the same screen.
5. As a Website visitor rotating or resizing the window, I want `srcset` to remain valid so a later layout does not stay stuck on a tiny file chosen at first paint.
6. As a Website visitor, I want lazy-loaded Participants images to keep using `srcset` so off-screen tiles do not fetch 4K.
7. As a Website visitor, I want autoplay playlist transitions to keep using the solid white scrim, not a 4K poster flash of the next Video.
8. As a Website visitor, I want paused/cold loads to show the target Video’s poster at the correct ladder rung, same as today’s thumbnail cover behaviour.
9. As a Website visitor, I want letterboxing (`r=pad`) stripped from grid thumbs so Participants tiles still show the full frame with `object-fit: contain`.
10. As a Website visitor, I want the player poster to keep covering the shell (`object-fit: cover`) while still selecting width from the ladder.
11. As a visitor on a 1080p-source Video, I still want a 3840 URL in the ladder so 4K screens do not need a special upscaler for fourteen Videos.
12. As a visitor whose Catalog entry has not been re-synced yet, I want the ladder derived from the existing Vimeo CDN URL so the Website improves before every Video is re-fetched from the API.
13. As a visitor, I want a broken or missing thumbnail to degrade to no poster / placeholder the same way it does today, not to a 404 spam of guessed sizes.
14. As a Producer in Studio Continguts, I want Video cards to use the small ladder rung so the catalogue grid stays light.
15. As a Producer adding a Video, I want the ladder base stored on first Vimeo resolve so new Catalog rows are not stuck on a single 960 URL.
16. As a Producer running “Sincronitzar a Vimeo”, I want each Video’s thumbnail base refreshed from Vimeo so a changed Vimeo picture is picked up.
17. As a Producer running Catalog sheet sync, I want missing ladder bases backfilled (create, or update when base is absent) without rewriting captions or Invisible.
18. As a Producer, I want Studio UI copy to stay Catalan; this feature adds no new Producer-facing controls if the ladder is automatic.
19. As a developer, I want the home-page `catalogPlaylist` payload to stay one thumbnail field per item so the payload-size guard does not absorb three CDN URLs × every Video.
20. As a developer, I want a single PHP helper to emit `src` / `srcset` / `sizes` so Participants, the player poster, and Studio cannot drift.
21. As a developer, I want the ladder widths to be a site-wide constant, not per-Video metadata, so Catalog JSON does not repeat `[640, 1920, 3840]` hundreds of times.
22. As a developer, I want JavaScript poster swaps to set `srcset` (and a matching `src` fallback), not only `src`, so client-side loads match the initial HTML poster.
23. As a developer, I want Participants’ per-visit thumb rotation (`data-thumb-urls`) to apply the ladder to whichever Video URL is chosen, not to smash `src` onto a bare 960 file.
24. As a developer, I want tests to lock: ladder derivation from a Vimeo URL, `srcset` on Participants and the home poster, playlist JSON shape, and that 4K is requested from the API on sync.
25. As a developer, I want CSP unchanged (`img-src` already allows `i.vimeocdn.com`) so this feature does not add a new origin.
26. As a developer, I want no JPEGs written under `data/` or the document root; “store” means Catalog URLs only.

## Implementation Decisions

### Locked product choices (from design)

- Origin: Vimeo CDN only. Do not download thumbnail files onto this server.
- Ladder (width×height, 16∶9): **640×360**, **1920×1080**, **3840×2160**.
- Omit Vimeo presets 100, 200, 295, 960, 1280. 960 is today’s stored size and is replaced, not kept as a rung.
- Always include 3840, including upsampled 1080p sources.
- Selection: native `srcset` + `sizes`. No PHP user-agent sniffing, no `ResizeObserver` picker, no `window.innerWidth` mapping.
- 1280×720 is out of the ladder unless a later measurement shows mid-size player posters as a real cost.

### Module: Thumbnail ladder (deep module)

One small public surface used by Website PHP, Studio PHP, and (via a mirrored constant or a config field) player JS.

Responsibilities:

- Site-wide rung list: 640, 1920, 3840 (heights 360, 1080, 2160).
- Extract a Vimeo `base_link` from `pictures.base_link` or from a legacy `thumbnail_url` (strip `_WxH` and `r=pad`).
- Build a size URL: `{base}_{width}x{height}` plus the query rules already used for display (strip `r=pad` for grids; player may keep or strip consistently with current poster behaviour).
- Emit HTML attributes: fallback `src` (1920 for player, 640 for grids), `srcset` with `w` descriptors, and a `sizes` string the caller passes in.
- If there is no usable Vimeo CDN URL, emit nothing (placeholder path unchanged).

Do not fetch HTTP. Do not call Vimeo. This module is pure URL/HTML assembly and is the primary unit test target.

`sizes` hints (locked starting point, tune only if a page test shows wrong picks):

- Participants / Studio cards: something equivalent to “about one grid cell” (e.g. `(max-width: 600px) 45vw, 213px`) matching `minmax(200px, 1fr)`.
- Player poster: the player’s laid-out width, not `100vw` if a tighter hint is already knowable from the shell; if not, a conservative large `sizes` is acceptable because a single poster fetch is cheap compared to a wrong 640 on a 1800px box.

### Catalog schema

Per Video:

- `thumbnail_base` — Vimeo picture base (no `_WxH`). Canonical for the ladder.
- `thumbnail_url` — keep as a compatibility alias: the **1920** URL (not 960). Writers set both together. Readers that have not been updated still show a usable large-enough image. After all readers use the helper, `thumbnail_url` may remain as the `src` fallback source of truth for old tools.

Do **not** store a map of three full URLs per Video in the Catalog unless derivation from `base_link` fails in production. First implementation derives rungs from `thumbnail_base`. Sync still **asks the API** for 3840 so we know the file exists; we persist `base_link` from that response, not a guessed suffix as the only write path.

Migration without blocking Website ship:

1. Helper treats “legacy `thumbnail_url` only” as: extract base, derive all three rungs. Current hashed CDN URLs already serve 640 / 1920 / 3840.
2. Next Vimeo push-sync / add-video / sheet create writes `thumbnail_base` from the API `base_link`.
3. Sheet sync treats “has `thumbnail_url` but no `thumbnail_base`” as needing a media fetch (or a local extract-and-write of base from the existing URL if we want zero extra API). Prefer extract-and-write on Catalog save when the URL is already vimeocdn, and still refresh from API on push-sync so picture changes propagate.

### Vimeo client

Replace “pick the smallest size above 640px” with “return thumbnail metadata for the ladder”:

- Read default `pictures` (includes 640 and 1920, plus `base_link`).
- Also obtain 3840×2160 via `GET /videos/{id}/pictures?sizes=3840x2160` (or a combined `sizes` query if one request can return all three rungs — prefer one extra pictures call over three).
- Return `{ base, thumbnail_url }` where `thumbnail_url` is the 1920 `link` (fallback: derived from base). Do not persist 960.
- If the 4K pictures call fails, still return base + 640/1920; display helper may still derive 3840 from base (CDN already serves it). Log/skip 4K fetch failure; do not fail the whole Video sync.
- Add-video / resolver / push-sync / sheet fetch all go through this return shape, not the old single-size picker.

Rate limit: push-sync already waits on 429. One extra pictures GET per Video is acceptable on bulk sync (~250 Videos). Do not add a third GET per size.

### Catalog writers

- Add-video and Vimeo resolve: store `thumbnail_base` + 1920 `thumbnail_url`.
- `updateThumbnailUrl` becomes an update of the pair (base + alias), or a dedicated “update thumbnail metadata” method — one Catalog lock, both fields.
- Sheet upsert: on create, write the pair; on update, write the pair when base is missing (backfill). Do not refresh the picture on every sheet sync if base is already present (same “don’t hammer Vimeo for media” rule as today), except push-sync which already refreshes thumbnails every run.
- Bulk import: same pair as add-video.

### Catalog → player projection

Projection copies `thumbnail_base` when present; otherwise the helper extracts base from `thumbnail_url` at projection time so JSON can still send one base.

Player config:

- Per playlist / catalogPlaylist item: `thumbnailBase` (one string). Keep `thumbnailUrl` only if a short overlap is needed for tests; prefer replacing `thumbnailUrl` with `thumbnailBase` in the allow-list in the same change so payload does not carry both.
- Once at config root: `thumbnailLadder: [640, 1920, 3840]` so JS does not duplicate magic numbers in a second source of truth. PHP helper is canonical; JS reads the array from config.

Payload-size test: still one URL-like field per item; average bytes must not jump by three CDN paths. If `thumbnailBase` is shorter than today’s `_960x540?&r=pad` URL, that is fine.

### Website player

Initial poster `<img>`: helper emits `src` + `srcset` + `sizes` from the head playlist item.

Client poster swap (paused/cold load): when applying a thumbnail cover, set `srcset` from `thumbnailBase` + config ladder, set `src` to the 1920 (or 640 if base missing) fallback, keep the existing “preload Image then swap” token behaviour so a stale load cannot paint the wrong Video. Compare readiness by video id + base, not by a single `src` string only.

`planLoadCover` stays “thumbnail vs white scrim vs none”; it does not pick a rung.

### Participants page

Server-rendered `<img>` uses the helper with the grid `sizes` hint. `data-thumb-urls` should store **bases** (or full legacy URLs the helper can parse). The existing session rotation script must assign `src` **and** `srcset` for the chosen Video, or replace the inner HTML with helper output. Do not leave rotation writing a naked `src` that bypasses the ladder.

### Studio Continguts

Card `<img>` (PHP and the JS `innerHTML` path when adding/updating cards) use the 640 `src` plus the same `srcset`. Vimeo ID preview in the add panel can keep a single preview image at 640 or 1920; not a user-facing canvas that needs 4K.

### CSP, cache, ownership

- No CSP change.
- No files under `data/` for this feature; no `chown` work.
- Do not add a cron beyond existing Studio sync.

### ADR

Optional short ADR: Catalog stores Vimeo `base_link`; Website never hosts thumbnail bytes; ladder is 640/1920/3840; selection is `srcset`. Only write if the Catalog field rename is considered a lasting decision.

## Testing Decisions

Good tests assert external behaviour: which attributes go on the tag, which fields land in Catalog/JSON, which Vimeo request is made. Do not assert private picker loops or exact HTML pretty-print.

**Must test (priority):**

1. **Thumbnail ladder helper** — from a real-shaped vimeocdn `thumbnail_url` / `base_link`, produces 640/1920/3840 `srcset`; strips `r=pad` for grid display; no output when URL empty/non-Vimeo; legacy 960 URL still yields a full ladder.
2. **Vimeo client** — given a mocked `pictures` payload with 640 and 1920 plus a mocked 3840 pictures response, returns base and 1920 alias; 4K fetch failure still returns base + 1920.
3. **Catalog writer** — add/update stores `thumbnail_base` and 1920 `thumbnail_url` together.
4. **Projection / player JSON** — items expose `thumbnailBase`, not three URLs; config includes `thumbnailLadder`; payload-size allow-list updated in the same change; ceilings still pass.
5. **Home page** — initial `.vpc-poster-cover` has `srcset` containing 1920 and 3840.
6. **Participants page** — grid img has 640 in `src`/`srcset` and no `r=pad`.
7. **Player JS** — paused cover sets `srcset` from `thumbnailBase`; autoplay still uses white scrim and does not assign a poster URL.

Prior art: `VimeoClientReadClassificationTest` (pictures size pick), `catalog_projection_test.php`, `participants_page_test.php` (`r=pad`, object-fit), `home_page_test.php` (poster `src`), `vimeo_playlist_logic.test.js` (`planLoadCover`), `config_payload_size_test.php`, `VimeoPushSyncTest` (thumbnail refresh).

**Lower priority / skip unless cheap:** Studio Continguts markup; bulk import; visual pixel tests of which file the browser actually downloaded (cannot reliably assert in PHP).

## Out of Scope

- Hosting JPEGs on deaf.city or any origin other than Vimeo CDN.
- Runtime canvas measurement / JS rung picker.
- Per-Video “skip 4K if source width < 3840”.
- Adding 1280 or restoring 960 as a stored rung.
- Changing player crop, `object-fit`, or Participants grid CSS except what `srcset`/`sizes` require.
- Animated Vimeo thumbnails or `link_with_play_button`.
- New Producer UI for choosing thumbnail size.
- Rewriting Sheet sync to refresh pictures on every run.

## Further Notes

### Why three rungs

Participants ~213 CSS px × 2–3 DPR ≈ 426–640 → 640. Player ~1800 CSS px × 1 DPR → 1920; × 1.5–2 → 2700–3600 → 3840. Extra Vimeo sizes do not hit a real canvas.

### Verified against this Catalog (2026-09-09)

- Default `GET /videos/{id}?fields=pictures` max size is 1920×1080 even for 3840×2160 source Videos.
- `GET /videos/{id}/pictures?sizes=3840x2160` returns a real 3840×2160 `link`.
- CDN `_3840x2160` on an existing hashed path is a real 3840×2160 JPEG (~145 KB for a sample).
- Catalog mix: 236 Videos at 3840×2160 source, 14 at 1920×1080; 4K pictures still returned for a sampled 1080p Video (upsampled).

### Implementation order (tracer bullets)

1. Helper + tests (legacy URL → ladder attributes).
2. Participants + home initial poster (Website visible without a full Vimeo re-sync).
3. Player JS poster swap + playlist JSON `thumbnailBase`.
4. Vimeo client + Catalog writers + push-sync / add-video / sheet backfill.
5. Studio cards + payload-size allow-list.
6. One push-sync (or extract-base Catalog rewrite) so `thumbnail_base` is populated for every Video.

### Payload

Do not put 640/1920/3840 full URLs on every `catalogPlaylist` item. That would fight the home-page size guard and gzip-only hides the problem.
