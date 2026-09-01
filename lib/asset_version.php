<?php

/**
 * Cache-busted URLs for preview JS/CSS assets.
 *
 * Every page used to hand-roll its own `?v=NN` query string per <script>/<link> tag,
 * and those numbers drifted out of sync between pages (e.g. the home page bumped to
 * v=23 for a fix while about/participants stayed on v=19) — a returning visitor could
 * then get a stale cached script paired with fresh markup. This derives the version
 * from the asset file's own filemtime() at render time, in one place, so every page
 * that includes the same file always gets the same (correct) version automatically.
 *
 * PHP 5.6 compatible.
 */

require_once __DIR__ . '/site_base.php';

if (!function_exists('preview_asset_url')) {
    /**
     * @param string $relativePath Path relative to the document root, e.g.
     *   'js/vimeo_playlist_logic.js' or 'components/vimeo_caption_player.css'.
     * @return string Absolute site path with a filemtime()-derived ?v= query string,
     *   e.g. '/js/vimeo_playlist_logic.js?v=1735689600'.
     */
    function preview_asset_url($relativePath) {
        $relativePath = ltrim((string) $relativePath, '/');
        $absolutePath = dirname(__DIR__) . '/' . $relativePath;
        $mtime = is_readable($absolutePath) ? @filemtime($absolutePath) : false;
        $version = $mtime !== false ? $mtime : 0;

        return preview_asset_base_path() . '/' . $relativePath . '?v=' . $version;
    }
}
