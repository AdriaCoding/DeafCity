<?php

namespace DeafCity\Controllers;

use \DeafCity\BaseController;

class Home extends BaseController {

    const CORRECT_PASSWORD = 'nons1v3d3';
    
    public function index() {
        session_start();
        if (empty($_SESSION['password_ok']) && (!defined('PASSWORD_REQUIRED') || (defined('PASSWORD_REQUIRED') && PASSWORD_REQUIRED))) return $this->password();
        
        $playlists = $this->app->getVideoCache()->getPlaylists();
        
        //$selected_playlist = array_rand($playlists['playlists']);
        $selected_playlist = 0;
        $selected_video_id = $playlists['playlists'][$selected_playlist]['vimeo_ids'][0];
        
        $galleryImages = $this->app->getImageGallery()->getImages();
        
        echo $this->view('index', [
            'playlists' => $playlists,
            'selected_playlist' => $selected_playlist,
            'selected_video_id' => $selected_video_id,
            'gallery_images' => $galleryImages,
        ], 'layout');
    }
    
    protected function password() {
        $password_error = false;

        if (!empty($_POST['password'])) {
            if ($_POST['password']==self::CORRECT_PASSWORD) {
                $_SESSION['password_ok'] = true;
                header('Location: ./', true, 302);
                exit;
            }
            else {
                unset($_SESSION['password_ok']);
                $password_error = true;
                header('HTTP/1.0 403 Forbidden');
            }
        }

        echo $this->view('password', [
            'password_error' => $password_error
        ], 'layout');
    }
}
