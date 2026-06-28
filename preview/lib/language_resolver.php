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

            $parts[] = array('tag' => $tag, 'q' => $q);
        }

        usort($parts, function ($a, $b) {
            if ($a['q'] === $b['q']) {
                return 0;
            }
            return ($a['q'] > $b['q']) ? -1 : 1;
        });

        return $parts;
    }
}

if (!function_exists('vpc_map_bcp47_to_language_id')) {
    /**
     * Map a single Accept-Language tag to a studio language id, or null when unmapped.
     * Returns '__arabic_first_ready__' for bare ar / unregioned ar-* (caller picks first ready).
     *
     * @param string $tag
     * @param list<string> $availableIds
     * @param list<string> $arabicOrder
     * @return string|null
     */
    function vpc_map_bcp47_to_language_id($tag, array $availableIds, array $arabicOrder)
    {
        $tag = strtolower(str_replace('_', '-', trim((string) $tag)));
        if ($tag === '') {
            return null;
        }

        $segments = explode('-', $tag);
        $primary = $segments[0];
        $region = isset($segments[1]) ? strtolower($segments[1]) : '';

        if ($primary === 'pt') {
            return in_array('pt', $availableIds, true) ? 'pt' : null;
        }

        if ($primary === 'ar' && $region === 'dz') {
            return in_array('arq', $availableIds, true) ? 'arq' : null;
        }

        if ($primary === 'ar' && $region === 'tn') {
            return in_array('aeb', $availableIds, true) ? 'aeb' : null;
        }

        if ($primary === 'ar') {
            return '__arabic_first_ready__';
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
     * @param list<string> $arabicOrder  Arabic-script ids in studio-config order
     */
    function vpc_resolve_language(
        $acceptLanguage,
        $langOverride,
        array $availableIds,
        array $completeness,
        array $arabicOrder
    ) {
        if ($langOverride !== null && $langOverride !== '') {
            $override = strtolower(trim((string) $langOverride));
            if (in_array($override, $availableIds, true)) {
                return $override;
            }
        }

        $candidates = vpc_parse_accept_language(is_string($acceptLanguage) ? $acceptLanguage : '');
        foreach ($candidates as $candidate) {
            $mapped = vpc_map_bcp47_to_language_id($candidate['tag'], $availableIds, $arabicOrder);
            if ($mapped === null) {
                continue;
            }

            if ($mapped === '__arabic_first_ready__') {
                foreach ($arabicOrder as $arId) {
                    if (!in_array($arId, $availableIds, true)) {
                        continue;
                    }
                    if (!empty($completeness[$arId])) {
                        return $arId;
                    }
                }
                continue;
            }

            if (!empty($completeness[$mapped])) {
                return $mapped;
            }
        }

        return 'en';
    }
}
