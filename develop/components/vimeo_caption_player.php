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
 *     'captions_endpoint' => '/develop/captions-static.php',
 *
 *     // Optional ordered playlist (each entry: video_id OR embed_url; optional caption_tracks, embed_params).
 *     // Omit to use single video_id / embed_url at the top level. Prev/Next appear when length > 1.
 *     // Canonical metadata: data/videos.json plus data/captions/*.vtt (videos_catalog.php).
 *     //
 *     // Optional: sign-language filter (reads option labels from data/playlists.json titles).
 *     // Renders a picker under the transport row; client filters playlist by item sign_language.
 *     'sign_language_filter' => array(
 *       'options' => array( array('value' => 'LIBRAS Brazilian Sign Language', 'label' => '...'), ),
 *       'default' => 'LIBRAS Brazilian Sign Language',
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

                $out[] = array(
                    'videoId' => $digits,
                    'caption_tracks' => $ct,
                    'embed_url' => $entryEmbed,
                    'embed_params' => $eParams,
                    'sign_language' => $signLangMeta,
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
            $firstCaptionTracks = $legacyTracks;
            return array(array(
                'videoId' => $digitsLegacy,
                'caption_tracks' => $legacyTracks,
                'embed_url' => $legacyEmbed,
                'embed_params' => $extraParams,
                'sign_language' => $legacySl,
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
    : '/develop/captions-static.php';

$iframeId       = $idBase . '__iframe';
$captionBoxId   = $idBase . '__captions';
$headingId      = $idBase . '__caption-heading';
$transportId    = $idBase . '__transport';

$wrapperClass   = 'develop-vimeo-player-root';

$defaultParams = array(
    'api'      => '1',
    'title'    => '0',
    'byline'   => '0',
    'portrait' => '0',
    'dnt'      => '1',
    'controls' => '0',
    'autoplay' => '1',
    // Most browsers block audible autoplay; keep muted unless overridden via embed_params.
    'muted'    => '1',
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
    $plTracks = isset($pe['caption_tracks']) && is_array($pe['caption_tracks']) ? $pe['caption_tracks'] : array();
    $slOut      = isset($pe['sign_language']) && is_string($pe['sign_language']) ? $pe['sign_language'] : '';
    $playlistForJson[] = array(
        'videoId'      => $pe['videoId'],
        'tracks'       => $plTracks,
        'signLanguage' => $slOut,
    );
}

$signLanguageFilterCfg = null;
$captionPickerDynamic   = false;
if (isset($vpc['sign_language_filter']) && is_array($vpc['sign_language_filter'])) {
    $optRaw = isset($vpc['sign_language_filter']['options']) ? $vpc['sign_language_filter']['options'] : null;
    if (is_array($optRaw) && count($optRaw) > 0) {
        $captionPickerDynamic = true;
        $def = isset($vpc['sign_language_filter']['default'])
            ? (string) $vpc['sign_language_filter']['default']
            : '';
        $signLanguageFilterCfg = array(
            'options' => $optRaw,
            'default' => $def,
        );
    }
}

$signLangOptionsList = isset($vpc['sign_language_filter']['options']) && is_array($vpc['sign_language_filter']['options'])
    ? $vpc['sign_language_filter']['options']
    : array();
$useSignLanguageFilter = count($signLangOptionsList) > 0;
$signLangDefault = '';
if ($useSignLanguageFilter) {
    if (isset($vpc['sign_language_filter']['default'])) {
        $signLangDefault = (string) $vpc['sign_language_filter']['default'];
    }
    if ($signLangDefault === '' && isset($signLangOptionsList[0]['value'])) {
        $signLangDefault = (string) $signLangOptionsList[0]['value'];
    }
}
$signLangSelectId = $idBase . '__sign-language-select';

$config = array(
    'iframeId'             => $iframeId,
    'captionBoxId'         => $captionBoxId,
    'tracks'               => $captionTracks,
    'captionsEndpoint'     => $captionsBase,
    'playlist'             => $playlistForJson,
    'playlistIndex'        => 0,
    'captionPickerDynamic' => $captionPickerDynamic,
    'signLanguageFilter'   => $signLanguageFilterCfg,
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

<?php if ($useSignLanguageFilter): ?>
    <div
        id="<?php echo htmlspecialchars($idBase . '__caption-picker', ENT_QUOTES, 'UTF-8'); ?>"
        class="caption-lang-picker vpc-caption-lang-dynamic vpc-caption-picker-hidden"
        role="group"
        aria-label="<?php echo htmlspecialchars($captionsHeading, ENT_QUOTES, 'UTF-8'); ?>"
    >
        <span class="caption-lang-picker-label" id="<?php echo htmlspecialchars($headingId, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($captionsHeading, ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <span class="vpc-caption-dynamic-btns" aria-live="polite"></span>
    </div>
<?php elseif (count($captionTracks) > 0): ?>
    <div class="caption-lang-picker" role="group" aria-label="<?php echo htmlspecialchars($captionsHeading, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="caption-lang-picker-label" id="<?php echo htmlspecialchars($headingId, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($captionsHeading, ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <?php foreach ($captionTracks as $i => $track): ?>
        <button
            type="button"
            class="caption-lang-btn"
            data-track-index="<?php echo (int) $i; ?>"
            aria-pressed="<?php echo $i === 0 ? 'true' : 'false'; ?>"
            aria-controls="<?php echo htmlspecialchars($captionBoxId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-describedby="<?php echo htmlspecialchars($headingId, ENT_QUOTES, 'UTF-8'); ?>"
        ><?php echo htmlspecialchars($track['label'], ENT_QUOTES, 'UTF-8'); ?></button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
            aria-hidden="true"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
        ></button>
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
            aria-pressed="false"
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
        <button
            type="button"
            class="vpc-reset-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Restart video from the beginning"
        ><span class="material-icons" aria-hidden="true">replay</span></button>
        <?php if ($showPlaylistNav): ?>
        <button
            type="button"
            class="vpc-next-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Next video in playlist"
        ><span class="material-icons" aria-hidden="true">skip_next</span></button>
        <?php endif; ?>
    </div>
    <?php if ($useSignLanguageFilter): ?>
    <div class="vpc-sign-language" role="group" aria-label="Sign language">
        <label class="vpc-sign-language-label" for="<?php echo htmlspecialchars($signLangSelectId, ENT_QUOTES, 'UTF-8'); ?>">Sign language</label>
        <select id="<?php echo htmlspecialchars($signLangSelectId, ENT_QUOTES, 'UTF-8'); ?>" class="vpc-sign-lang-select" autocomplete="off">
            <?php foreach ($signLangOptionsList as $opt): ?>
                <?php if (!isset($opt['value'], $opt['label'])) continue; ?>
            <option
                value="<?php echo htmlspecialchars((string) $opt['value'], ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo (string) $opt['value'] === $signLangDefault ? ' selected' : ''; ?>
            ><?php echo htmlspecialchars((string) $opt['label'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</div>
