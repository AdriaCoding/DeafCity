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
                if ($digits === '' && !empty($entry['embed_url'])) {
                    $entryEmbed = (string) $entry['embed_url'];
                    $digits = vpc_vimeo_digits_from_public_url($entryEmbed);
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
                        $ct[] = array(
                            'file' => $track['file'],
                            'label' => $track['label'],
                        );
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

                $out[] = array(
                    'videoId' => $digits,
                    'caption_tracks' => $ct,
                    'embed_url' => $entryEmbed,
                    'embed_params' => $eParams,
                    'sign_language' => $signLangMeta,
                    'edition' => $editionMeta,
                    'typology' => $typologyMeta,
                    'participant' => $participantMeta,
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
                $legacyTracks[] = array(
                    'file' => $track['file'],
                    'label' => $track['label'],
                );
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
    $playlistForJson[] = array(
        'videoId'      => $pe['videoId'],
        'tracks'       => $plTracks,
        'signLanguage' => $slOut,
        'edition'      => $edOut,
        'typology'     => $tyOut,
        'participant'  => $ptOut,
    );
}

// ── R2 filter row: Sign language custom picker (D15, D17) ──────────────────────
// Options come from vpc['sign_language_filter']['options'] (populated-from-present, D17).
$signLangOptionsList = isset($vpc['sign_language_filter']['options']) && is_array($vpc['sign_language_filter']['options'])
    ? $vpc['sign_language_filter']['options']
    : array();
$useSignLanguageFilter = count($signLangOptionsList) > 0;

// ── R2 Spoken Language track selector (D16) ────────────────────────────────────
// NOT a filter — swaps the subtitle track of the current video only, does not
// re-queue the playlist. Sits in R2 as the second picker (after Sign language,
// before City/Edition) per tasks.md §1.3 order: Sign · Spoken · City · Typology.
// Options are distinct caption track labels across all visible catalog videos.
// Omitted entirely when no catalog video has caption tracks.
$spokenLangOptionsList = isset($vpc['spoken_language_options']) && is_array($vpc['spoken_language_options'])
    ? $vpc['spoken_language_options']
    : array();
$useSpokenLanguagePicker = count($spokenLangOptionsList) > 0;

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

// R2 row shows when there is at least one filter picker or the spoken language selector.
$showR2FilterRow = $useSignLanguageFilter || $useSpokenLanguagePicker || $useEditionFilter || $useTypologyFilter;

// Prepare sign-language picker config for JS (D18 composable filter state).
$signLangFilterForConfig = $useSignLanguageFilter
    ? array('options' => $signLangOptionsList)
    : null;

// Prepare spoken language options for JS (D16 track selector — not filterState).
$spokenLangForConfig = $useSpokenLanguagePicker
    ? $spokenLangOptionsList
    : null;

// playlist_index from $vpc lets the caller specify which item is the initial poster.
// When the server has already shuffled (D12), this is always 0.
$initialPlaylistIndex = isset($vpc['playlist_index']) ? (int) $vpc['playlist_index'] : 0;
$initialPlaylistIndex = max(0, min($initialPlaylistIndex, count($playlistNormalized) - 1));

$config = array(
    'iframeId'             => $iframeId,
    'captionBoxId'         => $captionBoxId,
    'tracks'               => $captionTracks,
    'captionsEndpoint'     => $captionsBase,
    'playlist'             => $playlistForJson,
    'playlistIndex'        => $initialPlaylistIndex,
    // When true the server has pre-shuffled; JS must trust item[0] as the queue head
    // without re-shuffling, so the paused poster matches what Play will continue (D12).
    'serverShuffled'       => true,
    'captionPickerDynamic' => $captionPickerDynamic,
    // R2 filter pickers config (D17, D18). signLanguageFilter carries options (value+label)
    // populated only from values present in the visible catalog.
    'signLanguageFilter'   => $signLangFilterForConfig,
    // R2 Spoken Language track selector options (D16). Array of {value, label} for all
    // distinct caption track labels in the catalog. JS uses stickyCaptionLabel to switch
    // tracks without touching filterState or the playlist queue.
    'spokenLanguageOptions' => $spokenLangForConfig,
);

$configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if ($configJson === false) {
    trigger_error('vimeo_caption_player: json_encode failed for config', E_USER_WARNING);
    return;
}

$showPlaylistNav = count($playlistNormalized) > 1;
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
<script type="application/json" class="vpc-config"><?php echo $configJson; ?></script>

    <div class="vpc-media-stage">
        <div class="video-stack">
            <div id="<?php echo htmlspecialchars($captionBoxId, ENT_QUOTES, 'UTF-8'); ?>" class="caption-box"></div>
            <div class="video-shell">
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
                    aria-label="Play or pause video"
                ></button>
            </div>
        </div>
    </div>
    <div
        id="<?php echo htmlspecialchars($transportId, ENT_QUOTES, 'UTF-8'); ?>"
        class="vpc-transport"
        role="group"
        aria-label="Playback"
    >
        <?php if ($showPlaylistNav): ?>
        <button
            type="button"
            class="vpc-shuffle-btn"
            aria-pressed="true"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Shuffle playlist"
        ><span class="material-icons" aria-hidden="true">shuffle</span></button>
        <button
            type="button"
            class="vpc-prev-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Previous video in playlist"
        ><span class="material-icons" aria-hidden="true">skip_previous</span></button>
        <?php endif; ?>
        <button
            type="button"
            class="vpc-play-pause-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Play video"
        ><span class="material-icons" aria-hidden="true">play_arrow</span></button>
        <?php if ($showPlaylistNav): ?>
        <button
            type="button"
            class="vpc-next-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Next video in playlist"
        ><span class="material-icons" aria-hidden="true">skip_next</span></button>
        <?php endif; ?>
        <button
            type="button"
            class="vpc-reset-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Restart video from the beginning"
        ><span class="material-icons" aria-hidden="true">replay</span></button>
    </div>
    <?php if ($showR2FilterRow): ?>
    <?php
    /*
     * R2 — Filter row (D15, D17, D18)
     * Order (tasks.md §1.3): Sign language · Spoken Language · City/Edition · Typology
     * Sign language, City/Edition, Typology: real playlist filters (AND composition, D18).
     * Spoken Language: track selector only — does NOT re-queue the playlist (D16).
     * Custom dropdown, NOT a native <select>. Brand green accents on active state.
     * Built to hold 4 pickers side-by-side (flex row, wraps on mobile per D20).
     */
    $signLangPickerId    = $idBase . '__sign-lang-picker';
    $signLangDropdownId  = $idBase . '__sign-lang-dropdown';
    $signLangPickerBtnId = $idBase . '__sign-lang-btn';

    $spokenLangPickerId    = $idBase . '__spoken-lang-picker';
    $spokenLangDropdownId  = $idBase . '__spoken-lang-dropdown';
    $spokenLangPickerBtnId = $idBase . '__spoken-lang-btn';

    $editionPickerId    = $idBase . '__edition-picker';
    $editionDropdownId  = $idBase . '__edition-dropdown';
    $editionPickerBtnId = $idBase . '__edition-btn';

    $typologyPickerId    = $idBase . '__typology-picker';
    $typologyDropdownId  = $idBase . '__typology-dropdown';
    $typologyPickerBtnId = $idBase . '__typology-btn';
    ?>
    <div class="vpc-r2-filters" role="group" aria-label="Filters">
        <?php if ($useSignLanguageFilter): ?>
        <div
            class="vpc-picker"
            id="<?php echo htmlspecialchars($signLangPickerId, ENT_QUOTES, 'UTF-8'); ?>"
            data-picker="sign_language"
        >
            <button
                type="button"
                id="<?php echo htmlspecialchars($signLangPickerBtnId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-btn"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="<?php echo htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                data-generic-label="Sign language"
            >Sign language</button>
            <ul
                role="listbox"
                id="<?php echo htmlspecialchars($signLangDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-dropdown"
                aria-label="Sign language"
                hidden
            >
                <li
                    role="option"
                    class="vpc-picker-option vpc-picker-clear"
                    data-value=""
                    aria-selected="true"
                >All sign languages</li>
                <?php foreach ($signLangOptionsList as $opt): ?>
                    <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                <li
                    role="option"
                    class="vpc-picker-option"
                    data-value="<?php echo htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                ><?php echo htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ($useSpokenLanguagePicker): ?>
        <?php
        /*
         * Spoken Language picker (D16).
         * data-picker="spoken_language" — JS identifies this picker by attribute.
         * Does NOT participate in filterState or applyFilterChange().
         * JS wires a separate handler that calls setActiveCaptionTrack() directly,
         * leaving the playlist queue untouched.
         */
        ?>
        <div
            class="vpc-picker"
            id="<?php echo htmlspecialchars($spokenLangPickerId, ENT_QUOTES, 'UTF-8'); ?>"
            data-picker="spoken_language"
        >
            <button
                type="button"
                id="<?php echo htmlspecialchars($spokenLangPickerBtnId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-btn"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="<?php echo htmlspecialchars($spokenLangDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                data-generic-label="Spoken Language"
            >Spoken Language</button>
            <ul
                role="listbox"
                id="<?php echo htmlspecialchars($spokenLangDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-dropdown"
                aria-label="Spoken Language"
                hidden
            >
                <li
                    role="option"
                    class="vpc-picker-option vpc-picker-clear"
                    data-value=""
                    aria-selected="true"
                >No subtitles</li>
                <?php foreach ($spokenLangOptionsList as $opt): ?>
                    <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                <li
                    role="option"
                    class="vpc-picker-option"
                    data-value="<?php echo htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                ><?php echo htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ($useEditionFilter): ?>
        <div
            class="vpc-picker"
            id="<?php echo htmlspecialchars($editionPickerId, ENT_QUOTES, 'UTF-8'); ?>"
            data-picker="edition"
        >
            <button
                type="button"
                id="<?php echo htmlspecialchars($editionPickerBtnId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-btn"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="<?php echo htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                data-generic-label="City / Edition"
            >City / Edition</button>
            <ul
                role="listbox"
                id="<?php echo htmlspecialchars($editionDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-dropdown"
                aria-label="City / Edition"
                hidden
            >
                <li
                    role="option"
                    class="vpc-picker-option vpc-picker-clear"
                    data-value=""
                    aria-selected="true"
                >All cities</li>
                <?php foreach ($editionOptionsList as $opt): ?>
                    <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                <li
                    role="option"
                    class="vpc-picker-option"
                    data-value="<?php echo htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                ><?php echo htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ($useTypologyFilter): ?>
        <div
            class="vpc-picker"
            id="<?php echo htmlspecialchars($typologyPickerId, ENT_QUOTES, 'UTF-8'); ?>"
            data-picker="typology"
        >
            <button
                type="button"
                id="<?php echo htmlspecialchars($typologyPickerBtnId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-btn"
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="<?php echo htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                data-generic-label="Typology"
            >Typology</button>
            <ul
                role="listbox"
                id="<?php echo htmlspecialchars($typologyDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
                class="vpc-picker-dropdown"
                aria-label="Typology"
                hidden
            >
                <li
                    role="option"
                    class="vpc-picker-option vpc-picker-clear"
                    data-value=""
                    aria-selected="true"
                >All typologies</li>
                <?php foreach ($typologyOptionsList as $opt): ?>
                    <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
                <li
                    role="option"
                    class="vpc-picker-option"
                    data-value="<?php echo htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                ><?php echo htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
    $siteNavRoute = isset($vpc['site_nav_route']) ? trim((string) $vpc['site_nav_route']) : '';
    if ($siteNavRoute !== ''):
        $currentRoute = $siteNavRoute;
        $navPlacement = 'chrome';
    ?>
    <div class="vpc-site-nav-wrap">
        <?php include dirname(__DIR__) . '/components/site_nav.php'; ?>
    </div>
    <?php endif; ?>
</div>
