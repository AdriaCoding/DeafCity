# Catalog as sole source of truth (transitional)

The Catalog is the authoritative Video metadata registry for the Studio and Preview site. The legacy homepage still reads `playlists.json` directly and must not be changed until the Preview site is validated and promoted to the homepage.

During this transition two data sources coexist: Playlists (legacy homepage only) and the Catalog (Studio and Preview). The Catalog file is `data/catalog.json` (migrated from the former `videos.json` at Publication slice deploy). When the legacy homepage is retired, the Catalog alone feeds the Website — Playlists become views generated from Catalog metadata (e.g. by Sign language), not a separately maintained file.

**Considered options:** maintain both indefinitely (rejected — drift risk); migrate legacy homepage now (rejected — stakeholder validation gate); transitional dual source with Catalog as target (chosen).
