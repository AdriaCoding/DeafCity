<?php

namespace DeafCity\Services;

use Vimeo\Vimeo;

class VideoDB {
    const FILENAME = 'playlists.json';

    public function getPlaylists() {
        $videos = json_decode(file_get_contents($this->getFilePath()), true);
        
        //if (!$videos) throw new \Exception("Error decoding playlist file");
        
        $this->fetchFromVimeo($videos);
        
        return $videos;
    }
    
    public function getFilePath() {
        return ROOTDIR . '/data/' . self::FILENAME;
    }
    
    protected function fetchFromVimeo(&$videos) {
        
        $params = [];
        if ($password = $this->getVimeoPassword()) $params['password'] = $password;
        
        $client = new Vimeo(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);
        
        $videos['videos'] = [];
        
        foreach ($videos['playlists'] as $playlist) {
            foreach ($playlist['vimeo_ids'] as $vimeoid) {
                if (!isset($videos['videos'][$vimeoid])) {
                    $videos['videos'][$vimeoid] = $client->request('/videos/'.$vimeoid, $params, 'GET');
                    $videos['videos'][$vimeoid]['texttracks'] = $client->request('/videos/'.$vimeoid.'/texttracks', [], 'GET');
                    if (defined('VIMEO_APPLY_PRESET')) {
                        $response = $client->request('/videos/'.$vimeoid.'/presets/'.VIMEO_APPLY_PRESET, [], 'PUT');
                        if (isset($_REQUEST['DEBUG7778'])) echo "<pre>".htmlspecialchars(print_r($response, true))."</pre>";
                    }
                    else if (isset($_REQUEST['DEBUG7778'])) echo "<pre>WTF</pre>";
                }
            }
        }
        
    }
    
    protected function getVimeoPassword() {
        if (defined('VIMEO_PASSWORD')) return VIMEO_PASSWORD;
        else return false;
    }
}
