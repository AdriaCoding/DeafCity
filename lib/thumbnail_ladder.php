<?php

/**
 * Vimeo CDN thumbnail ladder for Website and Studio.
 *
 * Rungs: 640×360, 1920×1080, 3840×2160. Selection is native srcset.
 */

if (!function_exists('vpc_thumbnail_ladder_widths')) {
    /**
     * @return list<int>
     */
    function vpc_thumbnail_ladder_widths() {
        return array(640, 1920, 3840);
    }
}

if (!function_exists('vpc_thumbnail_base')) {
    /**
     * Vimeo picture base (no _WxH). Empty when the URL cannot feed the ladder.
     */
    function vpc_thumbnail_base($urlOrBase) {
        $parsed = vpc_thumbnail_parse($urlOrBase);
        if ($parsed === null) {
            return '';
        }
        $url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
        if (count($parsed['query']) > 0) {
            $url .= '?' . http_build_query($parsed['query']);
        }
        return $url;
    }
}

if (!function_exists('vpc_playlist_entry_thumbnail_base')) {
    /**
     * @param array<string, mixed> $entry
     */
    function vpc_playlist_entry_thumbnail_base(array $entry) {
        if (!empty($entry['thumbnail_base']) && is_string($entry['thumbnail_base'])) {
            $base = vpc_thumbnail_base($entry['thumbnail_base']);
            if ($base !== '') {
                return $base;
            }
        }
        if (!empty($entry['thumbnail_url']) && is_string($entry['thumbnail_url'])) {
            return vpc_thumbnail_base($entry['thumbnail_url']);
        }
        return '';
    }
}

if (!function_exists('vpc_thumbnail_attrs')) {
    /**
     * @param string $urlOrBase catalog thumbnail_url, thumbnail_base, or empty
     * @param string $role 'grid' (Participants, Studio cards) or 'player'
     * @return array{src: string, srcset: string, sizes: string}|array{}
     */
    function vpc_thumbnail_attrs($urlOrBase, $role) {
        $parsed = vpc_thumbnail_parse($urlOrBase);
        if ($parsed === null) {
            return array();
        }

        $fallbackWidth = ($role === 'player') ? 1920 : 640;
        $srcsetParts = array();
        foreach (vpc_thumbnail_ladder_widths() as $width) {
            $srcsetParts[] = vpc_thumbnail_size_url_from_parts($parsed, $width, true) . ' ' . $width . 'w';
        }

        $sizes = ($role === 'player')
            ? '100vw'
            : '(max-width: 600px) 45vw, 213px';

        return array(
            'src' => vpc_thumbnail_size_url_from_parts($parsed, $fallbackWidth, true),
            'srcset' => implode(', ', $srcsetParts),
            'sizes' => $sizes,
        );
    }
}

if (!function_exists('vpc_thumbnail_catalog_fields')) {
    /**
     * Catalog storage pair: thumbnail_base plus a 1920 thumbnail_url alias.
     * Empty when the URL cannot feed the ladder (caller may keep a non-Vimeo URL).
     *
     * @return array{thumbnail_base: string, thumbnail_url: string}|array{}
     */
    function vpc_thumbnail_catalog_fields($urlOrBase) {
        $base = vpc_thumbnail_base($urlOrBase);
        if ($base === '') {
            return array();
        }
        $player = vpc_thumbnail_attrs($base, 'player');
        if ($player === array()) {
            return array();
        }
        return array(
            'thumbnail_base' => $base,
            'thumbnail_url' => $player['src'],
        );
    }
}

if (!function_exists('vpc_thumbnail_html_attrs')) {
    /**
     * Escaped src/srcset/sizes attribute string, or empty when there is no ladder.
     */
    function vpc_thumbnail_html_attrs($urlOrBase, $role) {
        $attrs = vpc_thumbnail_attrs($urlOrBase, $role);
        if ($attrs === array()) {
            return '';
        }
        return 'src="' . htmlspecialchars($attrs['src'], ENT_QUOTES, 'UTF-8')
            . '" srcset="' . htmlspecialchars($attrs['srcset'], ENT_QUOTES, 'UTF-8')
            . '" sizes="' . htmlspecialchars($attrs['sizes'], ENT_QUOTES, 'UTF-8') . '"';
    }
}

if (!function_exists('vpc_thumbnail_parse')) {
    /**
     * @return array{scheme: string, host: string, path: string, query: array<string, string>}|null
     */
    function vpc_thumbnail_parse($urlOrBase) {
        $url = trim((string) $urlOrBase);
        if ($url === '' || strpos($url, 'vimeocdn.com') === false) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }

        $path = (string) $parts['path'];
        $path = preg_replace('/_\\d+x\\d+$/', '', $path);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $query = array();
        if (!empty($parts['query'])) {
            parse_str(ltrim((string) $parts['query'], '&?'), $query);
        }
        unset($query['r']);

        return array(
            'scheme' => isset($parts['scheme']) ? (string) $parts['scheme'] : 'https',
            'host' => (string) $parts['host'],
            'path' => $path,
            'query' => $query,
        );
    }
}

if (!function_exists('vpc_thumbnail_size_url_from_parts')) {
    /**
     * @param array{scheme: string, host: string, path: string, query: array<string, string>} $parsed
     */
    function vpc_thumbnail_size_url_from_parts(array $parsed, $width, $stripPad) {
        $height = vpc_thumbnail_height_for_width((int) $width);
        $url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'] . '_' . (int) $width . 'x' . $height;
        $query = $parsed['query'];
        if ($stripPad) {
            unset($query['r']);
        }
        if (count($query) > 0) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }
}

if (!function_exists('vpc_thumbnail_height_for_width')) {
    function vpc_thumbnail_height_for_width($width) {
        return (int) round(((int) $width) * 9 / 16);
    }
}
