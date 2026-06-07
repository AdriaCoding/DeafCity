# Preview player adapts to each Video's aspect ratio at runtime via the Vimeo SDK

The Preview site player sizes its video frame to each Video's true aspect ratio, queried from the Vimeo Player SDK (`getVideoWidth()` / `getVideoHeight()`) after the Video loads. The frame opens at a 16:9 placeholder for layout stability, then snaps to the real ratio once the SDK reports dimensions. No per-Video dimensions are stored in the Catalog.

## Why this came up

Sign-language monologues are frequently filmed in portrait or non-16:9 ratios. The Preview player hardcodes a 16:9 frame and stretches the iframe to fill it, so a portrait Video is letterboxed or mis-framed. We compared how the legacy Website handles this against what the Preview site currently does.

## How the legacy Website does it

The legacy homepage carries a real per-Video aspect ratio sourced from live Vimeo metadata:

- `views/index.php` reads `$info['width']` / `$info['height']` from `VideoCache` (a Vimeo metadata lookup) and writes `style="aspect-ratio:{width}/{height}"` directly onto the iframe. Each thumbnail also gets `data-aspect="{width}/{height}"`.
- `leaflet/js/deafcity.js` (`createPlayer`) reads that `data-aspect` off the selected Video and re-applies it to the new iframe on every Video switch.
- `css/deafcity.css` (`#current-video`) locks the **container** at `aspect-ratio: 1.77/1` (≈16:9) with `overflow: hidden`. The iframe inside is `height:100%; width:auto`, absolutely centered; its own `aspect-ratio` is "overridden by actual value" (per the inline comment).

So the legacy "hardcode" is not forcing Video shape — it is a **stable 16:9 layout slot** that prevents page reflow while Videos of differing ratios load. The true ratio sizes the iframe on top, centered and **fit-to-height, crop-width** inside that fixed window.

## What the Preview site currently does

- `preview/components/vimeo_caption_player.css` — `.video-shell` is a fixed `aspect-ratio: 16/9`; the iframe is `width:100%; height:100%`, stretched to fill. No per-Video ratio anywhere.
- The Preview player reads `data/catalog.json`, and **that file has no width/height**. Keys are only `id, vimeo_id, title, sign_language, edition, tags, captions, thumbnail_url`. `videos.json` lacks dimensions too.

The legacy site obtained dimensions from a live `VideoCache` Vimeo lookup that the Preview pipeline never recorded into the Catalog. Adapting per-Video is therefore **not a CSS tweak** — the dimension data does not exist in the Preview's source of truth.

## Decision

Adopt **runtime SDK** sizing: after each Video loads, call `getVideoWidth()` / `getVideoHeight()` and set `.video-shell`'s aspect ratio dynamically. The shell keeps `16/9` as its initial placeholder so layout is stable before the SDK responds, then snaps to the true ratio (a brief one-time reflow on load).

This keeps the change self-contained in the player, needs no pipeline or Catalog data changes, and frames portrait performances correctly.

**Considered options:**

- **Runtime via the SDK** — chosen. Self-contained in the player; no data/pipeline work; correct framing for portrait Videos. Cost: a one-time reflow on load, softened by the 16:9 placeholder.
- **Bake `width`/`height` into the Catalog** — rejected for now. Requires Publication/sync to record dimensions and a Catalog schema change; would avoid the reflow but is more work and couples player framing to pipeline state. Revisit if the reflow proves objectionable.
- **Keep the legacy 16:9 crop frame** — rejected. Simple, but crops portrait performances, which is the case we most need to get right.
