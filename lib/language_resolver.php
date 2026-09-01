<?php

/**
 * Resolve preview UI language from Accept-Language, completeness gate, and ?lang= override.
 * PHP 5.6 compatible — no shared class with Studio LocalizationStore.
 */

if (!function_exists('vpc_parse_accept_language')) {
    /**
     * @return array<int, array{tag: string, q: float}>
     */
    function vpc_parse_accept_language($header)
    {
        if (!is_string($header) || trim($header) === '') {
            return array();
        }

        $parts = array();
        $index = 0;
        foreach (explode(',', $header) as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }

            $tag = $piece;
            $q = 1.0;
            if (preg_match('/^(.+?)\\s*;\\s*q\\s*=\\s*([0-9.]+)/i', $piece, $m)) {
                $tag = trim($m[1]);
                $q = (float) $m[2];
            }

            $parts[] = array('tag' => $tag, 'q' => $q, 'i' => $index);
            $index++;
        }

        usort($parts, function ($a, $b) {
            if ($a['q'] !== $b['q']) {
                return ($a['q'] > $b['q']) ? -1 : 1;
            }
            return $a['i'] - $b['i'];
        });

        return $parts;
    }
}

if (!function_exists('vpc_map_bcp47_to_language_id')) {
    /**
     * Map a single Accept-Language tag to a studio language id, or null when unmapped.
     *
     * @param string $tag
     * @param list<string> $availableIds
     * @return string|null
     */
    function vpc_map_bcp47_to_language_id($tag, array $availableIds)
    {
        $tag = strtolower(str_replace('_', '-', trim((string) $tag)));
        if ($tag === '') {
            return null;
        }

        $segments = explode('-', $tag);
        $primary = $segments[0];

        if ($primary === 'pt') {
            return in_array('pt', $availableIds, true) ? 'pt' : null;
        }

        if ($primary === 'ar') {
            return in_array('ar', $availableIds, true) ? 'ar' : null;
        }

        if (strlen($primary) === 2 && in_array($primary, $availableIds, true)) {
            return $primary;
        }

        return null;
    }
}

if (!function_exists('vpc_resolve_language')) {
    /**
     * @param string|null $acceptLanguage
     * @param string|null $langOverride  ?lang= value; bypasses completeness gate when valid
     * @param list<string> $availableIds
     * @param array<string, bool> $completeness
     */
    function vpc_resolve_language(
        $acceptLanguage,
        $langOverride,
        array $availableIds,
        array $completeness
    ) {
        if ($langOverride !== null && $langOverride !== '') {
            $override = strtolower(trim((string) $langOverride));
            if (in_array($override, $availableIds, true)) {
                return $override;
            }
        }

        $candidates = vpc_parse_accept_language(is_string($acceptLanguage) ? $acceptLanguage : '');
        foreach ($candidates as $candidate) {
            $mapped = vpc_map_bcp47_to_language_id($candidate['tag'], $availableIds);
            if ($mapped === null) {
                continue;
            }

            if (!empty($completeness[$mapped])) {
                return $mapped;
            }
        }

        return 'en';
    }
}
