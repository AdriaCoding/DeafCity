# DEAF.city — Requirements & User Stories

Requirements captured from stakeholder conversations and domain documentation sessions. Domain vocabulary is defined in [`CONTEXT.md`](../CONTEXT.md); architectural decisions are recorded in [`docs/adr/`](adr/).

## Project overview

DEAF.city is an artistic social project that gives visibility to sign language and Deaf culture by recording **Participants** performing humorous monologues in their **Sign language**, then publishing them for Deaf and hearing audiences worldwide.

Humor is the bridge between Deaf and hearing communities. The project disseminates content through an open-access video repository and physical multi-screen exhibitions (museums, art centres, public spaces).

Since launching in Valencia (2020), the project has expanded to Mexico City (2021), Bilbao and São Paulo (2023), and will grow to Marseille, Rome, Athens, Istanbul, Tunis, Algiers, and Barcelona (2026) — each **Edition** introducing new Sign languages.

## Roles & responsibilities

| Role | Responsibility |
| --- | --- |
| **Antoni Abad** (stakeholder / artist) | Produces Videos — filming Participants, delivering footage |
| **Producer** | Uses the **Studio** to upload Videos, create and edit **Subtitles**, translate, tag, and **Publish** |
| **Developers** | Modernize and maintain the **Website**, build and maintain the **Studio** — do **not** operate the video production workflow once the Studio is live |
| **Visitors** | Browse the public **Website**; rely on **Subtitles** when they do not know the Sign language |

## Mission-critical constraint

**Subtitles are essential.** Most hearing visitors do not know any sign language and cannot understand the jokes without written translation. Subtitle generation, editing, and multi-language translation are the Studio's core purpose.

## Surfaces

### Website (public)

- The current public homepage is legacy PHP built by a previous developer.
- A modern replacement is being built on the **Preview site** (`/preview/`, replacing `/develop/`).
- The Preview site uses the develop player model: Vimeo for video delivery, **Subtitles** served from caption files on our server (see [ADR-0001](adr/0001-server-hosted-subtitles.md)).
- **Do not modify the live homepage** until Antoni validates the Preview site and approves the swap.

### Studio (private)

- Password-protected web application on a subroute of deaf.city.
- Used by **Producers** (Antoni and production team) to process Videos end-to-end.
- Spec origin: collaborative document with Antoni (Catalan).

### Preview site (transitional)

- Staging ground for the modern public Website.
- Reads from the **Catalog**; not the legacy `playlists.json` data path.
- Becomes the live homepage after stakeholder validation.

## Studio workflow

The Studio follows this pipeline:

1. **Intake** — Producer uploads a Video, plus either **Interpreter audio** or an existing subtitle file. Selects Subtitle language, Sign language, and Tags.
2. **Subtitle generation** — If Interpreter audio is provided, auto-generate Subtitles using Blind Wiki tools or Gemini with format validation. Skip if a subtitle file was uploaded.
3. **Subtitle editing** — Interface to edit Subtitle text and timestamps, with video playback and real-time Subtitle preview.
4. **Translation** — From the validated **Master subtitle**, generate Subtitles in other Subtitle languages. Each translation is reviewable and editable via the same editor (step 3).
5. **Tagging** — Before Publication, Producer selects Tags (new or reused).
6. **Publication** — Upload Video to Vimeo via API (TBD), save caption files on the server, update the **Catalog**.

## User stories

### Producer — subtitle pipeline

- As a **Producer**, I want to upload a Video with Interpreter audio so that Subtitles are generated automatically and I don't have to transcribe from scratch.
- As a **Producer**, I want to upload a Video with an existing subtitle file so that I can skip generation and go straight to review.
- As a **Producer**, I want to edit Subtitle text and timing while watching the Video with live preview so that I can verify the Subtitles match the performance.
- As a **Producer**, I want to generate Subtitles in additional Subtitle languages from the Master subtitle so that the joke reaches a global audience.
- As a **Producer**, I want to review and edit translated Subtitles in the same editor so that translations are accurate and well-timed.
- As a **Producer**, I want to attach Tags (new or existing) before Publication so that Videos are discoverable and organized.
- As a **Producer**, I want to Publish a Video in one action so that it appears on the Preview site with all Subtitle languages available.

### Producer — metadata

- As a **Producer**, I want to specify the Sign language and Edition for each Video so that the Catalog and Website reflect where and how it was filmed.
- As a **Producer**, I want to reuse Tags from previous Videos so that I don't recreate labels for every Publication.

### Visitor — Website

- As a **Visitor** who does not know sign language, I want to read Subtitles in a written language I understand so that I can follow the joke.
- As a **Visitor**, I want to browse Videos grouped by Sign language so that I can explore content from different Deaf communities.
- As a **Visitor**, I want to learn about the DEAF.city project and its mission so that I understand the artistic and social context.

### Stakeholder — Website modernization

- As **Antoni**, I want to review the modern homepage on the Preview site before it goes live so that I can validate the artistic presentation.
- As **Antoni**, I want the live homepage to remain unchanged until I approve the replacement so that the public site stays stable during development.

### Developer — platform (build & maintain, not operate)

- As a **Developer**, I want the Catalog to be the single source of truth for Video metadata so that the Studio and Preview site stay in sync.
- As a **Developer**, I want Subtitles stored as caption files on the server so that Producers can iterate without depending on Vimeo text-track management.
- As a **Developer**, I want the legacy homepage and its `playlists.json` data path left untouched so that we don't break the live site during transition.

## Data & catalog

- The **Catalog** is the authoritative Video metadata registry (currently `data/videos.json`, to be renamed `data/catalog.json`).
- During transition, the legacy homepage continues reading `data/playlists.json` independently ([ADR-0002](adr/0002-catalog-dual-source-transition.md)).
- When the Preview site replaces the homepage, the Catalog becomes the sole source — Playlists become views generated from Catalog metadata (e.g. by Sign language).

## Subtitle delivery

- Vimeo hosts Video files (CDN, encoding).
- **Subtitles** are served as caption files (WebVTT) from our server, synced by the player — not pulled from Vimeo native text tracks ([ADR-0001](adr/0001-server-hosted-subtitles.md)).
- The legacy homepage still uses Vimeo `?texttrack=` today; the Preview site player replaces this approach.

## Out of scope (for this codebase)

- Physical **Installations** (multi-screen museum setups) — external to this repo.
- Video filming and Participant recruitment — Antoni's production responsibility, upstream of the Studio.

## Open questions

- Vimeo upload via API at Publication — confirmed as direction, implementation TBD.
- Blind Wiki tooling integration — referenced in Studio spec, details TBD.
- Exact Studio subroute path on deaf.city — TBD.
