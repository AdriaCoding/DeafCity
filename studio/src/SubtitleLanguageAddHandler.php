<?php

namespace Studio;

class SubtitleLanguageAddHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly Iso639LanguageRegistry $isoRegistry,
        private readonly VimeoLocaleRegistry $vimeoRegistry,
    ) {
    }

    /**
     * A Subtitle language may be added only when its ISO language id is also an
     * accepted Vimeo text-track locale — the intersection of both registries.
     * The single id then serves as server id, Caption language, and Vimeo locale.
     *
     * @return array{ok: bool, id?: string, label?: string, errors?: string[]}
     */
    public function handle(string $id, string $label): array
    {
        $id = trim($id);
        $label = trim($label);

        if ($id === '' || $label === '') {
            return [
                'ok' => false,
                'errors' => ['Indiqueu una llengua i un nom.'],
            ];
        }

        if (!$this->isoRegistry->isValidCode($id)) {
            return [
                'ok' => false,
                'errors' => ['Codi d\'idioma no reconegut.'],
            ];
        }

        if (!$this->vimeoRegistry->isValidCode($id)) {
            return [
                'ok' => false,
                'errors' => ['Aquest idioma no és un locale acceptat per Vimeo.'],
            ];
        }

        foreach ($this->studioConfig->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $id) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta llengua verbal ja existeix a la llista.'],
                ];
            }
        }

        try {
            $this->studioConfig->addSubtitleLanguage($id, $label);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova llengua verbal.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $id,
            'label' => $label,
        ];
    }
}
