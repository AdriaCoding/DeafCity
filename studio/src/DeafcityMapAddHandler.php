<?php

namespace Studio;

class DeafcityMapAddHandler
{
    public function __construct(
        private readonly DeafcityMapStore $store,
        private readonly DeafcityMapLabel $label = new DeafcityMapLabel(),
        private readonly EditionFormatter $editionFormatter = new EditionFormatter(),
    ) {
    }

    /**
     * @return array{ok: bool, id?: string, label?: string, city?: string, sign_language_code?: string, coordinates?: array{0: float, 1: float}, errors?: string[]}
     */
    public function handle(string $city, string $code, string $year, string $latitude, string $longitude): array
    {
        $formatted = $this->editionFormatter->format($city, $year);
        $label = $this->label->format($city, $code);
        $coords = $this->parseCoordinates($latitude, $longitude);

        if ($formatted === null || $label === null || $coords === null) {
            return [
                'ok' => false,
                'errors' => ['Indiqueu ciutat, any, codi de llengua de signes i coordenades vàlides.'],
            ];
        }

        foreach ($this->store->all() as $existing) {
            if (($existing['id'] ?? '') === $formatted['id']) {
                return [
                    'ok' => false,
                    'errors' => ['Aquesta localització ja existeix al mapa.'],
                ];
            }
        }

        $location = [
            'id' => $formatted['id'],
            'city' => trim($city),
            'sign_language_code' => strtoupper(trim($code)),
            'label' => $label,
            'coordinates' => $coords,
        ];

        try {
            $this->store->add($location);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'errors' => ['No s\'ha pogut desar la nova localització.'],
            ];
        }

        return [
            'ok' => true,
            'id' => $location['id'],
            'label' => $location['label'],
            'city' => $location['city'],
            'sign_language_code' => $location['sign_language_code'],
            'coordinates' => $location['coordinates'],
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function parseCoordinates(string $latitude, string $longitude): ?array
    {
        if (!is_numeric(trim($latitude)) || !is_numeric(trim($longitude))) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [$lng, $lat];
    }
}
