<?php

namespace Studio;

class TypologyAddHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
    ) {
    }

    /**
     * @return array{ok: bool, id?: string, label?: string, errors?: string[]}
     */
    public function handle(string $label): array
    {
        $label = trim($label);
        $id = $this->slugify($label);
        if ($label === '' || $id === '') {
            return [
                'ok' => false,
                'errors' => ['Indiqueu un nom per a la tipologia.'],
            ];
        }

        foreach ($this->studioConfig->getTypologies() as $typology) {
            if (($typology['id'] ?? '') === $id) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta tipologia ja existeix a la llista.'],
                ];
            }
        }

        try {
            $this->studioConfig->addTypology($id, $label);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova tipologia.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $id,
            'label' => $label,
        ];
    }

    private function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii === false) {
            $ascii = $value;
        }

        $lower = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($slug, '-');
    }
}
