<!DOCTYPE html>
<html lang="en"<?php if (defined('SQUARE') && SQUARE) echo ' class="is-square"';?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php include(__DIR__.'/about/page-title.txt'); ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('css/reset.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/deafcity.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/about.css'); ?>">
    <link rel="stylesheet" href="leaflet/leaflet.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <?php echo $content; ?>
</body>
</html>
