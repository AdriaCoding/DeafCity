<?php
// Run: php preview/tests/site_nav_test.php

require dirname(dirname(__FILE__)) . '/lib/site_nav_builder.php';
require dirname(dirname(__FILE__)) . '/lib/preview_locale.php';

$locale = preview_bootstrap_locale();
$preview_i18n = $locale['i18n'];

function sn_assert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

function sn_routes($links)
{
    $routes = array();
    foreach ($links as $link) {
        $routes[] = $link['route'];
    }
    return $routes;
}

function sn_find($links, $route)
{
    foreach ($links as $link) {
        if ($link['route'] === $route) {
            return $link;
        }
    }
    return null;
}

// chrome placement omits the current route
$chromeAbout = preview_build_site_nav_links('about', 'chrome', array());
sn_assert(count($chromeAbout) === 2, 'chrome on about returns two links');
sn_assert(!in_array('about', sn_routes($chromeAbout), true), 'chrome on about omits about');
sn_assert(in_array('home', sn_routes($chromeAbout), true), 'chrome on about includes home');
sn_assert(in_array('participants', sn_routes($chromeAbout), true), 'chrome on about includes participants');

// navbar placement includes all routes
$navbarAbout = preview_build_site_nav_links('about', 'navbar', array());
sn_assert(count($navbarAbout) === 3, 'navbar on about returns all three routes');
sn_assert(in_array('about', sn_routes($navbarAbout), true), 'navbar on about includes about');

$aboutLink = sn_find($navbarAbout, 'about');
sn_assert($aboutLink !== null, 'navbar about link exists');
sn_assert($aboutLink['aria_current'] === 'page', 'navbar marks current route with aria-current=page');
sn_assert(strpos($aboutLink['class'], 'is-current') !== false, 'navbar current route has is-current class');

$playerLink = sn_find($navbarAbout, 'home');
sn_assert($playerLink['aria_current'] === '', 'navbar non-current route has no aria-current');

// active collection surfaces collection label on chrome (D21)
$chromeHome = preview_build_site_nav_links('home', 'chrome', array('participants' => 'Hamida'));
$participantsLink = sn_find($chromeHome, 'participants');
sn_assert($participantsLink !== null, 'chrome home includes participants link');
sn_assert($participantsLink['label'] === 'Hamida', 'chrome active collection shows participant name');
sn_assert(strpos($participantsLink['class'], 'is-active') !== false, 'chrome active collection has is-active');
sn_assert($participantsLink['aria_current'] === 'true', 'chrome active collection has aria-current=true');

// navbar does not override label with active collection
$navbarParticipants = preview_build_site_nav_links('participants', 'navbar', array('participants' => 'Hamida'));
$navParticipantsLink = sn_find($navbarParticipants, 'participants');
sn_assert($navParticipantsLink['label'] === 'Participants', 'navbar keeps generic Participants label');
sn_assert($navParticipantsLink['aria_current'] === 'page', 'navbar marks participants as current page');

// chrome uses button style; navbar uses button style
foreach ($chromeAbout as $link) {
    sn_assert(strpos($link['class'], 'preview-site-nav__btn') !== false, 'chrome links use button class');
    sn_assert(strpos($link['class'], 'preview-site-nav__link') === false, 'chrome links avoid text link class');
}
foreach ($navbarAbout as $link) {
    sn_assert(strpos($link['class'], 'preview-site-nav__btn') !== false, 'navbar links use button class');
}

// language switcher options
$langOpts = preview_build_language_switcher_options('about', 'en');
sn_assert(count($langOpts) >= 2, 'language switcher lists at least en + one other');
$enOpt = null;
foreach ($langOpts as $opt) {
    if ($opt['id'] === 'en') {
        $enOpt = $opt;
        break;
    }
}
sn_assert($enOpt !== null, 'language switcher includes English');
sn_assert($enOpt['href'] === '/preview/about', 'English href omits lang query');
sn_assert(!empty($enOpt['selected']), 'English selected when current lang is en');

$esNav = preview_build_site_nav_links('about', 'navbar', array(), 'es');
$aboutEs = sn_find($esNav, 'about');
sn_assert(strpos($aboutEs['href'], 'lang=es') !== false, 'navbar links preserve lang query');

sn_assert(preview_append_lang_query('/preview/about', 'en') === '/preview/about', 'append lang skips en');
sn_assert(preview_append_lang_query('/preview/about', 'es') === '/preview/about?lang=es', 'append lang adds query');

echo "\nAll site_nav tests passed.\n";
