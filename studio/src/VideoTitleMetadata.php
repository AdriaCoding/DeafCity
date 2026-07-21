<?php

namespace Studio;

class VideoTitleMetadata
{
    /** @var array<string, array{edition: string, sign_language: string}> */
    private const CITY_MAP = [
        'VALENCIA' => ['edition' => '2020-valencia', 'sign_language' => 'lse'],
        'MEXICO' => ['edition' => '2021-mexico', 'sign_language' => 'lsm'],
        'BILBAO' => ['edition' => '2023-bilbao', 'sign_language' => 'lse'],
        'SAOPAULO' => ['edition' => '2023-sao-paulo', 'sign_language' => 'libras'],
        'ROMA' => ['edition' => '2026-rome', 'sign_language' => 'lis'],
        'MARSEILLE' => ['edition' => '2026-marseille', 'sign_language' => 'lsf'],
        'BARCELONA' => ['edition' => '2026-barcelona', 'sign_language' => 'lsc'],
        'ALGER' => ['edition' => '2026-algiers', 'sign_language' => 'algerian-sl'],
        'TUNIS' => ['edition' => '2026-tunis', 'sign_language' => 'tunisian-sl'],
    ];

    public static function extractParticipant(string $title): ?string
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        if (preg_match('/\bby\s+(.+)$/i', $title, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/\s+#\S+.*$/', '', $name);

            return self::applyCasing(trim((string) $name));
        }

        $clean = preg_replace('/\s+#.*$/', '', $title);
        $clean = preg_replace('/_HD$/i', '', $clean);
        $parts = array_map(static fn(string $part): string => trim($part), explode('_', $clean));
        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));

        if ($parts === []) {
            return null;
        }

        if (count($parts) >= 3 && preg_match('/^\d{4}$/', $parts[0])) {
            return self::applyCasing($parts[2]);
        }

        if (count($parts) >= 2 && self::normalizeCityKey($parts[0]) === 'SAOPAULO') {
            return self::applyCasing($parts[1]);
        }

        if (count($parts) >= 3) {
            return self::applyCasing($parts[2]);
        }

        return null;
    }

    /** @return array{edition: string, sign_language: string} */
    public static function resolveEditionAndSignLanguage(string $title): array
    {
        $cityKey = self::extractCityKey($title);
        if ($cityKey === null || !isset(self::CITY_MAP[$cityKey])) {
            throw new \InvalidArgumentException("Could not resolve edition from title: $title");
        }

        return self::CITY_MAP[$cityKey];
    }

    private static function extractCityKey(string $title): ?string
    {
        $clean = preg_replace('/\s+#.*$/', '', trim($title));
        $clean = preg_replace('/_HD$/i', '', (string) $clean);
        $parts = array_map(static fn(string $part): string => trim($part), explode('_', (string) $clean));
        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));

        if ($parts === []) {
            return null;
        }

        if (preg_match('/^\d{4}$/', $parts[0])) {
            return count($parts) >= 2 ? self::normalizeCityKey($parts[1]) : null;
        }

        return self::normalizeCityKey($parts[0]);
    }

    private static function normalizeCityKey(string $city): string
    {
        return strtoupper(str_replace([' ', '-'], '', $city));
    }

    private static function applyCasing(string $name): string
    {
        if ($name === '') {
            return $name;
        }

        if (mb_strtolower($name, 'UTF-8') === $name) {
            return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($name, 1, null, 'UTF-8');
        }

        return $name;
    }
}
