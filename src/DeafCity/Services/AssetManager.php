<?php

namespace DeafCity\Services;

class AssetManager {
    public function getUrl($file) {
        $url = $file;
        if (file_exists($file = ROOTDIR . '/' . $file)) {
            $url .= '?v=' . md5_file($file);
        }
        return $url;
    }
}
