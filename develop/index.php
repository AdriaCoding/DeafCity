<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Develop</title>
    <link rel="stylesheet" href="/develop/components/vimeo_caption_player.css?v=6">
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
// Vimeo caption player props: use `embed_url` instead of `video_id` + embed_params when you already have the iframe src.
$vpc = array(
    'instance_id'   => 'develop-luis02',
    'playlist'      => array(
        array(
            'video_id'       => '639494119',
            'caption_tracks' => array(
                array('file' => 'luis_02.es-MX.vtt', 'label' => 'Español (México)'),
                array('file' => 'luis_02.en.vtt', 'label' => 'English'),
                array('file' => 'luis_02.it.vtt', 'label' => 'Italiano'),
            ),
        ),
        array(
            'embed_url' => 'https://vimeo.com/1128906791?fl=tl&fe=ec',
        ),
    ),
);
?>

    <div class="develop-block">
        <?php require __DIR__ . '/components/vimeo_caption_player.php'; ?>
    </div>
<script src="/develop/js/vimeo_caption_player.js?v=6" defer></script>
</body>
</html>
