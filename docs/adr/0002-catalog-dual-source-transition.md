# Catalog as sole source of truth

**Status:** Superseded (2026-07-31) — the transition described below is complete.

## Original decision

The Catalog is the authoritative Video metadata registry for the Studio and Preview site. The legacy homepage still reads `playlists.json` directly and must not be changed until the Preview site is validated and promoted to the homepage.

During this transition two data sources coexist: Playlists (legacy homepage only) and the Catalog (Studio and Preview). The Catalog file is `data/catalog.json` (migrated from the former `videos.json` at Publication slice deploy). When the legacy homepage is retired, the Catalog alone feeds the Website — Playlists become views generated from Catalog metadata (e.g. by Sign language), not a separately maintained file.

**Considered options:** maintain both indefinitely (rejected — drift risk); migrate legacy homepage now (rejected — stakeholder validation gate); transitional dual source with Catalog as target (chosen).

## Supersession

The legacy homepage has been retired. `playlists.json` and `videos.json` no longer exist anywhere in the repo or on the server; `data/catalog.json` is the sole Video metadata source for both Studio and the Website. Filtered views (by Sign language, edition, typology, etc.) are computed at runtime from the Catalog — see `preview/js/vimeo_playlist_logic.js` and `preview/lib/catalog_projection.php` — exactly as this ADR anticipated for the post-transition state. There is no remaining dual-source condition to guard, and no code path should treat Playlists as a separately maintained file.
