<?php
for ($i = 1; $i <= 30; $i++) {
    $key = 'about.credits.p' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $html = preview_t($key);
    if ($html === $key) {
        break;
    }
    echo '<p>' . $html . '</p>';
}
