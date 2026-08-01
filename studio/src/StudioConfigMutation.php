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

    public function addEdition(string $id, string $label): void
    {
        $this->config->addEdition($id, $label);
        $this->localizationSync->syncEdition($id, $label);
    }

    public function addSignLanguage(string $id, string $label): void
    {
        $this->config->addSignLanguage($id, $label);
        $this->localizationSync->syncSignLanguage($id, $label);
    }

    public function addTypology(string $id, string $label): void
    {
        $this->config->addTypology($id, $label);
        $this->localizationSync->syncTypology($id, $label);
    }

    public function addSubtitleLanguage(string $id, string $label): void
    {
        $this->config->addSubtitleLanguage($id, $label);
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
