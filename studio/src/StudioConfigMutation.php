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
    ) {}

    public function addEdition(string $id, string $label): void
    {
        $this->config->addEdition($id, $label);
    }

    public function addSignLanguage(string $id, string $label): void
    {
        $this->config->addSignLanguage($id, $label);
    }

    public function addTypology(string $id, string $label): void
    {
        $this->config->addTypology($id, $label);
    }

    public function addSubtitleLanguage(string $id, string $label): void
    {
        $this->config->addSubtitleLanguage($id, $label);
    }

    public function removeEdition(string $id): void
    {
        $this->config->removeEdition($id, $this->catalog);
    }

    public function removeSignLanguage(string $id): void
    {
        $this->config->removeSignLanguage($id, $this->catalog);
    }

    public function removeTypology(string $id): void
    {
        $this->config->removeTypology($id, $this->catalog);
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
