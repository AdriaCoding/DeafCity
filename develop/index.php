<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Develop</title>
    <link rel="stylesheet" href="/develop/components/vimeo_caption_player.css?v=8">
    <style>
        html, body {
            margin: 0;
            min-height: 100%;
            background: #fff;
        }
        .develop-block {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>

<?php
// YouTube (disabled). Change to `if (true):` and uncomment the JS block marked `YOUTUBE RESCUE` in a page script below.
if (false):
    $videoIdMinimal = '38DSoHOO8u4';
    $base = [
        'rel'            => '0',
        'iv_load_policy' => '3',
        'playsinline'    => '1',
        'color'          => 'white',
        'enablejsapi'    => '1',
        'origin'         => 'https://deaf.city',
    ];
    $paramsMinimal = http_build_query(array_merge($base, [
        'controls' => '0',
        'fs'       => '0',
    ]));
    $embedMinimal = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoIdMinimal) . '?' . $paramsMinimal;
?>
    <div class="develop-block">
        <div id="caption-box" class="caption-box"></div>
        <div class="video-shell">
            <iframe
                id="yt-player"
                src="<?php echo htmlspecialchars($embedMinimal, ENT_QUOTES, 'UTF-8'); ?>"
                title="Video (minimal chrome)"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; web-share"
                referrerpolicy="strict-origin-when-cross-origin"
                allowfullscreen></iframe>
        </div>
    </div>
<?php endif; ?>

<?php
require __DIR__ . '/lib/videos_catalog.php';

$videosJsonPath = dirname(__DIR__) . '/data/videos.json';
$playlistsJsonPath = dirname(__DIR__) . '/data/playlists.json';
$catalog = vpc_load_videos_catalog($videosJsonPath);
$playlist = $catalog ? vpc_vimeo_playlist_all_from_catalog($catalog) : array();

$signLanguageOptions = vpc_sign_language_options_from_playlists_json($playlistsJsonPath);
$defaultSignLanguage = isset($signLanguageOptions[0]['value'])
    ? (string) $signLanguageOptions[0]['value']
    : '';

$vpc = null;
if (count($playlist) > 0) {
    $vpc = array(
        'instance_id' => 'develop-playlist-demo',
        'playlist'    => $playlist,
    );
    if (count($signLanguageOptions) > 0) {
        $vpc['sign_language_filter'] = array(
            'options' => $signLanguageOptions,
            'default' => $defaultSignLanguage,
        );
    }
}
?>

    <div class="develop-block">
        <?php if ($vpc !== null): ?>
            <?php require __DIR__ . '/components/vimeo_caption_player.php'; ?>
        <?php else: ?>
            <p style="font-family: sans-serif; padding: 1rem;">
                No playlist entries loaded. Check that <code>data/videos.json</code> exists and the ordered catalog ids match entries in the <code>videos</code> array.
            </p>
        <?php endif; ?>
    </div>
<script src="/develop/js/vimeo_caption_player.js?v=8" defer></script>
</body>
</html>
