<?php
/**
 * Vimeo iframe + caption language picker synced via Player SDK + static VTT JSON.
 *
 * Pass configuration as $vpc before including this file:
 *
 *   $vpc = array(
 *     'video_id' => '639494119',
 *     'embed_params' => array(...),           // merged into query string when using video_id
 *     // OR pass a full iframe src (include `controls=0` for chromeless + external controls):
 *     'embed_url' => 'https://player.vimeo.com/video/123?title=0&controls=0&autoplay=1&muted=1',
 *
 *     'caption_tracks' => array(
 *       array('file' => 'foo.en.vtt', 'label' => 'English'),
 *     ),
 *
 *     'instance_id' => 'main',                   // sanitized; used for HTML ids (default: random)
 *     'captions_heading' => 'Captions',
 *     'iframe_title' => 'Vimeo',
 *     'captions_endpoint' => '/preview/captions-static.php',
 *
 *     // Optional ordered playlist (each entry: video_id OR embed_url; optional caption_tracks, embed_params).
 *     // Omit to use single video_id / embed_url at the top level. Prev/Next appear when length > 1.
 *     // Canonical metadata: data/videos.json plus data/captions/*.vtt (videos_catalog.php).
 *     //
 *     // Optional: R2 filter row — sign language, city/edition, typology custom pickers (D15, D17, D18).
 *     // options: populated-from-present only (D17). Client composable filter state.
 *     'sign_language_filter' => array(
 *       'options' => array( array('value' => 'libras', 'label' => 'LIBRAS Brazilian Sign Language'), ),
 *     ),
 *     'edition_filter' => array(
 *       'options' => array( array('value' => '2023-sao-paulo', 'label' => '2023 São Paulo'), ),
 *     ),
 *     'typology_filter' => array(
 *       'options' => array( array('value' => 'acudits', 'label' => 'ACUDITS'), ),
 *     ),
 *     'playlist' => array(
 *       array(
 *         'video_id' => '639494119',
 *         'caption_tracks' => array( array('file' => 'foo.vtt', 'label' => 'English') ),
 *       ),
 *       array( 'video_id' => '1128906791' ),
 *     ),
 *   );
 */

if (!function_exists('preview_t')) {
    require_once dirname(__DIR__) . '/lib/preview_locale.php';
}
if (!isset($preview_i18n)) {
    $_vpcLocale = preview_bootstrap_locale();
    $preview_i18n = $_vpcLocale['i18n'];
}

if (!function_exists('vpc_vimeo_digits_from_public_url')) {
    /**
     * Extract the numeric Vimeo clip id from a vimeo.com or player.vimeo.com URL; returns '' if not found.
     */
    function vpc_vimeo_digits_from_public_url($embedUrl) {
        $parts = parse_url((string) $embedUrl);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }
        $host = strtolower($parts['host']);
        if (substr($host, -strlen('vimeo.com')) !== 'vimeo.com') {
            return '';
        }
        $path = isset($parts['path']) ? $parts['path'] : '';
        if (preg_match('~/video/(\\d+)~', $path, $m)) {
            return $m[1];
        }
        // e.g. /1128906791 or /channels/foo/videos/123 — take last path segment digits
        if (preg_match('~/(\\d+)(?:/|\\?|$)~', $path, $m)) {
            return $m[1];
        }
        return '';
    }
}

