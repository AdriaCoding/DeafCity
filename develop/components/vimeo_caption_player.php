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
 *   );
 */

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

$captionTracks = array();
if (isset($vpc['caption_tracks']) && is_array($vpc['caption_tracks'])) {
    foreach ($vpc['caption_tracks'] as $track) {
        if (!is_array($track) || empty($track['file']) || !isset($track['label'])) {
            continue;
        }
        $captionTracks[] = array(
            'file' => $track['file'],
            'label' => $track['label'],
        );
    }
}

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

$embedSrc = '';
$extraParams = isset($vpc['embed_params']) && is_array($vpc['embed_params'])
    ? $vpc['embed_params']
    : array();

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

if (!empty($vpc['embed_url'])) {
    $embedSrc = vpc_merge_vimeo_embed_query($vpc['embed_url'], $defaultParams, $extraParams);
} elseif (!empty($vpc['video_id'])) {
    $merged = array_merge($defaultParams, $extraParams);
    $embedSrc = 'https://player.vimeo.com/video/' . rawurlencode((string) $vpc['video_id'])
        . '?' . http_build_query($merged, '', '&', PHP_QUERY_RFC3986);
} else {
    trigger_error('$vpc requires either embed_url or video_id', E_USER_WARNING);
    return;
}

$config = array(
    'iframeId'         => $iframeId,
    'captionBoxId'     => $captionBoxId,
    'tracks'           => $captionTracks,
    'captionsEndpoint' => $captionsBase,
);
$configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if ($configJson === false) {
    trigger_error('vimeo_caption_player: json_encode failed for config', E_USER_WARNING);
    return;
}
?>
<div class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
<script type="application/json" class="vpc-config"><?php echo $configJson; ?></script>

<?php if (count($captionTracks) > 0): ?>
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
        <button
            type="button"
            class="vpc-play-pause-btn"
            aria-controls="<?php echo htmlspecialchars($iframeId, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="Play video"
        >Play</button>
    </div>
</div>
