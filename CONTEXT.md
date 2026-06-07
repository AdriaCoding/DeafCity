# DEAF.city

An artistic social project that gives visibility to sign language and Deaf culture by recording participants performing humorous monologues in their sign language, then publishing them for Deaf and hearing audiences worldwide.

## Language

**Subtitle**:
The written translation of a signed performance into a spoken/written language (e.g. Spanish, English, Italian), so audiences who do not know sign language can follow the joke.
_Avoid_: Caption (when referring to the human-readable text — see Caption file)

**Caption file**:
A machine-readable timed text file (typically WebVTT) that carries Subtitle text for playback sync. Not the subtitle itself — the file format that delivers it. Studio intake may accept SubRip (.srt); the canonical on-disk format in a Job is always WebVTT.
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
The language a Subtitle is written in (e.g. Spanish, English, Italian). Distinct from Sign language — what is signed on camera vs what is written for the audience. A Subtitle language may exist in Studio config without being a Translation target — it can still be chosen at Intake as the Master language and published to Vimeo; it is simply excluded from automatic translation generation.
_Avoid_: Idioma alone, spoken language, locale code alone

**Translation target**:
A Subtitle language that the Studio auto-translates when a Producer saves the Master subtitle. Only a subset of configured Subtitle languages are Translation targets; the rest remain available for Intake, manual caption upload, and Publication but are never spawned by the translation pipeline.
_Avoid_: Target language (alone — say Translation target), locale, output language

**Playlist**:
A curated, ordered list of Videos grouped for browsing on the Website (today: one Playlist per Sign language).
_Avoid_: Collection, channel, feed

**Edition**:
A city-or-region chapter of the DEAF.city project (e.g. Valencia 2020, Marseille 2026). Each Video belongs to one Edition — where and when it was filmed as part of the artistic programme.
Stakeholder wants its display name to be "ciutat", in catalan. However, we should still call it edition in the backend
_Avoid_: Season, event

## Studio workflow

**Interpreter audio**:
An optional spoken-language recording of the signed performance, uploaded alongside a Video, used to auto-generate Subtitles. Alternative to uploading an existing subtitle file at intake.
_Avoid_: Voiceover, audio track, narration

**Publication**:
The Studio action that makes a Video live — uploading the Video and all reviewed caption files to Vimeo via the API, saving the same caption files on the server, and updating the Catalog. Antoni's Vimeo Plus account supports API upload. Website playback uses server caption files; Vimeo text tracks are kept in sync at Publication.
_Avoid_: Publish (as a noun), release, deploy

**Master subtitle**:
The first validated Subtitle in one Subtitle language for a Video. All other Subtitle languages for that Video are translated from the Master subtitle. A caption file is considered validated when it has passed through the Subtitle Editor and been saved without integrity errors.
_Avoid_: Source subtitle, original subtitle, primary track

**Subtitle Editor**:
The universal caption file editor in the Studio. Used in every pipeline slice that requires a Producer to review or author cues: reviewing an uploaded caption file (Slice 2), correcting auto-generated cues (Slice 3), and reviewing translations (Slice 4). Presents a cue list alongside a Vimeo player; supports full CRUD on cues and validates caption file integrity before saving.
_Avoid_: Caption editor, VTT editor, subtitle review screen

**Caption file integrity**:
A set of structural rules a caption file must satisfy to be saved from the Subtitle Editor: valid WebVTT header, well-formed cue timestamps, and no overlapping cue time ranges. A file that fails any rule cannot be saved until the Producer resolves the errors.
_Avoid_: Format validation, VTT validation (as a noun phrase — say "integrity check")

**Job**:
A single in-progress pipeline run in the Studio — from Intake through to Publication for one Video. A Job persists on the server filesystem as a folder holding the uploaded files and draft caption files, so a Producer can resume it across sessions. Only one Job is processed at a time. A Job is destroyed once Publication completes.
_Avoid_: Draft, task, upload, session

**Catalog**:
The authoritative metadata registry of all Videos — ids, Sign language, Edition, Tags, and caption file references. The Preview site and Studio read from the Catalog; Publication writes to it. During transition, the legacy homepage still reads Playlists separately; the Catalog becomes the sole source of truth when the Preview site replaces the homepage.
_Avoid_: Video database, registry, index
