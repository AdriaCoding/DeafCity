<?php

namespace Studio;

class EditionAddHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
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

        foreach ($this->studioConfig->getEditions() as $edition) {
            if (($edition['id'] ?? '') === $formatted['id']) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta edició ja existeix a la llista.'],
                ];
            }
        }

        try {
            $this->studioConfig->addEdition($formatted['id'], $formatted['label']);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova edició.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $formatted['id'],
            'label' => $formatted['label'],
        ];
    }
}
