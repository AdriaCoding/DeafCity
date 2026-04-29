<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Develop</title>
    <style>
        html, body {
            margin: 0;
            min-height: 100%;
            background: #0a0a0a;
        }
        .video-shell {
            max-width: min(100vw, 1280px);
            margin: 0 auto;
            aspect-ratio: 16 / 9;
            background: #000;
        }
        .video-shell iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            vertical-align: top;
        }
    </style>
</head>
<body>
<?php
$videoId = '38DSoHOO8u4';
$params = http_build_query([
    'modestbranding' => '1',
    'rel' => '0',
    'iv_load_policy' => '3',
    'playsinline' => '1',
    'color' => 'white',
]);
$embedSrc = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId) . '?' . $params;
?>
    <div class="video-shell">
        <iframe
            src="<?php echo htmlspecialchars($embedSrc, ENT_QUOTES, 'UTF-8'); ?>"
            title="Video"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
            loading="lazy"></iframe>
    </div>
</body>
</html>
