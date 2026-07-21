<?php

namespace Studio;

class SheetCatalogParser
{
    /** @var array<string, array{edition: string, sign_language: string}> */
    private const CITY_MAP = [
        '2020 VALENCIA' => ['edition' => '2020-valencia', 'sign_language' => 'lse'],
        '2021 MEXICO' => ['edition' => '2021-mexico', 'sign_language' => 'lsm'],
        '2023 BILBAO' => ['edition' => '2023-bilbao', 'sign_language' => 'lse'],
        '2023 SAO PAULO' => ['edition' => '2023-sao-paulo', 'sign_language' => 'libras'],
        '2026 ALGER' => ['edition' => '2026-algiers', 'sign_language' => 'algerian-sl'],
        '2026 BARCELONA' => ['edition' => '2026-barcelona', 'sign_language' => 'lsc'],
        '2026 MARSELLA' => ['edition' => '2026-marseille', 'sign_language' => 'lsf'],
        '2026 ROMA' => ['edition' => '2026-rome', 'sign_language' => 'lis'],
        '2026 TUNIS' => ['edition' => '2026-tunis', 'sign_language' => 'tunisian-sl'],
    ];

    /** @var array<string, string> */
    private const TYPOLOGY_MAP = [
        'JOKES' => 'acudits',
        'ANECDOTES' => 'anecdotes',
        'MEMORIES' => 'memories',
        'MISUNDERSTANDINGS' => 'malentesos',
        'RIDDLES' => 'endevinalles',
    ];

    /**
     * @param list<list<string|int|float|null>> $rows
     * @return array{
     *   rows: list<SheetVideoRow>,
     *   duplicateVimeoIds: list<string>,
     *   warnings: list<string>,
     *   skippedUnknownCities: list<array{vimeoId: string, city: string, identity: string}>
     * }
     */
    public function parse(array $rows): array
    {
        $warnings = [];
        $parsed = [];
        $currentCity = '';
        $currentParticipant = '';
        $started = false;

        foreach ($rows as $row) {
            $cells = array_map(static fn($c) => trim((string) $c), $row);
            while (count($cells) < 8) {
                $cells[] = '';
            }

            $seq = $cells[0];
            $city = $cells[1];
            $participant = $cells[2];
            $vimeoId = $cells[3];
            $titles = $cells[5];
            $typologyRaw = $cells[6];
            $deafHearing = $cells[7];

            if (!$started) {
                if ($this->looksLikeHeader($cells)) {
                    $started = true;
                    continue;
                }
                $started = true;
            }

            if ($this->isJunkCutoff($city, $participant, $vimeoId, $seq)) {
                break;
            }

            if ($city !== '') {
                $currentCity = $city;
            }
            if ($participant !== '') {
                $currentParticipant = $participant;
            }

            if ($vimeoId === '' || !preg_match('/^\d+$/', $vimeoId)) {
                continue;
            }

            $cityKey = $this->normalizeCityKey($currentCity);
            if ($cityKey === '' || !isset(self::CITY_MAP[$cityKey])) {
                $warnings[] = "Unknown city for Vimeo ID $vimeoId: " . ($currentCity !== '' ? $currentCity : '(empty)');
                continue;
            }

            $map = self::CITY_MAP[$cityKey];
            $tags = $this->buildTags($titles, $deafHearing);
            $typologyId = null;
            $unknownTypology = false;
            if ($typologyRaw !== '') {
                $typoKey = strtoupper($typologyRaw);
                if (isset(self::TYPOLOGY_MAP[$typoKey])) {
                    $typologyId = self::TYPOLOGY_MAP[$typoKey];
                } else {
                    $unknownTypology = true;
                    $warnings[] = "Unknown typology \"$typologyRaw\" for Vimeo ID $vimeoId";
                }
            }

            $identity = trim($currentCity . ' / ' . $currentParticipant . ' / ' . $titles);
            $parsed[] = new SheetVideoRow(
                vimeoId: $vimeoId,
                editionId: $map['edition'],
                signLanguage: $map['sign_language'],
                participant: $currentParticipant,
                tags: $tags,
                typologyId: $typologyId,
                sheetIdentity: $identity,
                unknownTypology: $unknownTypology,
                rawTypology: $typologyRaw !== '' ? $typologyRaw : null,
            );
        }

        $counts = [];
        foreach ($parsed as $row) {
            $counts[$row->vimeoId] = ($counts[$row->vimeoId] ?? 0) + 1;
        }
        $duplicateVimeoIds = array_values(array_map(
            static fn(string|int $id): string => (string) $id,
            array_keys(array_filter($counts, static fn(int $n): bool => $n > 1)),
        ));
        $duplicateSet = array_fill_keys($duplicateVimeoIds, true);

        $rowsOut = [];
        foreach ($parsed as $row) {
            if (isset($duplicateSet[$row->vimeoId])) {
                continue;
            }
            $rowsOut[] = $row;
        }

        foreach ($duplicateVimeoIds as $dupId) {
            $identities = [];
            foreach ($parsed as $row) {
                if ($row->vimeoId === $dupId) {
                    $identities[] = $row->sheetIdentity;
                }
            }
            $warnings[] = "Duplicate Vimeo ID $dupId skipped (" . implode('; ', $identities) . ')';
        }

        return [
            'rows' => $rowsOut,
            'duplicateVimeoIds' => array_values($duplicateVimeoIds),
            'warnings' => $warnings,
            'skippedUnknownCities' => [],
        ];
    }

    /** @param list<string> $cells */
    private function looksLikeHeader(array $cells): bool
    {
        $joined = strtoupper(implode(' ', $cells));
        return str_contains($joined, 'CIUTATS') || str_contains($joined, 'VIMEO');
    }

    private function isJunkCutoff(string $city, string $participant, string $vimeoId, string $seq): bool
    {
        $markers = [strtoupper($city), strtoupper($participant), strtoupper($seq), strtoupper($vimeoId)];
        foreach ($markers as $m) {
            if ($m === 'STATS' || $m === 'EXPORTS' || str_contains($m, '#REF!')) {
                return true;
            }
        }
        return false;
    }

    private function normalizeCityKey(string $city): string
    {
        $city = trim($city);
        if ($city === '') {
            return '';
        }
        $city = str_replace(['à', 'á', 'è', 'é', 'í', 'ò', 'ó', 'ú', 'ã', 'õ', 'ç'], ['a', 'a', 'e', 'e', 'i', 'o', 'o', 'u', 'a', 'o', 'c'], mb_strtolower($city, 'UTF-8'));
        $city = preg_replace('/\s+/', ' ', $city) ?? $city;
        return strtoupper($city);
    }

    /**
     * @return list<string>
     */
    private function buildTags(string $titles, string $deafHearing): array
    {
        $tags = [];
        if ($titles !== '') {
            $tag = ltrim($titles, '#');
            $tag = trim($tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        if ($deafHearing !== '') {
            $tags[] = 'DEAF&HEARING';
        }
        return $tags;
    }
}
