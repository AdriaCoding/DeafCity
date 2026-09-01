<?php

/**
 * Public URL roots for the DEAF.city site.
 *
 * Pages live at /, /about, /participants. Static assets are served at
 * /js, /css, /img, /components.
 */

if (!function_exists('preview_site_base_path')) {
    /** @return string Site route prefix; empty string means document-root routes. */
    function preview_site_base_path()
    {
        return '';
    }
}

if (!function_exists('preview_asset_base_path')) {
    /** @return string URL prefix for static assets; empty string = document-root paths. */
    function preview_asset_base_path()
    {
        return '';
    }
}

if (!function_exists('preview_home_path')) {
    function preview_home_path()
    {
        return '/';
    }
}

if (!function_exists('preview_route_path')) {
    /**
     * @param string $route home|about|participants
     */
    function preview_route_path($route)
    {
        switch ((string) $route) {
            case 'about':
                return '/about';
            case 'participants':
                return '/participants';
            case 'home':
            default:
                return preview_home_path();
        }
    }
}

if (!function_exists('preview_captions_endpoint')) {
    function preview_captions_endpoint()
    {
        return '/captions-static.php';
    }
}

if (!function_exists('preview_locale_api_path')) {
    function preview_locale_api_path()
    {
        return '/api/locale.php';
    }
}

if (!function_exists('preview_participant_home_url')) {
    function preview_participant_home_url($participantName)
    {
        return '/?participant=' . rawurlencode((string) $participantName);
    }
}

if (!function_exists('preview_page_url_meta')) {
    /**
     * @param string $route home|about|participants
     */
    function preview_page_url_meta($route)
    {
        $path = preview_route_path($route);
        if ($path !== '/') {
            return rtrim($path, '/');
        }

        return '/';
    }
}
