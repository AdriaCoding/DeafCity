<?php

namespace Studio;

/**
 * Domain-facing gateway for configuration changes.
 *
 * Callers provide an intended mutation; Catalog reference inspection stays
 * inside this service instead of leaking persistence details into routes.
 */
class StudioConfigMutation
{
    public function __construct(
        private StudioConfig $config,
        private CatalogEditor $catalog,
        private ContentLocalizationSync $localizationSync,
    ) {}

    /*
     * Adding a content item spans two files — studio-config.json and
     * data/ui-localizations.json — with no shared transaction. The two
     * half-states are not equally bad: a config entry with no translation key
     * shows a raw slug in the Studio and on the Website, while a stray
     * translation key is invisible and gets tidied up by
     * studio/scripts/extract_ui_localizations.php. So the config write is the
     * one that gets undone when its localization key cannot be created.
     *
     * The removals below are ordered on the same principle and need no
     * rollback: config first, localization second, so a failure there leaves
     * only the harmless orphan key.
     */

    public function addEdition(string $id, string $label): void
    {
        $this->config->addEdition($id, $label);
        try {
            $this->localizationSync->syncEdition($id, $label);
        } catch (\Throwable $e) {
            $this->config->removeEdition($id, $this->catalog);
            throw $e;
        }
    }

    public function addSignLanguage(string $id, string $label): void
    {
        $this->config->addSignLanguage($id, $label);
        try {
            $this->localizationSync->syncSignLanguage($id, $label);
        } catch (\Throwable $e) {
            $this->config->removeSignLanguage($id, $this->catalog);
            throw $e;
        }
    }

    public function addTypology(string $id, string $label): void
    {
        $this->config->addTypology($id, $label);
        try {
            $this->localizationSync->syncTypology($id, $label);
        } catch (\Throwable $e) {
            $this->config->removeTypology($id, $this->catalog);
            throw $e;
        }
    }

    public function addSubtitleLanguage(string $id, string $label): void
    {
        $this->config->addSubtitleLanguage($id, $label);
    }

    public function addInputLanguage(string $id, string $label, string $baseLanguage): void
    {
        $this->config->addInputLanguage($id, $label, $baseLanguage);
    }

    public function getEditions(): array
    {
        return $this->config->getEditions();
    }

    public function getSignLanguages(): array
    {
        return $this->config->getSignLanguages();
    }

    public function getTypologies(): array
    {
        return $this->config->getTypologies();
    }

    public function getSubtitleLanguages(): array
    {
        return $this->config->getSubtitleLanguages();
    }

    public function getInputLanguages(): array
    {
        return $this->config->getInputLanguages();
    }

    public function removeEdition(string $id): void
    {
        $this->config->removeEdition($id, $this->catalog);
        $this->localizationSync->removeEdition($id);
    }

    public function removeSignLanguage(string $id): void
    {
        $this->config->removeSignLanguage($id, $this->catalog);
        $this->localizationSync->removeSignLanguage($id);
    }

    public function removeTypology(string $id): void
    {
        $this->config->removeTypology($id, $this->catalog);
        $this->localizationSync->removeTypology($id);
    }

    public function removeSubtitleLanguage(string $id): void
    {
        $this->config->removeSubtitleLanguage($id, $this->catalog);
    }

    public function removeInputLanguage(string $id): void
    {
        $this->config->removeInputLanguage($id);
    }

    public function updateEditionLabel(string $id, string $label): void
    {
        $this->config->updateEditionLabel($id, $label);
    }

    public function updateSignLanguageLabel(string $id, string $label): void
    {
        $this->config->updateSignLanguageLabel($id, $label);
    }

    public function updateTypologyLabel(string $id, string $label): void
    {
        $this->config->updateTypologyLabel($id, $label);
    }
}
