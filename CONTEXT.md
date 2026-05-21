# DEAF.city

An artistic social project that gives visibility to sign language and Deaf culture by recording participants performing humorous monologues in their sign language, then publishing them for Deaf and hearing audiences worldwide.

## Language

**Subtitle**:
The written translation of a signed performance into a spoken/written language (e.g. Spanish, English, Italian), so audiences who do not know sign language can follow the joke.
_Avoid_: Caption (when referring to the human-readable text — see Caption file)

**Caption file**:
A machine-readable timed text file (typically WebVTT) that carries Subtitle text for playback sync. Not the subtitle itself — the file format that delivers it.
_Avoid_: Subtitle file (redundant — say "caption file in language X")

**Video**:
One recorded sign-language performance — a single participant telling one joke or monologue as one continuous recording.
_Avoid_: Clip, asset, recording, monologue (as the primary noun)

**Participant**:
A Deaf person who performs a humorous monologue for DEAF.city, filmed and published as part of the artistic project.
_Avoid_: User, storyteller, actor, comedian

**Sign language**:
The natural signed language a Participant uses in a Video. Each Video belongs to exactly one Sign language, named `{CODE} {Full name}` (e.g. `LSM Mexican Sign Language`, `LSE Spanish Sign Language`).
_Avoid_: Dialect, locale, spoken language name alone, signing style

## Surfaces

**Website**:
The public-facing DEAF.city site where visitors browse Videos, read about the project, and experience installations.
_Avoid_: Portal, frontend (as a domain noun)

**Preview site**:
The in-progress modern Website shown to Antoni for validation before it replaces the live homepage. Lives at `/preview/`.
_Avoid_: Staging, prototype, develop

**Studio**:
The private, password-protected application where Producers process Videos — upload, subtitle generation and editing, translation, tagging, and publication.
_Avoid_: Webapp, admin, CMS, portal

**Producer**:
Someone who uses the Studio to process Videos on behalf of the project — typically Antoni. Developers build and maintain the Studio and Website but do not operate the subtitle-and-publication workflow.
_Avoid_: User, operator, editor, participant, developer (as a Studio role)

**Tag**:
A reusable label a Producer attaches to a Video before publication (e.g. city edition, theme, installation). Tags can be newly created or reused from previous Videos.
_Avoid_: Category, label, keyword

**Subtitle language**:
The language a Subtitle is written in (e.g. Spanish, English, Italian). Distinct from Sign language — what is signed on camera vs what is written for the audience.
_Avoid_: Idioma alone, spoken language, locale code alone

**Playlist**:
A curated, ordered list of Videos grouped for browsing on the Website (today: one Playlist per Sign language).
_Avoid_: Collection, channel, feed

**Edition**:
A city-or-region chapter of the DEAF.city project (e.g. Valencia 2020, Marseille 2026). Each Video belongs to one Edition — where and when it was filmed as part of the artistic programme.
_Avoid_: City alone, season, event

## Studio workflow

**Interpreter audio**:
An optional spoken-language recording of the signed performance, uploaded alongside a Video, used to auto-generate Subtitles. Alternative to uploading an existing subtitle file at intake.
_Avoid_: Voiceover, audio track, narration

**Publication**:
The Studio action that makes a Video live — uploading to Vimeo (when needed), saving caption files on the server, and updating the Catalog.
_Avoid_: Publish (as a noun), release, deploy

**Master subtitle**:
The first validated Subtitle in one Subtitle language for a Video. All other Subtitle languages for that Video are translated from the Master subtitle.
_Avoid_: Source subtitle, original subtitle, primary track

**Catalog**:
The authoritative metadata registry of all Videos — ids, Sign language, Edition, Tags, and caption file references. The Preview site and Studio read from the Catalog; Publication writes to it. During transition, the legacy homepage still reads Playlists separately; the Catalog becomes the sole source of truth when the Preview site replaces the homepage.
_Avoid_: Video database, registry, index
