<?php
/**
 * Load video metadata from data/videos.json for the Vimeo caption player.
 *
 * videos.json shape:
 *   { "videos": [ {
 *     "id", "vimeo_id"?, "embed_url"?, "title"?, "sign_language"?(string — same titles as playlists.json),
 *     "captions": [ { "label", "file" } ]
 *   } ] }
 *
 * Caption "file" is a basename under data/captions/ (served via preview/captions-static.php).
 */

if (!function_exists('vpc_sign_language_options_from_playlists_json')) {
    /**
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_sign_language_options_from_playlists_json($playlistsJsonPath) {
        if (!is_string($playlistsJsonPath) || $playlistsJsonPath === '' || !is_readable($playlistsJsonPath)) {
            return array();
        }
        $raw = file_get_contents($playlistsJsonPath);
        if ($raw === false) {
            return array();
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['playlists']) || !is_array($data['playlists'])) {
            return array();
        }
        $opts = array();
        foreach ($data['playlists'] as $block) {
            if (!is_array($block)) {
                continue;
            }
            $title = isset($block['title']) ? trim((string) $block['title']) : '';
            if ($title === '') {
                continue;
            }
            $opts[] = array(
                'value' => $title,
                'label' => $title,
            );
        }
        return $opts;
    }
}

if (!function_exists('vpc_load_videos_catalog')) {
    /**
     * @return array{ videos: array<int, array<string, mixed>> }|null
     */
    function vpc_load_videos_catalog($jsonPath) {
        if (!is_string($jsonPath) || $jsonPath === '' || !is_readable($jsonPath)) {
            trigger_error('videos catalog: file not readable', E_USER_WARNING);
            return null;
        }
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            trigger_error('videos catalog: failed to read file', E_USER_WARNING);
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['videos']) || !is_array($data['videos'])) {
            trigger_error('videos catalog: invalid JSON (expected "videos" array)', E_USER_WARNING);
            return null;
        }
        return $data;
    }
}

if (!function_exists('vpc_catalog_entry_is_visible')) {
    /**
     * @param array<string, mixed> $entry
     */
    function vpc_catalog_entry_is_visible(array $entry) {
        return (isset($entry['invisible']) ? $entry['invisible'] : false) !== true;
    }
}

