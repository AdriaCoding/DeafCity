<?php

define('ROOTDIR', dirname(__DIR__));

function app() {
    return \DeafCity\App::get();
}

function asset($path) {
    return app()->getAssetManager()->getUrl($path);
}
