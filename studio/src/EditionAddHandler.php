<?php

namespace Studio;

class EditionAddHandler
{
    public function __construct(
        private readonly StudioConfigMutation $configMutation,
        private readonly EditionFormatter $formatter = new EditionFormatter(),
    ) {
    }

    /**
     * @return array{ok: bool, id?: string, label?: string, errors?: string[]}
     */
    public function handle(string $city, string $year): array
    {
        $formatted = $this->formatter->format($city, $year);
        if ($formatted === null) {
            return [
                'ok' => false,
                'errors' => ['Indiqueu una ciutat i un any de quatre xifres.'],
            ];
        }

        foreach ($this->configMutation->getEditions() as $edition) {
            if (($edition['id'] ?? '') === $formatted['id']) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta ciutat ja existeix a la llista.'],
                ];
            }
        }

        try {
            $this->configMutation->addEdition($formatted['id'], $formatted['label']);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova ciutat.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $formatted['id'],
            'label' => $formatted['label'],
        ];
    }
}
