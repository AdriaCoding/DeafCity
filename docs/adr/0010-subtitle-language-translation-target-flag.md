# ADR-0010 — Translation targets are an explicit flag on Subtitle languages

**Date**: 2026-06-07
**Status**: Superseded (2026-07-17)
**Relates to**: [ADR-0005](0005-gemini-flash-for-subtitle-translation.md)

---

## Original decision

Not every configured Subtitle language should receive automatic translation. Dialect entries such as Algerian Darija (`arq`) and Tunisian Arabic (`aeb`) remain valid for Intake, manual caption upload, and Vimeo Publication, but must not be spawned by the translation pipeline unless a Producer explicitly marks them.

Each `subtitle_languages` entry gains a boolean `translation_target`. The Llengues verbals screen exposes an **Objectiu de traducció** checkbox per row (immediate save). New languages default to `false`; existing entries migrate with `true` except denylist `{arq, aeb}`. `TranslationCoordinator` and the Translation Hub list only flagged targets (excluding the Master). When no targets remain, **Desa i tradueix** skips the translation step. The transcription intake path keeps its hardcoded post-transcription chain to English only — it does not use this flag.

Toggle changes do not affect in-flight Jobs; the flag applies on the next spawn only.

## Supersession

The selective flag was removed. Every configured Subtitle language is now a translation destination (excluding the Master and languages that already have captions). The `translation_target` field, Llengues verbals checkbox, and related Studio APIs no longer exist. Legacy keys in config JSON are ignored on read.

The active Subtitle-language configuration uses international Arabic (`ar`) only. Historical dialect references (`arq`, `aeb`) in this superseded decision are not configured application languages.