if (!function_exists('vpc_normalize_vimeo_caption_player_playlist')) {
    /**
     * Build a playlist spec: each item needs a numeric Vimeo id plus normalized caption tracks.
     *
     * @param array $vpc
     * @param array &$firstCaptionTracks Tracks for playlist[0] (for language picker markup).
     * @return array[] list of arrays with keys videoId (string digits), caption_tracks, embed_url|null, embed_params
     */
    function vpc_normalize_vimeo_caption_player_playlist(array $vpc, &$firstCaptionTracks) {
        $out = array();
        $usePlaylist = isset($vpc['playlist']) && is_array($vpc['playlist']) && count($vpc['playlist']) > 0;

        if ($usePlaylist) {
            foreach ($vpc['playlist'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $digits = '';
                $entryEmbed = null;
                if (!empty($entry['video_id'])) {
                    $digits = preg_replace('/\\D/', '', (string) $entry['video_id']);
                }
                if (!empty($entry['embed_url']) && is_string($entry['embed_url'])) {
                    $entryEmbed = (string) $entry['embed_url'];
                    if ($digits === '') {
                        $digits = vpc_vimeo_digits_from_public_url($entryEmbed);
                    }
                }
                if ($digits === '') {
                    trigger_error(
                        'vimeo_caption_player playlist entry needs video_id or a Vimeo embed_url with numeric id',
                        E_USER_WARNING
                    );
                    continue;
                }

                $ct = array();
                if (isset($entry['caption_tracks']) && is_array($entry['caption_tracks'])) {
                    foreach ($entry['caption_tracks'] as $track) {
                        if (!is_array($track) || empty($track['file']) || !isset($track['label'])) {
                            continue;
                        }
                        $row = array(
                            'file' => $track['file'],
                            'label' => $track['label'],
                        );
                        if (!empty($track['lang']) && is_string($track['lang'])) {
                            $row['lang'] = trim($track['lang']);
                        }
                        $ct[] = $row;
                    }
                }

                $eParams = isset($entry['embed_params']) && is_array($entry['embed_params'])
                    ? $entry['embed_params']
                    : array();

                $signLangMeta = isset($entry['sign_language']) && is_string($entry['sign_language'])
                    ? trim($entry['sign_language'])
                    : '';
                $editionMeta = isset($entry['edition']) && is_string($entry['edition'])
                    ? trim($entry['edition'])
                    : '';
                $typologyMeta = isset($entry['typology']) && is_string($entry['typology'])
                    ? trim($entry['typology'])
                    : '';
                $participantMeta = isset($entry['participant']) && is_string($entry['participant'])
                    ? trim($entry['participant'])
                    : '';
                $thumbnailMeta = isset($entry['thumbnail_url']) && is_string($entry['thumbnail_url'])
                    ? trim($entry['thumbnail_url'])
                    : '';
                $tagsMeta = array();
                if (isset($entry['tags']) && is_array($entry['tags'])) {
                    foreach ($entry['tags'] as $tag) {
                        if (!is_string($tag)) {
                            continue;
                        }
                        $tag = trim($tag);
                        if ($tag !== '' && !in_array($tag, $tagsMeta, true)) {
                            $tagsMeta[] = $tag;
                        }
                    }
                }

                $out[] = array(
                    'videoId' => $digits,
                    'caption_tracks' => $ct,
                    'embed_url' => $entryEmbed,
                    'embed_params' => $eParams,
                    'sign_language' => $signLangMeta,
                    'edition' => $editionMeta,
                    'typology' => $typologyMeta,
                    'participant' => $participantMeta,
                    'thumbnail_url' => $thumbnailMeta,
                    'tags' => $tagsMeta,
                );
            }
        }

        $digitsLegacy = '';
        $legacyEmbed = null;
        if (!empty($vpc['video_id'])) {
            $digitsLegacy = preg_replace('/\\D/', '', (string) $vpc['video_id']);
        }
        if ($digitsLegacy === '' && !empty($vpc['embed_url'])) {
            $legacyEmbed = (string) $vpc['embed_url'];
            $digitsLegacy = vpc_vimeo_digits_from_public_url($legacyEmbed);
        }

        $legacyTracks = array();
        if (isset($vpc['caption_tracks']) && is_array($vpc['caption_tracks'])) {
            foreach ($vpc['caption_tracks'] as $track) {
                if (!is_array($track) || empty($track['file']) || !isset($track['label'])) {
                    continue;
                }
                $row = array(
                    'file' => $track['file'],
                    'label' => $track['label'],
                );
                if (!empty($track['lang']) && is_string($track['lang'])) {
                    $row['lang'] = trim($track['lang']);
                }
                $legacyTracks[] = $row;
            }
        }

        if (!$usePlaylist || count($out) === 0) {
            if ($digitsLegacy === '') {
                return array();
            }
            $extraParams = isset($vpc['embed_params']) && is_array($vpc['embed_params'])
                ? $vpc['embed_params']
                : array();
            $legacySl = isset($vpc['sign_language']) && is_string($vpc['sign_language'])
                ? trim($vpc['sign_language'])
                : '';
            $legacyEdition = isset($vpc['edition']) && is_string($vpc['edition'])
                ? trim($vpc['edition']) : '';
            $legacyTypology = isset($vpc['typology']) && is_string($vpc['typology'])
                ? trim($vpc['typology']) : '';
            $legacyParticipant = isset($vpc['participant']) && is_string($vpc['participant'])
                ? trim($vpc['participant']) : '';
            $firstCaptionTracks = $legacyTracks;
            return array(array(
                'videoId' => $digitsLegacy,
                'caption_tracks' => $legacyTracks,
                'embed_url' => $legacyEmbed,
                'embed_params' => $extraParams,
                'sign_language' => $legacySl,
                'edition' => $legacyEdition,
                'typology' => $legacyTypology,
                'participant' => $legacyParticipant,
                'tags' => array(),
            ));
        }

        $firstCaptionTracks = isset($out[0]['caption_tracks'])
            ? $out[0]['caption_tracks']
            : array();
        return $out;
    }
}

if (!function_exists('vpc_merge_vimeo_embed_query')) {
    /**
     * Merge default/embed_params into an existing Vimeo player URL query string (preserving path/host/fragment).
     *
     * @param array $defaults  Base defaults (applied first).
     * @param array $overrides Passed last; wins over defaults and URL values.
     */
    function vpc_merge_vimeo_embed_query($embedUrl, array $defaults, array $overrides) {
        $parts = parse_url($embedUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $embedUrl;
        }

        $existing = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $merged = array_merge($defaults, $existing, $overrides);
        $qs = http_build_query($merged, '', '&', PHP_QUERY_RFC3986);

        $scheme = $parts['scheme'] . '://';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $path . ($qs !== '' ? '?' . $qs : '') . $frag;
    }
}

if (!function_exists('vpc_label_for_filter_option')) {
    /**
     * Resolve a studio-config label for a filter option value.
     *
     * @param array<int, array{value?: string, label?: string}> $optionsList
     * @param string $value
     * @param string $fallback
     * @return string
     */
    function vpc_label_for_filter_option(array $optionsList, $value, $fallback = '') {
        $value = (string) $value;
        foreach ($optionsList as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            if ((string) $opt['value'] === $value) {
                return isset($opt['label']) ? (string) $opt['label'] : $value;
            }
        }
        if ($value !== '') {
            return $value;
        }
        return $fallback;
    }
}

if (!function_exists('vpc_title_case_facet_label')) {
    /**
     * Title-case a label for compact typology faces (D14″).
     *
     * @param string $label
     * @return string
     */
    function vpc_title_case_facet_label($label) {
        $s = (string) $label;
        if ($s === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_strtolower(mb_substr($s, 1, null, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('vpc_compact_label_for_filter_option')) {
    /**
     * Compact face label for a filter option value (D14″).
     *
     * @param string $facet sign_language|edition|typology
     * @param array<int, array{value?: string, label?: string, short_label?: string}> $optionsList
     * @param string $value
     * @param string $fallback
     * @return string
     */
    function vpc_compact_label_for_filter_option($facet, array $optionsList, $value, $fallback = '') {
        $value = (string) $value;
        if ($value === '') {
            return $fallback;
        }
        $fullLabel = vpc_label_for_filter_option($optionsList, $value, $value);
        foreach ($optionsList as $opt) {
            if (!is_array($opt) || !isset($opt['value'])) {
                continue;
            }
            if ((string) $opt['value'] === $value) {
                if (!empty($opt['short_label']) && is_string($opt['short_label'])) {
                    return (string) $opt['short_label'];
                }
                break;
            }
        }
        if ($facet === 'sign_language') {
            $parts = preg_split('/\s+/', $fullLabel, 2);
            $firstToken = isset($parts[0]) ? $parts[0] : '';
            return $firstToken !== '' ? $firstToken : $value;
        }
        if ($facet === 'edition') {
            $stripped = preg_replace('/^\d{4}\s+/', '', $fullLabel);
            $stripped = preg_replace('/\s+\d{4}$/', '', $stripped);
            return $stripped !== '' ? $stripped : $value;
        }
        if ($facet === 'typology') {
            $tc = vpc_title_case_facet_label($fullLabel);
            if ($tc !== '') {
                return $tc;
            }
            $tcValue = vpc_title_case_facet_label($value);
            return $tcValue !== '' ? $tcValue : $value;
        }
        return $fullLabel !== '' ? $fullLabel : $value;
    }
}

if (!isset($vpc) || !is_array($vpc)) {
    trigger_error('$vpc array is required before including vimeo_caption_player.php', E_USER_WARNING);
    return;
}

$idBase = isset($vpc['instance_id']) ? $vpc['instance_id'] : null;
$idBase = $idBase !== null && $idBase !== ''
    ? preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $idBase)
    : 'vpc-' . str_replace('.', '', uniqid('', true));
$idBase = $idBase !== '' ? $idBase : 'vpc';

$firstCaptionTracks = array();
$playlistNormalized = vpc_normalize_vimeo_caption_player_playlist($vpc, $firstCaptionTracks);
if (count($playlistNormalized) === 0) {
    trigger_error('$vpc requires embed_url/video_id or a non-empty valid playlist', E_USER_WARNING);
    return;
}

$captionTracks = $firstCaptionTracks;

$captionsHeading = isset($vpc['captions_heading']) ? (string) $vpc['captions_heading'] : 'Captions';
$iframeTitle     = isset($vpc['iframe_title']) ? (string) $vpc['iframe_title'] : 'Vimeo';
$captionsBase    = isset($vpc['captions_endpoint'])
    ? (string) $vpc['captions_endpoint']
    : '/preview/captions-static.php';

$iframeId       = $idBase . '__iframe';
$captionBoxId   = $idBase . '__captions';
$headingId      = $idBase . '__caption-heading';
$transportId    = $idBase . '__transport';

$wrapperClass   = 'preview-vimeo-player-root';

$defaultParams = array(
    'api'      => '1',
    'title'    => '0',
    'byline'   => '0',
    'portrait' => '0',
    'dnt'      => '1',
    'controls' => '0',
    'autoplay'    => '0',
    'preload'     => 'auto',
    'playsinline' => '1',
);

$firstEntry = $playlistNormalized[0];
$firstExtraParams = isset($firstEntry['embed_params']) && is_array($firstEntry['embed_params'])
    ? $firstEntry['embed_params']
    : array();
$fallbackExtra = isset($vpc['embed_params']) && is_array($vpc['embed_params'])
    ? $vpc['embed_params']
    : array();
$mergedFirstParams = array_merge($fallbackExtra, $firstExtraParams);

if (!empty($firstEntry['embed_url'])) {
    $embedSrc = vpc_merge_vimeo_embed_query($firstEntry['embed_url'], $defaultParams, $mergedFirstParams);
} elseif ($firstEntry['videoId'] !== '') {
    $merged = array_merge($defaultParams, $mergedFirstParams);
    $embedSrc = 'https://player.vimeo.com/video/' . rawurlencode($firstEntry['videoId'])
        . '?' . http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
} else {
    trigger_error('$vpc playlist first item lacks a playable Vimeo id', E_USER_WARNING);
    return;
}

$playlistForJson = array();
foreach ($playlistNormalized as $pe) {
    $plTracks    = isset($pe['caption_tracks']) && is_array($pe['caption_tracks']) ? $pe['caption_tracks'] : array();
    $slOut       = isset($pe['sign_language'])  && is_string($pe['sign_language'])  ? $pe['sign_language']  : '';
    $edOut       = isset($pe['edition'])        && is_string($pe['edition'])        ? $pe['edition']        : '';
    $tyOut       = isset($pe['typology'])       && is_string($pe['typology'])       ? $pe['typology']       : '';
    $ptOut       = isset($pe['participant'])    && is_string($pe['participant'])    ? $pe['participant']    : '';
    $thumbOut    = isset($pe['thumbnail_url'])   && is_string($pe['thumbnail_url'])   ? $pe['thumbnail_url']   : '';
    $tagsOut     = isset($pe['tags']) && is_array($pe['tags']) ? array_values($pe['tags']) : array();
    $eParams     = isset($pe['embed_params']) && is_array($pe['embed_params']) ? $pe['embed_params'] : array();
    $embedOut    = '';
    if (!empty($pe['embed_url']) && is_string($pe['embed_url'])) {
        $embedOut = vpc_merge_vimeo_embed_query($pe['embed_url'], $defaultParams, array_merge($fallbackExtra, $eParams));
    }
    $row = array(
        'videoId'      => $pe['videoId'],
        'tracks'       => $plTracks,
        'signLanguage' => $slOut,
        'edition'      => $edOut,
        'typology'     => $tyOut,
        'participant'  => $ptOut,
        'tags'         => $tagsOut,
    );
    if ($embedOut !== '') {
        $row['embedUrl'] = $embedOut;
    }
    if ($thumbOut !== '') {
        $row['thumbnailUrl'] = vpc_participant_thumbnail_display_url($thumbOut);
    }
    $playlistForJson[] = $row;
}

$catalogForJson = null;
if (isset($vpc['catalog_playlist']) && is_array($vpc['catalog_playlist']) && count($vpc['catalog_playlist']) > 0) {
    $catalogFirstTracks = array();
    $catalogNormalized = vpc_normalize_vimeo_caption_player_playlist(
        array('playlist' => $vpc['catalog_playlist']),
        $catalogFirstTracks
    );
    if (count($catalogNormalized) > 0) {
        $catalogForJson = array();
        foreach ($catalogNormalized as $pe) {
            $plTracks = isset($pe['caption_tracks']) && is_array($pe['caption_tracks']) ? $pe['caption_tracks'] : array();
            $eParams  = isset($pe['embed_params']) && is_array($pe['embed_params']) ? $pe['embed_params'] : array();
            $embedOut = '';
            if (!empty($pe['embed_url']) && is_string($pe['embed_url'])) {
                $embedOut = vpc_merge_vimeo_embed_query($pe['embed_url'], $defaultParams, array_merge($fallbackExtra, $eParams));
            }
            $row = array(
                'videoId'      => $pe['videoId'],
                'tracks'       => $plTracks,
                'signLanguage' => isset($pe['sign_language']) ? $pe['sign_language'] : '',
                'edition'      => isset($pe['edition']) ? $pe['edition'] : '',
                'typology'     => isset($pe['typology']) ? $pe['typology'] : '',
                'participant'  => isset($pe['participant']) ? $pe['participant'] : '',
                'tags'         => isset($pe['tags']) && is_array($pe['tags']) ? array_values($pe['tags']) : array(),
            );
            if ($embedOut !== '') {
                $row['embedUrl'] = $embedOut;
            }
            $thumbOut = isset($pe['thumbnail_url']) && is_string($pe['thumbnail_url']) ? $pe['thumbnail_url'] : '';
            if ($thumbOut !== '') {
                $row['thumbnailUrl'] = vpc_participant_thumbnail_display_url($thumbOut);
            }
            $catalogForJson[] = $row;
        }
    }
}

// ── R2 filter row: Sign language custom picker (D15, D17) ──────────────────────
// Options come from vpc['sign_language_filter']['options'] (populated-from-present, D17).
$signLangOptionsList = isset($vpc['sign_language_filter']['options']) && is_array($vpc['sign_language_filter']['options'])
    ? $vpc['sign_language_filter']['options']
    : array();
$useSignLanguageFilter = count($signLangOptionsList) > 0;

// Subtitle language labels for JS track selection (website lang → subtitle track on load).
$subtitleLanguagesList = isset($vpc['subtitle_languages']) && is_array($vpc['subtitle_languages'])
    ? $vpc['subtitle_languages']
    : array();

// ── R2 filter row: City/Edition custom picker (D15, D17) ───────────────────────
$editionOptionsList = isset($vpc['edition_filter']['options']) && is_array($vpc['edition_filter']['options'])
    ? $vpc['edition_filter']['options']
    : array();
$useEditionFilter = count($editionOptionsList) > 0;

// ── R2 filter row: Typology custom picker (D15, D17) ──────────────────────────
$typologyOptionsList = isset($vpc['typology_filter']['options']) && is_array($vpc['typology_filter']['options'])
    ? $vpc['typology_filter']['options']
    : array();
$useTypologyFilter = count($typologyOptionsList) > 0;

$captionPickerDynamic = false;

$showR2FilterRow = $useSignLanguageFilter || $useEditionFilter || $useTypologyFilter;

// Prepare sign-language picker config for JS (D18 composable filter state).
$signLangFilterForConfig = $useSignLanguageFilter
    ? array('options' => $signLangOptionsList)
    : null;

$editionFilterForConfig = $useEditionFilter
    ? array('options' => $editionOptionsList)
    : null;

$typologyFilterForConfig = $useTypologyFilter
    ? array('options' => $typologyOptionsList)
    : null;

// Prepare subtitle language labels for JS (D16′ spoken-language mapping).
$subtitleLanguagesForConfig = count($subtitleLanguagesList) > 0
    ? $subtitleLanguagesList
    : null;

// playlist_index from $vpc lets the caller specify which item is the initial poster.
// When the server has already shuffled (D12), this is always 0.
$initialPlaylistIndex = isset($vpc['playlist_index']) ? (int) $vpc['playlist_index'] : 0;
$initialPlaylistIndex = max(0, min($initialPlaylistIndex, count($playlistNormalized) - 1));

$initialEntry = $playlistNormalized[$initialPlaylistIndex];
$initialPosterUrl = isset($initialEntry['thumbnail_url']) && is_string($initialEntry['thumbnail_url'])
    ? vpc_participant_thumbnail_display_url($initialEntry['thumbnail_url'])
    : '';
$initialSignLangReadout = vpc_compact_label_for_filter_option(
    'sign_language',
    $signLangOptionsList,
    isset($initialEntry['sign_language']) ? $initialEntry['sign_language'] : '',
    preview_t('player.filter.sign_language')
);
$initialEditionReadout = vpc_compact_label_for_filter_option(
    'edition',
    $editionOptionsList,
    isset($initialEntry['edition']) ? $initialEntry['edition'] : '',
    preview_t('player.filter.city_edition')
);
$initialTypologyReadout = vpc_compact_label_for_filter_option(
    'typology',
    $typologyOptionsList,
    isset($initialEntry['typology']) ? $initialEntry['typology'] : '',
    preview_t('player.filter.typology')
);

$config = array(
    'iframeId'             => $iframeId,
    'captionBoxId'         => $captionBoxId,
    'tracks'               => $captionTracks,
    'captionsEndpoint'     => $captionsBase,
    'playlist'             => $playlistForJson,
    'catalogPlaylist'      => $catalogForJson,
    'playlistIndex'        => $initialPlaylistIndex,
    // When true the server has pre-shuffled; JS must trust item[0] as the queue head
    // without re-shuffling, so the paused poster matches what Play will continue (D12).
    'serverShuffled'       => true,
    'captionPickerDynamic' => $captionPickerDynamic,
    // R2 filter pickers config (D17, D18). signLanguageFilter carries options (value+label)
    // populated only from values present in the visible catalog.
    'signLanguageFilter'   => $signLangFilterForConfig,
    'editionFilter'        => $editionFilterForConfig,
    'typologyFilter'       => $typologyFilterForConfig,
    // D16′: studio-config subtitle_languages for track lang → label mapping.
    'subtitleLanguages'    => $subtitleLanguagesForConfig,
    // Website language drives initial subtitle track selection (issue #19).
    'initialSubtitleLang'  => isset($preview_lang) ? (string) $preview_lang : 'en',
    // D18: Participant mode — non-empty when a participant playlist is active.
    'participantName' => isset($vpc['participant_name']) ? (string)$vpc['participant_name'] : '',
    // Localized chrome strings for JS (player.* keys).
    'strings' => (isset($preview_i18n) && $preview_i18n instanceof PreviewI18n)
        ? $preview_i18n->chromeMap()
        : array(),
);

$configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if ($configJson === false) {
    trigger_error('vimeo_caption_player: json_encode failed for config', E_USER_WARNING);
    return;
}

// Prev/next transport always visible; disabled state toggled by JS from playlist count.
$singleVideoPlaylist = count($playlistNormalized) <= 1;
$navHiddenClass = '';
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
<script type="application/json" class="vpc-config"><?php echo $configJson; ?></script>

    <div class="vpc-media-stage">
        <div class="video-stack">
            <div id="<?php echo htmlspecialchars($captionBoxId, ENT_QUOTES, 'UTF-8'); ?>" class="caption-box"></div>
            <div class="video-shell">
                <?php if ($initialPosterUrl !== ''): ?>
                <img
                    class="vpc-poster-cover"
                    src="<?php echo htmlspecialchars($initialPosterUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    alt=""
                    aria-hidden="true"
                    draggable="false">
                <?php endif; ?>
                <iframe
                    id="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
                    src="<?php echo htmlspecialchars($embedSrc, ENT_QUOTES, 'UTF-8'); ?>"
                    title="<?php echo htmlspecialchars($iframeTitle, ENT_QUOTES, 'UTF-8'); ?>"
                    allow="autoplay; fullscreen; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen></iframe>
                <button
                    type="button"
                    class="vpc-video-hitarea"
                    tabindex="-1"
                    aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?= htmlspecialchars(preview_t('player.transport.play_or_pause'), ENT_QUOTES, 'UTF-8') ?>"
                ></button>
            </div>
        </div>
    </div>
    <?php
    $bottomBarActiveCollections = array();
    $participantNavName = isset($vpc['participant_name']) ? trim((string) $vpc['participant_name']) : '';
    if ($participantNavName !== '') {
        $bottomBarActiveCollections['participants'] = $participantNavName;
    }
    $bottomBar = array(
        'mode' => 'player',
        'current_route' => 'home',
        'lang' => isset($preview_lang) ? (string) $preview_lang : 'en',
        'active_collections' => $bottomBarActiveCollections,
        'player' => array(
            'transport_id' => $transportId,
            'iframe_id' => $iframeId,
            'nav_hidden_class' => $navHiddenClass,
            'transport_prev_disabled' => $singleVideoPlaylist,
            'transport_next_disabled' => $singleVideoPlaylist,
            'show_r2_filter_row' => $showR2FilterRow,
            'sign_lang_picker_id' => $idBase . '__sign-lang-picker',
            'sign_lang_dropdown_id' => $idBase . '__sign-lang-dropdown',
            'sign_lang_picker_btn_id' => $idBase . '__sign-lang-btn',
            'edition_picker_id' => $idBase . '__edition-picker',
            'edition_dropdown_id' => $idBase . '__edition-dropdown',
            'edition_picker_btn_id' => $idBase . '__edition-btn',
            'typology_picker_id' => $idBase . '__typology-picker',
            'typology_dropdown_id' => $idBase . '__typology-dropdown',
            'typology_picker_btn_id' => $idBase . '__typology-btn',
            'use_sign_language_filter' => $useSignLanguageFilter,
            'use_edition_filter' => $useEditionFilter,
            'use_typology_filter' => $useTypologyFilter,
            'sign_lang_options' => $signLangOptionsList,
            'edition_options' => $editionOptionsList,
            'typology_options' => $typologyOptionsList,
            'initial_sign_lang_readout' => $initialSignLangReadout,
            'initial_edition_readout' => $initialEditionReadout,
            'initial_typology_readout' => $initialTypologyReadout,
            'deaf_hearing_enabled' => !empty($vpc['deaf_hearing_enabled']),
        ),
    );
    include dirname(__DIR__) . '/components/bottom_bar.php';
    ?>
</div>
