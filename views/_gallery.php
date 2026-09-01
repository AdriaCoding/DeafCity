<?php

if (!function_exists('preview_t')) {
    require_once dirname(__DIR__) . '/lib/preview_locale.php';
}

foreach ($gallery_images as $i => $image) {
    $captionKey = sprintf('gallery.caption.%02d', $i + 1);
    $caption = preview_t($captionKey);
    echo '<div class="gallery-image' . ($i == 0 ? ' current' : '') . '">' . "\n";
    echo '  <img src="' . $image['image'] . '">' . "\n";
    echo '  <span class="caption">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</span>' . "\n";
    echo "</div>\n";
}
?>
<div class="gallery-controls">
    <div class="prev"><img src="/img/previous.svg?v=9" width="30"></div>
    <div class="next"><img src="/img/next.svg?v=9" height="30"></div>
</div>
