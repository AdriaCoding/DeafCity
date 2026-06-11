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

            $tracks = array();
            if (!empty($v['captions']) && is_array($v['captions'])) {
                foreach ($v['captions'] as $c) {
                    if (!is_array($c) || empty($c['file']) || empty($c['label'])) {
                        continue;
                    }
                    $fn = basename((string) $c['file']);
                    $tracks[] = array(
                        'file'  => $fn,
                        'label' => (string) $c['label'],
                    );
                }
            }
            if (count($tracks) > 0) {
                $entry['caption_tracks'] = $tracks;
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
        if (is_readable($studioConfigPath)) {
            $raw = file_get_contents($studioConfigPath);
            $cfg = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($cfg)) {
                foreach (isset($cfg['sign_languages']) ? $cfg['sign_languages'] : array() as $item) {
                    if (!empty($item['id']) && !empty($item['label'])) {
                        $labelMap[$item['id']] = $item['label'];
                    }
                }
            }
        }

        $opts = array();
        foreach (array_keys($seen) as $id) {
            $opts[] = array(
                'value' => $id,
                'label' => isset($labelMap[$id]) ? $labelMap[$id] : $id,
            );
        }
        usort($opts, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });
        return $opts;
    }
}

if (!function_exists('vpc_vimeo_playlist_all_from_catalog')) {
    /**
     * Build a full $vpc playlist from all videos.json entries in array order (order defines playback within a category).
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

            $tracks = array();
            if (!empty($v['captions']) && is_array($v['captions'])) {
                foreach ($v['captions'] as $c) {
                    if (!is_array($c) || empty($c['file']) || empty($c['label'])) {
                        continue;
                    }
                    $fn = basename((string) $c['file']);
                    $tracks[] = array(
                        'file'  => $fn,
                        'label' => (string) $c['label'],
                    );
                }
            }
            if (count($tracks) > 0) {
                $entry['caption_tracks'] = $tracks;
            }

            $sl = isset($v['sign_language']) ? trim((string) $v['sign_language']) : '';
            if ($sl !== '') {
                $entry['sign_language'] = $sl;
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
