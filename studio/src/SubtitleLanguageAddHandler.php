<?php

namespace Studio;

class SubtitleLanguageAddHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly SubtitleLanguageFormatter $formatter = new SubtitleLanguageFormatter(),
    ) {
    }

    /**
     * @return array{ok: bool, id?: string, label?: string, errors?: string[]}
     */
    public function handle(string $code, string $name): array
    {
        $formatted = $this->formatter->format($code, $name);
        if ($formatted === null) {
            return [
                'ok' => false,
                'errors' => ['Indiqueu un codi i un nom.'],
            ];
        }

        foreach ($this->studioConfig->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $formatted['id']) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta llengua oral ja existeix a la llista.'],
                ];
            }
        }

        try {
            $this->studioConfig->addSubtitleLanguage($formatted['id'], $formatted['label']);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova llengua oral.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $formatted['id'],
            'label' => $formatted['label'],
        ];
    }
}
