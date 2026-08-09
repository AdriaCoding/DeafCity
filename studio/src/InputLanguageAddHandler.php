<?php

namespace Studio;

class InputLanguageAddHandler
{
    public function __construct(
        private readonly StudioConfigMutation $configMutation,
    ) {
    }

    /**
     * An Input language is a dialect variant (e.g. 'es-mx') used only for
     * transcription and revision. It is free text — never published to
     * Vimeo or shown publicly — but must declare an existing Subtitle
     * language as its base.
     *
     * @return array{ok: bool, id?: string, label?: string, baseLanguage?: string, errors?: string[]}
     */
    public function handle(string $id, string $label, string $baseLanguage): array
    {
        $id = strtolower(trim($id));
        $label = trim($label);
        $baseLanguage = trim($baseLanguage);

        if ($id === '' || $label === '' || $baseLanguage === '') {
            return ['ok' => false, 'errors' => ['Indiqueu un codi, un nom i una llengua base.']];
        }

        $baseLanguageExists = false;
        foreach ($this->configMutation->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $baseLanguage) {
                $baseLanguageExists = true;
                break;
            }
        }
        if (!$baseLanguageExists) {
            return ['ok' => false, 'errors' => ['La llengua base no existeix a la llista de llengües verbals.']];
        }

        foreach ($this->configMutation->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $id) {
                return ['ok' => false, 'errors' => ['Aquest codi ja existeix a la llista de llengües verbals.']];
            }
        }
        foreach ($this->configMutation->getInputLanguages() as $language) {
            if (($language['id'] ?? '') === $id) {
                return ['ok' => false, 'errors' => ['Aquest dialecte d\'entrada ja existeix a la llista.']];
            }
        }

        try {
            $this->configMutation->addInputLanguage($id, $label, $baseLanguage);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return ['ok' => false, 'errors' => ['No s\'ha pogut desar el nou dialecte d\'entrada.']];
        }

        return ['ok' => true, 'id' => $id, 'label' => $label, 'baseLanguage' => $baseLanguage];
    }
}
