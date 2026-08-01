<?php

namespace Studio;

/**
 * Keeps data/ui-localizations.json in sync with content items added to
 * studio-config.json (typologies, editions, sign languages). This is the
 * single source of truth for how a config item maps to its content.* key(s);
 * both the live "Afegir X" flow and studio/scripts/extract_ui_localizations.php
 * go through here so the two never drift apart.
 */
class ContentLocalizationSync
{
    public function __construct(private readonly LocalizationStore $store)
    {
    }

    public function syncTypology(string $id, string $label): void
    {
        $this->store->createKeyIfMissing(
            self::typologyKey($id),
            'content',
            self::typologyContext($id),
            $label,
        );
    }

    public function syncEdition(string $id, string $label): void
    {
        $this->store->createKeyIfMissing(
            self::editionKey($id),
            'content',
            self::editionContext($id),
            $label,
        );
    }

    public function syncSignLanguage(string $id, string $label): void
    {
        $this->store->createKeyIfMissing(
            self::signLanguageLabelKey($id),
            'content',
            self::signLanguageLabelContext($id),
            $label,
        );
    }

    public function removeTypology(string $id): void
    {
        $this->store->removeKey(self::typologyKey($id));
    }

    public function removeEdition(string $id): void
    {
        $this->store->removeKey(self::editionKey($id));
    }

    public function removeSignLanguage(string $id): void
    {
        $this->store->removeKey(self::signLanguageLabelKey($id));
        $this->store->removeKey(self::signLanguageShortLabelKey($id));
    }

    // Pure key/context builders — the single source of truth for how a
    // content item maps to its ui-localizations.json key(s), shared between
    // the live add-flow above and studio/scripts/extract_ui_localizations.php,
    // which needs the same shape but also refreshes already-existing keys.

    public static function typologyKey(string $id): string
    {
        return "content.typology.$id";
    }

    public static function typologyContext(string $id): string
    {
        return "Typology ($id)";
    }

    public static function editionKey(string $id): string
    {
        return "content.edition.$id";
    }

    public static function editionContext(string $id): string
    {
        return "Edition city ($id)";
    }

    public static function signLanguageLabelKey(string $id): string
    {
        return "content.sign_language.$id.label";
    }

    public static function signLanguageLabelContext(string $id): string
    {
        return "Sign language label ($id)";
    }

    public static function signLanguageShortLabelKey(string $id): string
    {
        return "content.sign_language.$id.short_label";
    }

    public static function signLanguageShortLabelContext(string $id): string
    {
        return "Sign language short label ($id)";
    }
}
