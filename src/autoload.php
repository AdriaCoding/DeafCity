<?php

require_once(dirname(__DIR__) . '/config/config.php');
require_once(__DIR__ . '/functions.php');

class Autoloader {
    static public function loader($className) {
        $filename = __DIR__ . '/' . str_replace("\\", '/', $className) . ".php";
        if (file_exists($filename)) {
            include($filename);
            if (class_exists($className)) {
                return TRUE;
            }
        }
        return FALSE;
    }
}
spl_autoload_register('Autoloader::loader');

require(__DIR__ . '/vendor/autoload.php');
