<?php

namespace Studio;

class EditionFormatter
{
    /**
     * @return array{id: string, label: string}|null null when city or year is invalid
     */
    public function format(string $city, string $year): ?array
    {
        $city = trim($city);
        $year = trim($year);

        if ($city === '' || !preg_match('/^\d{4}$/', $year)) {
            return null;
        }

        $slug = $this->slugifyCity($city);
        if ($slug === '') {
            return null;
        }

        return [
            'id' => $year . '-' . $slug,
            'label' => $city . ' ' . $year,
        ];
    }

    public function slugifyCity(string $city): string
    {
        $city = trim($city);
        if ($city === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $city);
        if ($ascii === false) {
            $ascii = $city;
        }

        $lower = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($slug, '-');
    }
}
