<?php
/**
 * Credits renderer. Static content (cities, sign-language names, institution
 * and people names, links, logos) is NOT translated — cities live in
 * content.edition.*, sign languages in content.sign_language.*, and proper
 * nouns/links are not translatable. Only the rubric labels are i18n keys.
 *
 * The editable half lives next door in credits_body.html: inert HTML with
 * {{placeholder}} tokens. This file owns every computation and every
 * translation lookup, and it is NOT reachable from the Studio credits editor.
 *
 * That split is deliberate. The editor used to write this file directly and
 * about/index.php includes it on every visit, so whoever held a Studio session
 * could publish arbitrary PHP that ran for every visitor to the site. An HTML
 * body with a fixed token vocabulary cannot execute anything.
 */
if (!function_exists('preview_t')) {
    require_once dirname(dirname(__DIR__)) . '/lib/preview_locale.php';
}
if (!function_exists('vpc_load_videos_catalog')) {
    require_once dirname(dirname(__DIR__)) . '/lib/videos_catalog.php';
}
if (!isset($GLOBALS['preview_i18n']) || !($GLOBALS['preview_i18n'] instanceof PreviewI18n)) {
    $__credits_locale = preview_bootstrap_locale();
    $GLOBALS['preview_i18n'] = $__credits_locale['i18n'];
}

require_once __DIR__ . '/credits_render.php';

echo credits_render_body(
    __DIR__ . '/credits_body.html',
    preview_resolve_data_dir() . '/catalog.json'
);
