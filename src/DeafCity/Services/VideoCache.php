<?php

namespace DeafCity\Services;

class VideoCache {
    const FILENAME = 'cache.json';

    protected $cache;
    
    protected $maps = [
        'city' => [
            'valencia' => 'València'
        ]
    ];
    
    public function __construct() {
        
    }
    
    public function getPlaylists() {
        if (!isset($this->cache)) {
            $db = app()->getVideoDB();
            $hash = md5_file($db->getFilePath());
            if (!defined('SKIP_VIDEO_CACHE') && file_exists($file = $this->getFilePath())) {
                $cache = json_decode(file_get_contents($file), true);
                if ($cache['hash'] == $hash) {
                    $this->cache = $cache;
                }
            }
            if (empty($this->cache)) {
                $this->cache = $db->getPlaylists();
                $this->cache['hash'] = $hash; 
                file_put_contents($this->getFilePath(), json_encode($this->cache, JSON_PRETTY_PRINT));
            }
        }
        return $this->cache;
    }
    
    public function getFilePath() {
        return ROOTDIR . '/data/cache/' . self::FILENAME;
    }
    
    public function getVideoInfo($vimeo_id) {
        if (!isset($this->cache['videos'][$vimeo_id])) return false;
        $video = $this->cache['videos'][$vimeo_id];
        $info = [
            'thumb' => '',
            'duration' => '',
            'year' => '',
            'title' => '',
            'num' => '',
            'tags' => [],
            'tags_html' => '',
            'subtitles' => [],
            'width' => '1920',
            'height' => '1080'
        ];
        $info['thumb'] = $this->getThumb($video);
        if (!empty($video['body']['name'])) {
            $tokens = explode("_", $video['body']['name']);
            if (!empty($tokens[0])) {
                $info['lang'] = $tokens[0];
            }
            if (!empty($tokens[1])) {
                $info['city'] = $tokens[1];
                if (isset($this->maps['city'][$city=strtolower($info['city'])])) {
                    $info['city'] = $this->maps['city'][$city];
                }
            }
            if (!empty($tokens[2])) {
                $info['title'] = mb_strtoupper(mb_substr($tokens[2], 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($tokens[2], 1, null, 'UTF-8');
            }
            if (!empty($tokens[3])) {
                $info['num'] = $tokens[3];
            }
        }
        if (!empty($video['body']['duration'])) {
            $minutes = 0;
            $seconds = $video['body']['duration'];
            if ($seconds > 60) {
                $minutes = floor($seconds/60);
                $seconds -= $minutes*60;
            }
            $info['duration'] = "$seconds&Prime;";
            if ($minutes>0) $info['duration'] = "$minutes&prime;" . $info['duration'];
        }
        if (!empty($video['body']['release_time'])) {
            $info['year'] = substr($video['body']['release_time'], 0, 4);
        }
        if (!empty($video['body']['description']) && preg_match('#\b(\d{4})\b#isu', $video['body']['description'], $match)) {
            $info['year'] = $match[1];
        }
        if (!empty($video['body']['tags'])) {
            $info['tags'] = array_filter(array_unique(array_map(
                function($tag) {
                    return trim($tag['name']);
                },
                $video['body']['tags']
            )));
            $info['tags_html'] = implode(' ', array_map(function($tag) {
                return '#'.htmlspecialchars($tag);
            }, $info['tags']));
        }
        if (!empty($video['texttracks']['body']['data']) && is_array($texttracks = $video['texttracks']['body']['data'])) {
            $info['subtitles'] = array_filter(array_map(function($item) {
                return (isset($item['language']) && !empty($item['type']) && in_array($item['type'], ['subtitles', 'captions'])) ? $item['language'] : false;
            }, $texttracks));
        }
        if (!empty($video['body']['width']) && !empty($video['body']['height'])) {
            $info['width'] = $video['body']['width'];
            $info['height'] = $video['body']['height'];
        }
        return $info;
    }
    
    public function getThumb($video) {
        
        if (empty($video['body']['pictures']['sizes'])) return false;
        $pics = $video['body']['pictures']['sizes'];
        $thumb = false;
        for ($i = 0; $i < count($pics); $i++) {
            if (!$thumb || $pics[$i]['height']>150) {
                $thumb = $pics[$i];
                if ($thumb['height']>150) break;
            }
        }
        return $thumb;
    }
}
