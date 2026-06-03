<?php

namespace Studio;

class SignLanguageAddHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly SignLanguageFormatter $formatter = new SignLanguageFormatter(),
    ) {
    }

    /**
     * @return array{ok: bool, id?: string, label?: string, errors?: string[]}
     */
    public function handle(string $code, string $qualifier): array
    {
        $formatted = $this->formatter->format($code, $qualifier);
        if ($formatted === null) {
            return [
                'ok' => false,
                'errors' => ['Indiqueu un codi i un país o variant.'],
            ];
        }

        foreach ($this->studioConfig->getSignLanguages() as $language) {
            if (($language['id'] ?? '') === $formatted['id']) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta llengua de signes ja existeix a la llista.'],
                ];
            }
        }

        try {
            $this->studioConfig->addSignLanguage($formatted['id'], $formatted['label']);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova llengua de signes.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $formatted['id'],
            'label' => $formatted['label'],
        ];
    }
}
