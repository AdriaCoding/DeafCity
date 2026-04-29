<?php

namespace DeafCity;

use \DeafCity\Services\AssetManager;
use DeafCity\Services\ImageGallery;
use \DeafCity\Services\VideoCache;
use \DeafCity\Services\VideoDB;

use \DeafCity\Controllers\Home;

class App {
    
    protected static $instance = false;
    
    protected static $serviceMap = [
    
    ];
    
    protected $services = [];
    
    public static function get() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }
    
    public function getService($class) {
        // TODO map
    
        if (!isset($this->services[$class])) {
            $this->services[$class] = new $class();
        }
        
        return $this->services[$class];
    }
    
    public function getAssetManager() {
        return $this->getService(AssetManager::class);
    }
    public function getVideoCache() {
        return $this->getService(VideoCache::class);
    }
    public function getVideoDB() {
        return $this->getService(VideoDB::class);
    }
    public function getImageGallery() {
        return $this->getService(ImageGallery::class);
    }
    
    public function run($controller = Home::class, $action='index') {
        $controller = new $controller($this);
        $controller->$action();
    }
    
    public function bestSubtitles($options) {
        if (defined('FORCE_SUBTITLES')) $prefLocales = [FORCE_SUBTITLES=>1];
        else {
            $prefLocales = array_reduce(
            explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']), 
            function ($res, $el) { 
                list($l, $q) = array_merge(explode(';q=', $el), [1]); 
                $res[$l] = (float) $q; 
                return $res; 
            }, []);
            if (!isset($prefLocales[DEFAULT_SUBTITLES])) $prefLocales[DEFAULT_SUBTITLES] = 0;
            arsort($prefLocales);
        }
        $ptn = '#-.*$#isu';
        
        foreach ($prefLocales as $p => $q) {
            foreach ($options as $lang) {
                if (strtolower(preg_replace($ptn, '', $lang))==strtolower(preg_replace($ptn,'',$p))) {
                    return $lang;
                }
            }
        }
        return DEFAULT_SUBTITLES;
    }
}
