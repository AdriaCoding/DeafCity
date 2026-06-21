<?php

function preview_load_gallery_images($galleryJsonPath)
{
    if (!is_file($galleryJsonPath)) {
        return array();
    }

    $raw = file_get_contents($galleryJsonPath);
    $images = json_decode($raw, true);
    if (!is_array($images)) {
        return array();
    }

    foreach ($images as &$image) {
        if (isset($image['image'])) {
            $image['image'] = '/gallery/' . $image['image'];
        }
    }
    unset($image);

    return $images;
}