if (!function_exists('vpc_vimeo_playlist_from_catalog')) {
    /**
     * Build a $vpc-compatible playlist from ordered catalog ids.
     *
     * @param array<string, mixed> $catalog Decoded root (must include "videos")
     * @param string[]             $orderedIds Catalog entry "id" values, in play order
     * @return array<int, array<string, mixed>> playlist entries for vimeo_caption_player
     */
    function vpc_vimeo_playlist_from_catalog(array $catalog, array $orderedIds) {
        /** @var array<string, array<string, mixed>> $byId */
        $byId = array();
        foreach ($catalog['videos'] as $v) {
            if (!is_array($v) || empty($v['id']) || !is_string($v['id'])) {
                continue;
            }
            if (!vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $byId[$v['id']] = $v;
        }

        $playlist = array();
        foreach ($orderedIds as $slug) {
            if (!is_string($slug) || $slug === '' || empty($byId[$slug])) {
                continue;
            }
            $v = $byId[$slug];
            $entry = array();

            if (!empty($v['vimeo_id'])) {
                $entry['video_id'] = preg_replace('/\D/', '', (string) $v['vimeo_id']);
            }
            if (!empty($v['embed_url']) && is_string($v['embed_url'])) {
                $entry['embed_url'] = $v['embed_url'];
            }

            if (!empty($v['captions']) && is_array($v['captions'])) {
                $tracks = vpc_caption_tracks_from_catalog_captions($v['captions']);
                if (count($tracks) > 0) {
                    $entry['caption_tracks'] = $tracks;
                }
            }

            $sl = isset($v['sign_language']) ? trim((string) $v['sign_language']) : '';
            if ($sl !== '') {
                $entry['sign_language'] = $sl;
            }

            if (empty($entry['video_id']) && empty($entry['embed_url'])) {
                continue;
            }

            $playlist[] = $entry;
        }
        return $playlist;
    }
}

if (!function_exists('vpc_sign_language_options_from_catalog')) {
    /**
     * Derive sign language filter options from distinct sign_language IDs in the catalog,
     * resolved to labels via studio-config.json.
     *
     * @param array<string, mixed> $catalog
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_sign_language_options_from_catalog(array $catalog, $studioConfigPath) {
        $seen = array();
        foreach (isset($catalog['videos']) ? $catalog['videos'] : array() as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $sl = isset($v['sign_language']) ? trim((string) $v['sign_language']) : '';
            if ($sl !== '' && !isset($seen[$sl])) {
                $seen[$sl] = true;
            }
        }

        $labelMap = array();
        $shortLabelMap = array();
        if (is_readable($studioConfigPath)) {
            $raw = file_get_contents($studioConfigPath);
            $cfg = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($cfg)) {
                foreach (isset($cfg['sign_languages']) ? $cfg['sign_languages'] : array() as $item) {
                    if (!empty($item['id']) && !empty($item['label'])) {
                        $labelMap[$item['id']] = $item['label'];
                        if (!empty($item['short_label']) && is_string($item['short_label'])) {
                            $shortLabelMap[$item['id']] = $item['short_label'];
                        }
                    }
                }
            }
        }

        $opts = array();
        foreach (array_keys($seen) as $id) {
            $opt = array(
                'value' => $id,
                'label' => isset($labelMap[$id]) ? $labelMap[$id] : $id,
            );
            if (isset($shortLabelMap[$id])) {
                $opt['short_label'] = $shortLabelMap[$id];
            }
            $opts[] = $opt;
        }
        usort($opts, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });
        return $opts;
    }
}

if (!function_exists('vpc_caption_tracks_from_catalog_captions')) {
    /**
     * Build vpc caption_tracks from catalog captions[], preserving lang when present.
     *
     * @param array<int, array<string, mixed>> $captions
     * @return array<int, array{file: string, label: string, lang?: string}>
     */
    function vpc_caption_tracks_from_catalog_captions(array $captions) {
        $tracks = array();
        foreach ($captions as $c) {
            if (!is_array($c) || empty($c['file']) || empty($c['label'])) {
                continue;
            }
            $track = array(
                'file'  => basename((string) $c['file']),
                'label' => (string) $c['label'],
            );
            if (!empty($c['lang']) && is_string($c['lang'])) {
                $lang = trim($c['lang']);
                if ($lang !== '') {
                    $track['lang'] = $lang;
                }
            }
            $tracks[] = $track;
        }
        return $tracks;
    }
}

if (!function_exists('vpc_subtitle_languages_from_studio_config')) {
    /**
     * Load subtitle_languages from studio-config.json for spoken-language track mapping (D16′).
     *
     * @param string $studioConfigPath
     * @return array<int, array{id: string, label: string, vimeo_code?: string}>
     */
    function vpc_subtitle_languages_from_studio_config($studioConfigPath) {
        if (!is_string($studioConfigPath) || $studioConfigPath === '' || !is_readable($studioConfigPath)) {
            return array();
        }
        $raw = file_get_contents($studioConfigPath);
        if ($raw === false) {
            return array();
        }
        $cfg = json_decode($raw, true);
        if (!is_array($cfg) || empty($cfg['subtitle_languages']) || !is_array($cfg['subtitle_languages'])) {
            return array();
        }
        $out = array();
        foreach ($cfg['subtitle_languages'] as $item) {
            if (!is_array($item) || empty($item['id']) || empty($item['label'])) {
                continue;
            }
            $entry = array(
                'id'    => (string) $item['id'],
                'label' => (string) $item['label'],
            );
            if (!empty($item['vimeo_code']) && is_string($item['vimeo_code'])) {
                $entry['vimeo_code'] = (string) $item['vimeo_code'];
            }
            $out[] = $entry;
        }
        return $out;
    }
}

if (!function_exists('vpc_normalize_spoken_lang_tag')) {
    /**
     * @param string $lang
     * @return string
     */
    function vpc_normalize_spoken_lang_tag($lang) {
        $norm = strtolower(trim((string) $lang));
        return str_replace('_', '-', $norm);
    }
}

if (!function_exists('vpc_resolve_track_lang_to_subtitle_id')) {
    /**
     * Map a caption track lang tag to studio-config subtitle_languages id (D16′).
     * Collapses region variants (es-MX, es-ES → es).
     *
     * @param string $trackLang
     * @param array<int, array{id: string, label: string, vimeo_code?: string}> $subtitleLanguages
     * @return string  subtitle language id, or '' when unmapped
     */
    function vpc_resolve_track_lang_to_subtitle_id($trackLang, array $subtitleLanguages) {
        $norm = vpc_normalize_spoken_lang_tag($trackLang);
        if ($norm === '') {
            return '';
        }

        foreach ($subtitleLanguages as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            if (vpc_normalize_spoken_lang_tag($item['id']) === $norm) {
                return (string) $item['id'];
            }
        }
        foreach ($subtitleLanguages as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            if (!empty($item['vimeo_code'])
                && vpc_normalize_spoken_lang_tag($item['vimeo_code']) === $norm) {
                return (string) $item['id'];
            }
        }

        $parts = explode('-', $norm);
        $base = isset($parts[0]) ? $parts[0] : '';
        if ($base === '') {
            return '';
        }
        foreach ($subtitleLanguages as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            if (vpc_normalize_spoken_lang_tag($item['id']) === $base) {
                return (string) $item['id'];
            }
            if (!empty($item['vimeo_code'])
                && vpc_normalize_spoken_lang_tag($item['vimeo_code']) === $base) {
                return (string) $item['id'];
            }
        }
        return '';
    }
}

if (!function_exists('vpc_vimeo_playlist_all_from_catalog')) {
    /**
     * Build a full $vpc playlist from all catalog entries in array order.
     * Each entry carries all filterable catalog fields (sign_language, edition,
     * typology, participant) so the client-side composable filter (D18) can
     * filter without a page reload.
     *
     * @param array<string, mixed> $catalog
     * @return array<int, array<string, mixed>>
     */
    function vpc_vimeo_playlist_all_from_catalog(array $catalog) {
        if (!isset($catalog['videos']) || !is_array($catalog['videos'])) {
            return array();
        }
        $playlist = array();
        foreach ($catalog['videos'] as $v) {
            if (!is_array($v) || empty($v['id']) || !is_string($v['id'])) {
                continue;
            }
            if (!vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $entry = array();

            if (!empty($v['vimeo_id'])) {
                $entry['video_id'] = preg_replace('/\D/', '', (string) $v['vimeo_id']);
            }
            if (!empty($v['embed_url']) && is_string($v['embed_url'])) {
                $entry['embed_url'] = $v['embed_url'];
            }

            if (!empty($v['captions']) && is_array($v['captions'])) {
                $tracks = vpc_caption_tracks_from_catalog_captions($v['captions']);
                if (count($tracks) > 0) {
                    $entry['caption_tracks'] = $tracks;
                }
            }

            // Filterable catalog fields — all passed to JS for client-side filtering (D17, D18).
            $sl = isset($v['sign_language']) ? trim((string) $v['sign_language']) : '';
            if ($sl !== '') {
                $entry['sign_language'] = $sl;
            }
            $edition = isset($v['edition']) ? trim((string) $v['edition']) : '';
            if ($edition !== '') {
                $entry['edition'] = $edition;
            }
            $typology = isset($v['typology']) ? trim((string) $v['typology']) : '';
            if ($typology !== '') {
                $entry['typology'] = $typology;
            }
            $participant = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($participant !== '') {
                $entry['participant'] = $participant;
            }

            // Filterable catalog fields — passed to JS for client-side composable filtering (D17, D18).
            $edition = isset($v['edition']) ? trim((string) $v['edition']) : '';
            if ($edition !== '') {
                $entry['edition'] = $edition;
            }
            $typology = isset($v['typology']) ? trim((string) $v['typology']) : '';
            if ($typology !== '') {
                $entry['typology'] = $typology;
            }
            $participant = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($participant !== '') {
                $entry['participant'] = $participant;
            }

            $eParams = isset($v['embed_params']) && is_array($v['embed_params'])
                ? $v['embed_params']
                : array();
            if (count($eParams) > 0) {
                $entry['embed_params'] = $eParams;
            }

            if (empty($entry['video_id']) && empty($entry['embed_url'])) {
                continue;
            }

            $playlist[] = $entry;
        }
        return $playlist;
    }
}

if (!function_exists('vpc_participant_thumbnail_display_url')) {
    /**
     * Strip Vimeo r=pad from a catalog thumbnail URL for grid display.
     * r=pad letterboxes the JPEG with black bars; without it the frame fills naturally.
     *
     * @param string $url thumbnail_url from catalog.json
     * @return string
     */
    function vpc_participant_thumbnail_display_url($url) {
        $url = trim((string) $url);
        if ($url === '' || strpos($url, 'vimeocdn.com') === false) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $query = array();
        if (!empty($parts['query'])) {
            parse_str(ltrim($parts['query'], '&?'), $query);
        }

        if (!isset($query['r']) || (string) $query['r'] !== 'pad') {
            return $url;
        }

        unset($query['r']);

        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $rebuilt = $scheme . '://' . $parts['host'];
        if (!empty($parts['path'])) {
            $rebuilt .= $parts['path'];
        }
        if (count($query) > 0) {
            $rebuilt .= '?' . http_build_query($query);
        }

        return $rebuilt;
    }
}

if (!function_exists('vpc_participant_videos_from_catalog')) {
    /**
     * All visible catalog entries for one participant (catalog order).
     *
     * @param array<string, mixed> $catalog
     * @param string $participantName
     * @return array<int, array<string, mixed>>
     */
    function vpc_participant_videos_from_catalog(array $catalog, $participantName)
    {
        $participantName = trim((string) $participantName);
        if ($participantName === '' || !isset($catalog['videos']) || !is_array($catalog['videos'])) {
            return array();
        }

        $videos = array();
        foreach ($catalog['videos'] as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $name = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($name === $participantName) {
                $videos[] = $v;
            }
        }

        return $videos;
    }
}

if (!function_exists('vpc_participants_from_catalog')) {
    /**
     * Return one representative visible video per distinct participant name.
     * Keyed by participant name; value is the first visible catalog entry for that participant.
     * Entries where `participant` is empty are skipped.
     *
     * @param array<string, mixed> $catalog
     * @return array<string, array<string, mixed>>
     */
    function vpc_participants_from_catalog(array $catalog) {
        if (!isset($catalog['videos']) || !is_array($catalog['videos'])) {
            return array();
        }
        $result = array();
        foreach ($catalog['videos'] as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $name = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($name === '') {
                continue;
            }
            if (!isset($result[$name])) {
                $result[$name] = $v;
            }
        }
        return $result;
    }
}

if (!function_exists('vpc_participant_playlist_from_catalog')) {
    /**
     * Build a $vpc-compatible playlist from all visible catalog entries where
     * participant === $participantName. Same entry shape as vpc_vimeo_playlist_all_from_catalog.
     *
     * @param array<string, mixed> $catalog
     * @param string $participantName
     * @return array<int, array<string, mixed>>
     */
    function vpc_participant_playlist_from_catalog(array $catalog, $participantName) {
        $participantName = trim((string) $participantName);
        if ($participantName === '') {
            return array();
        }
        if (!isset($catalog['videos']) || !is_array($catalog['videos'])) {
            return array();
        }
        $playlist = array();
        foreach ($catalog['videos'] as $v) {
            if (!is_array($v) || empty($v['id']) || !is_string($v['id'])) {
                continue;
            }
            if (!vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $vParticipant = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($vParticipant !== $participantName) {
                continue;
            }
            $entry = array();

            if (!empty($v['vimeo_id'])) {
                $entry['video_id'] = preg_replace('/\D/', '', (string) $v['vimeo_id']);
            }
            if (!empty($v['embed_url']) && is_string($v['embed_url'])) {
                $entry['embed_url'] = $v['embed_url'];
            }

            if (!empty($v['captions']) && is_array($v['captions'])) {
                $tracks = vpc_caption_tracks_from_catalog_captions($v['captions']);
                if (count($tracks) > 0) {
                    $entry['caption_tracks'] = $tracks;
                }
            }

            $sl = isset($v['sign_language']) ? trim((string) $v['sign_language']) : '';
            if ($sl !== '') {
                $entry['sign_language'] = $sl;
            }
            $edition = isset($v['edition']) ? trim((string) $v['edition']) : '';
            if ($edition !== '') {
                $entry['edition'] = $edition;
            }
            $typology = isset($v['typology']) ? trim((string) $v['typology']) : '';
            if ($typology !== '') {
                $entry['typology'] = $typology;
            }
            $participant = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($participant !== '') {
                $entry['participant'] = $participant;
            }

            $eParams = isset($v['embed_params']) && is_array($v['embed_params'])
                ? $v['embed_params']
                : array();
            if (count($eParams) > 0) {
                $entry['embed_params'] = $eParams;
            }

            if (empty($entry['video_id']) && empty($entry['embed_url'])) {
                continue;
            }

            $playlist[] = $entry;
        }
        return $playlist;
    }
}

if (!function_exists('vpc_shuffle_playlist')) {
    /**
     * Fisher-Yates shuffle of a playlist array (returns new shuffled copy).
     * The item at index 0 after shuffling is both the paused poster and the queue
     * head (D12: server-side random poster = shuffled queue head). Reload = new face.
     *
     * @param array<int, array<string, mixed>> $playlist
     * @return array<int, array<string, mixed>>
     */
    function vpc_shuffle_playlist(array $playlist) {
        $n = count($playlist);
        if ($n <= 1) {
            return $playlist;
        }
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp         = $playlist[$i];
            $playlist[$i] = $playlist[$j];
            $playlist[$j] = $tmp;
        }
        return array_values($playlist);
    }
}

if (!function_exists('vpc_edition_options_from_catalog')) {
    /**
     * Derive City/Edition filter options from distinct edition IDs in the catalog,
     * resolved to labels via studio-config.json. Order follows config file order.
     *
     * @param array<string, mixed> $catalog
     * @param string $studioConfigPath
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_edition_options_from_catalog(array $catalog, $studioConfigPath) {
        $seen = array();
        foreach (isset($catalog['videos']) ? $catalog['videos'] : array() as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $ed = isset($v['edition']) ? trim((string) $v['edition']) : '';
            if ($ed !== '' && !isset($seen[$ed])) {
                $seen[$ed] = true;
            }
        }

        $labelMap = array();
        $shortLabelMap = array();
        $orderMap = array();
        if (is_readable($studioConfigPath)) {
            $raw = file_get_contents($studioConfigPath);
            $cfg = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($cfg)) {
                $pos = 0;
                foreach (isset($cfg['editions']) ? $cfg['editions'] : array() as $item) {
                    if (!empty($item['id']) && !empty($item['label'])) {
                        $labelMap[$item['id']] = $item['label'];
                        if (!empty($item['short_label']) && is_string($item['short_label'])) {
                            $shortLabelMap[$item['id']] = $item['short_label'];
                        }
                        $orderMap[$item['id']] = $pos++;
                    }
                }
            }
        }

        $opts = array();
        foreach (array_keys($seen) as $id) {
            $opt = array(
                'value'  => $id,
                'label'  => isset($labelMap[$id]) ? $labelMap[$id] : $id,
                '_order' => isset($orderMap[$id]) ? $orderMap[$id] : 999,
            );
            if (isset($shortLabelMap[$id])) {
                $opt['short_label'] = $shortLabelMap[$id];
            }
            $opts[] = $opt;
        }
        usort($opts, function ($a, $b) { return $a['_order'] - $b['_order']; });
        return array_map(function ($o) {
            $out = array('value' => $o['value'], 'label' => $o['label']);
            if (isset($o['short_label'])) {
                $out['short_label'] = $o['short_label'];
            }
            return $out;
        }, $opts);
    }
}

if (!function_exists('vpc_typology_options_from_catalog')) {
    /**
     * Derive Typology filter options from distinct typology IDs in the catalog,
     * resolved to labels via studio-config.json. Order follows config file order.
     *
     * @param array<string, mixed> $catalog
     * @param string $studioConfigPath
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_typology_options_from_catalog(array $catalog, $studioConfigPath) {
        $seen = array();
        foreach (isset($catalog['videos']) ? $catalog['videos'] : array() as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $ty = isset($v['typology']) ? trim((string) $v['typology']) : '';
            if ($ty !== '' && !isset($seen[$ty])) {
                $seen[$ty] = true;
            }
        }

        $labelMap = array();
        $shortLabelMap = array();
        $orderMap = array();
        if (is_readable($studioConfigPath)) {
            $raw = file_get_contents($studioConfigPath);
            $cfg = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($cfg)) {
                $pos = 0;
                foreach (isset($cfg['typologies']) ? $cfg['typologies'] : array() as $item) {
                    if (!empty($item['id']) && !empty($item['label'])) {
                        $labelMap[$item['id']] = $item['label'];
                        if (!empty($item['short_label']) && is_string($item['short_label'])) {
                            $shortLabelMap[$item['id']] = $item['short_label'];
                        }
                        $orderMap[$item['id']] = $pos++;
                    }
                }
            }
        }

        $opts = array();
        foreach (array_keys($seen) as $id) {
            $opt = array(
                'value'  => $id,
                'label'  => isset($labelMap[$id]) ? $labelMap[$id] : $id,
                '_order' => isset($orderMap[$id]) ? $orderMap[$id] : 999,
            );
            if (isset($shortLabelMap[$id])) {
                $opt['short_label'] = $shortLabelMap[$id];
            }
            $opts[] = $opt;
        }
        usort($opts, function ($a, $b) { return $a['_order'] - $b['_order']; });
        return array_map(function ($o) {
            $out = array('value' => $o['value'], 'label' => $o['label']);
            if (isset($o['short_label'])) {
                $out['short_label'] = $o['short_label'];
            }
            return $out;
        }, $opts);
    }
}
