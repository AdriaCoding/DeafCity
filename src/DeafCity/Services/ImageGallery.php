<?php

namespace DeafCity\Services;

class ImageGallery {
    const FILENAME = 'gallery.json';
    
    public function getFilePath() {
        return ROOTDIR . '/data/' . self::FILENAME;
    }
    
    public function getImages() {
        $images = @ json_decode(file_get_contents($this->getFilePath()), true);
        if (!$images) return [];
        foreach ($images as &$image) {
            $image['image'] = $this->getImagesPath() . '/' . $image['image'];
        }
        
        return $images;
    }
    
    public function getImagesPath() {
        return '/gallery';
    }
}