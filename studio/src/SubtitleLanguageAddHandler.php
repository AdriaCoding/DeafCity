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
     * @return array{ok: bool, id?: string, label?: string, vimeo_code?: string, errors?: string[]}
     */
    public function handle(string $id, string $label, string $vimeoCode): array
    {
        $id = trim($id);
        $label = trim($label);
        $vimeoCode = trim($vimeoCode);

        if ($id === '' || $label === '' || $vimeoCode === '') {
            return [
                'ok' => false,
                'errors' => ['Indiqueu una llengua, un nom i un codi Vimeo.'],
            ];
        }

        if (!$this->isoRegistry->isValidCode($id)) {
            return [
                'ok' => false,
                'errors' => ['Codi d\'idioma no reconegut.'],
            ];
        }

        if (!$this->vimeoRegistry->isValidCode($vimeoCode)) {
            return [
                'ok' => false,
                'errors' => ['Codi de locale Vimeo no reconegut.'],
            ];
        }

        foreach ($this->studioConfig->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $id) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta llengua oral ja existeix a la llista.'],
                ];
            }
        }

        if (in_array($vimeoCode, $this->studioConfig->getUsedVimeoCodes(), true)) {
            return [
                'ok' => false,
                'errors' => ['Aquest codi de locale Vimeo ja està assignat a una altra llengua oral.'],
            ];
        }

        try {
            $this->studioConfig->addSubtitleLanguage($id, $label, $vimeoCode);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova llengua oral.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $id,
            'label' => $label,
            'vimeo_code' => $vimeoCode,
        ];
    }
}
