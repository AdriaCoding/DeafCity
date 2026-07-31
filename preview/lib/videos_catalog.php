<?php
require_once __DIR__ . '/catalog_projection.php';

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

if (!function_exists('vpc_filter_options_from_catalog')) {
    /**
     * Derive filter options for one Catalog field (sign_language, edition,
     * typology, ...), resolved to labels via studio-config.json. This is the
     * single place that walks the Catalog + config for a filterable field —
     * adding a new filterable Video field means one call to this function,
     * not a new copy-pasted extractor.
     *
     * @param array<string, mixed> $catalog
     * @param string $studioConfigPath
     * @param string $catalogField  Catalog video field, e.g. 'edition'
     * @param string $configListKey studio-config.json list key, e.g. 'editions'
     * @param 'config_order'|'label_second_word' $order
     *   'config_order': ordered as in studio-config.json (edition, typology).
     *   'label_second_word': alphabetical by the label's second word, e.g.
     *   "Spanish" in "LSE Spanish Sign Language" (sign_language).
     * @return array<int, array{value: string, label: string, short_label?: string}>
     */
    function vpc_filter_options_from_catalog(array $catalog, $studioConfigPath, $catalogField, $configListKey, $order = 'config_order') {
        $seen = array();
        foreach (isset($catalog['videos']) ? $catalog['videos'] : array() as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            $val = isset($v[$catalogField]) ? trim((string) $v[$catalogField]) : '';
            if ($val !== '' && !isset($seen[$val])) {
                $seen[$val] = true;
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
                foreach (isset($cfg[$configListKey]) ? $cfg[$configListKey] : array() as $item) {
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
                'value' => $id,
                'label' => isset($labelMap[$id]) ? $labelMap[$id] : $id,
            );
            if (isset($shortLabelMap[$id])) {
                $opt['short_label'] = $shortLabelMap[$id];
            }
            if ($order === 'config_order') {
                $opt['_order'] = isset($orderMap[$id]) ? $orderMap[$id] : 999;
            }
            $opts[] = $opt;
        }

        if ($order === 'config_order') {
            usort($opts, function ($a, $b) { return $a['_order'] - $b['_order']; });
            $opts = array_map(function ($o) {
                unset($o['_order']);
                return $o;
            }, $opts);
        } else {
            usort($opts, function ($a, $b) {
                $wordA = vpc_sign_language_sort_key($a['label']);
                $wordB = vpc_sign_language_sort_key($b['label']);
                $cmp = strcasecmp($wordA, $wordB);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return strcasecmp($a['label'], $b['label']);
            });
        }

        return $opts;
    }
}

if (!function_exists('vpc_sign_language_options_from_catalog')) {
    /**
     * @param array<string, mixed> $catalog
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_sign_language_options_from_catalog(array $catalog, $studioConfigPath) {
        return vpc_filter_options_from_catalog($catalog, $studioConfigPath, 'sign_language', 'sign_languages', 'label_second_word');
    }
}

if (!function_exists('vpc_sign_language_sort_key')) {
    /**
     * Sort key for sign language labels: second whitespace-separated word,
     * falling back to the full label when only one word is present.
     *
     * @param string $label
     * @return string
     */
    function vpc_sign_language_sort_key($label) {
        $label = trim((string) $label);
        if ($label === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $label);
        if (!is_array($parts) || count($parts) < 2) {
            return $label;
        }
        return $parts[1];
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

if (!function_exists('vpc_participant_sequence_from_title')) {
    /**
     * Sequence index from a Vimeo/catalog title (`…_Name_N_HD|4K`).
     *
     * @param string $title
     * @return string Decimal without leading zeros, or '' when unparseable
     */
    function vpc_participant_sequence_from_title($title)
    {
        $title = trim((string) $title);
        if ($title === '') {
            return '';
        }
        if (!preg_match('/_(\d+)_(?:HD|4K)\b/i', $title, $m)) {
            return '';
        }

        return (string) ((int) $m[1]);
    }
}

if (!function_exists('vpc_format_participant_nav_label')) {
    /**
     * Participants chrome label: bare name, or "Name N" when sequence is known.
     *
     * @param string $name
     * @param string $sequence
     * @return string
     */
    function vpc_format_participant_nav_label($name, $sequence)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        $sequence = trim((string) $sequence);
        if ($sequence === '') {
            return $name;
        }

        return $name . ' ' . $sequence;
    }
}

if (!function_exists('vpc_playlist_entry_apply_participant_fields')) {
    /**
     * Copy participant + title sequence onto a playlist entry.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $v Catalog video row
     * @return void
     */
    function vpc_playlist_entry_apply_participant_fields(array &$entry, array $v)
    {
        $participant = isset($v['participant']) ? trim((string) $v['participant']) : '';
        if ($participant !== '') {
            $entry['participant'] = $participant;
        }
        $title = isset($v['title']) ? (string) $v['title'] : '';
        $seq = vpc_participant_sequence_from_title($title);
        if ($seq !== '') {
            $entry['participant_sequence'] = $seq;
        }
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
            if (!is_array($v)) {
                continue;
            }
            $entry = vpc_project_catalog_video($v);
            if ($entry === null) {
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

if (!function_exists('vpc_sort_playlist_by_participant_sequence')) {
    /**
     * Sort playlist entries by numeric participant_sequence ascending.
     * Missing/non-numeric sequences sort last; original order is the tie-breaker.
     *
     * @param array<int, array<string, mixed>> $playlist
     * @return array<int, array<string, mixed>>
     */
    function vpc_sort_playlist_by_participant_sequence(array $playlist)
    {
        $decorated = array();
        foreach ($playlist as $i => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $raw = isset($entry['participant_sequence'])
                ? trim((string) $entry['participant_sequence'])
                : '';
            $hasSeq = ($raw !== '' && is_numeric($raw));
            $decorated[] = array(
                'i' => (int) $i,
                'entry' => $entry,
                'has' => $hasSeq,
                'seq' => $hasSeq ? (int) $raw : 0,
            );
        }
        usort($decorated, function ($a, $b) {
            if ($a['has'] !== $b['has']) {
                return $a['has'] ? -1 : 1;
            }
            if ($a['has'] && $a['seq'] !== $b['seq']) {
                return ($a['seq'] < $b['seq']) ? -1 : 1;
            }
            if ($a['i'] === $b['i']) {
                return 0;
            }
            return ($a['i'] < $b['i']) ? -1 : 1;
        });
        $out = array();
        foreach ($decorated as $row) {
            $out[] = $row['entry'];
        }
        return $out;
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
            if (!is_array($v)) {
                continue;
            }
            $vParticipant = isset($v['participant']) ? trim((string) $v['participant']) : '';
            if ($vParticipant !== $participantName) {
                continue;
            }
            $entry = vpc_project_catalog_video($v);
            if ($entry === null) {
                continue;
            }
            $playlist[] = $entry;
        }
        return vpc_sort_playlist_by_participant_sequence($playlist);
    }
}

if (!function_exists('vpc_catalog_deaf_hearing_tag_count')) {
    /**
     * Count visible catalog videos tagged DEAF&HEARING (DH27 chrome guard).
     *
     * @param array<string, mixed> $catalog
     * @return int
     */
    function vpc_catalog_deaf_hearing_tag_count(array $catalog) {
        if (!isset($catalog['videos']) || !is_array($catalog['videos'])) {
            return 0;
        }
        $count = 0;
        foreach ($catalog['videos'] as $v) {
            if (!is_array($v) || !vpc_catalog_entry_is_visible($v)) {
                continue;
            }
            if (!isset($v['tags']) || !is_array($v['tags'])) {
                continue;
            }
            foreach ($v['tags'] as $tag) {
                if (is_string($tag) && trim($tag) === 'DEAF&HEARING') {
                    $count++;
                    break;
                }
            }
        }
        return $count;
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
     * @param array<string, mixed> $catalog
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_edition_options_from_catalog(array $catalog, $studioConfigPath) {
        return vpc_filter_options_from_catalog($catalog, $studioConfigPath, 'edition', 'editions', 'config_order');
    }
}

if (!function_exists('vpc_typology_options_from_catalog')) {
    /**
     * @param array<string, mixed> $catalog
     * @return array<int, array{value: string, label: string}>
     */
    function vpc_typology_options_from_catalog(array $catalog, $studioConfigPath) {
        return vpc_filter_options_from_catalog($catalog, $studioConfigPath, 'typology', 'typologies', 'config_order');
    }
}

if (!function_exists('vpc_catalog_collection')) {
    /**
     * Assemble the catalog-derived bundle a Preview route needs to render
     * its player: shuffled playlist, unshuffled catalog playlist (for
     * client-side filtering), the three filter option lists, subtitle
     * languages, and the DEAF&HEARING chrome guard.
     *
     * This is the single place that walks the Catalog to build that bundle.
     * `preview/index.php` (home) and `preview/lib/bottom_bar_player_config.php`
     * (About / Participants secondary chrome) both need it; before this
     * function existed they independently re-derived it, so a new
     * catalog-derived field had to be wired into both places by hand.
     * Locale strings are intentionally left to the caller — this module has
     * no dependency on preview_locale.php.
     *
     * @param array<string, mixed>|null $catalog
     * @param string $studioConfigPath
     * @return array{
     *   playlist: array<int, array<string, mixed>>,
     *   catalog_playlist: array<int, array<string, mixed>>,
     *   sign_language_options: array<int, array<string, mixed>>,
     *   edition_options: array<int, array<string, mixed>>,
     *   typology_options: array<int, array<string, mixed>>,
     *   subtitle_languages: array<int, array<string, mixed>>,
     *   deaf_hearing_enabled: bool,
     * }
     */
    function vpc_catalog_collection($catalog, $studioConfigPath) {
        if (!$catalog) {
            return array(
                'playlist' => array(),
                'catalog_playlist' => array(),
                'sign_language_options' => array(),
                'edition_options' => array(),
                'typology_options' => array(),
                'subtitle_languages' => array(),
                'deaf_hearing_enabled' => false,
            );
        }

        $catalogPlaylist = vpc_vimeo_playlist_all_from_catalog($catalog);

        return array(
            'playlist' => vpc_shuffle_playlist($catalogPlaylist),
            'catalog_playlist' => $catalogPlaylist,
            'sign_language_options' => vpc_sign_language_options_from_catalog($catalog, $studioConfigPath),
            'edition_options' => vpc_edition_options_from_catalog($catalog, $studioConfigPath),
            'typology_options' => vpc_typology_options_from_catalog($catalog, $studioConfigPath),
            'subtitle_languages' => vpc_subtitle_languages_from_studio_config($studioConfigPath),
            'deaf_hearing_enabled' => vpc_catalog_deaf_hearing_tag_count($catalog) > 0,
        );
    }
}
