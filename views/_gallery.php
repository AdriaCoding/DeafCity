<?php

foreach ($gallery_images as $i => $image) {
    echo '<div class="gallery-image' . ($i == 0 ? ' current' : '') . '">' . "\n";
    echo '  <img src="' . $image['image'] . '">' . "\n";
    echo '  <span class="caption">' . htmlspecialchars($image['caption']) . '</span>' . "\n";
    echo "</div>\n";
}
?>
<div class="gallery-controls">
    <div class="prev"><img src="/img/previous.svg?v=9" width="30"></div>
    <div class="next"><img src="/img/next.svg?v=9" height="30"></div>
</div>
