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

// Visible routes: about + participants (home/Reproductor hidden)
$aboutLinks = preview_build_site_nav_links('about', 'chrome', array());
sn_assert(count($aboutLinks) === 2, 'about route returns about + participants');
sn_assert(in_array('about', sn_routes($aboutLinks), true), 'about route includes about');
sn_assert(!in_array('home', sn_routes($aboutLinks), true), 'home/Reproductor not in visible nav');
sn_assert(in_array('participants', sn_routes($aboutLinks), true), 'about route includes participants');

$aboutLink = sn_find($aboutLinks, 'about');
sn_assert($aboutLink !== null, 'about link exists');
sn_assert($aboutLink['aria_current'] === 'page', 'current route has aria-current=page');
sn_assert(strpos($aboutLink['class'], 'is-current') !== false, 'current route has is-current class');

// home route on player page — no home link in nav
$homeLinks = preview_build_site_nav_links('home', 'bottom', array());
sn_assert(count($homeLinks) === 2, 'home route returns visible links only');
sn_assert(sn_find($homeLinks, 'home') === null, 'home/Reproductor link omitted on player page');

// active collection surfaces collection label when not on that route
$homeParticipant = preview_build_site_nav_links('home', 'bottom', array('participants' => 'Hamida'));
$participantsLink = sn_find($homeParticipant, 'participants');
sn_assert($participantsLink !== null, 'home includes participants link');
sn_assert($participantsLink['label'] === 'Hamida', 'active collection shows participant name');
sn_assert(strpos($participantsLink['class'], 'is-active') !== false, 'active collection has is-active');
sn_assert($participantsLink['aria_current'] === 'true', 'active collection has aria-current=true');

// participants page keeps generic label for current route
$participantsPage = preview_build_site_nav_links('participants', 'bottom', array('participants' => 'Hamida'));
$navParticipantsLink = sn_find($participantsPage, 'participants');
sn_assert($navParticipantsLink['label'] === 'Participants', 'participants page keeps generic label');
sn_assert($navParticipantsLink['aria_current'] === 'page', 'participants page marks current route');

// all links use button style
foreach ($aboutLinks as $link) {
    sn_assert(strpos($link['class'], 'preview-site-nav__btn') !== false, 'links use button class');
}

// language switcher options — pure alphabetical by label
$langOpts = preview_build_language_switcher_options('about', 'en');
sn_assert(count($langOpts) >= 2, 'language switcher lists at least en + one other');
$labels = array();
foreach ($langOpts as $opt) {
    $labels[] = $opt['label'];
}
$sorted = $labels;
sort($sorted, SORT_FLAG_CASE | SORT_STRING);
sn_assert($labels === $sorted, 'language switcher sorted A-Z by label');

$enOpt = null;
foreach ($langOpts as $opt) {
    if ($opt['id'] === 'en') {
        $enOpt = $opt;
        break;
    }
}
sn_assert($enOpt !== null, 'language switcher includes English');
sn_assert($enOpt['href'] === '/preview/about?lang=en', 'English href includes explicit lang=en');
sn_assert(!empty($enOpt['selected']), 'English selected when current lang is en');

$arOpt = null;
foreach ($langOpts as $opt) {
    if ($opt['id'] === 'ar') {
        $arOpt = $opt;
        break;
    }
}
sn_assert($arOpt !== null, 'language switcher includes Arabic (ar)');
sn_assert(strpos($arOpt['label'], 'Arabic') !== false || $arOpt['label'] === 'Arabic', 'Arabic label present');

$esNav = preview_build_site_nav_links('about', 'bottom', array(), 'es');
$aboutEs = sn_find($esNav, 'about');
sn_assert(strpos($aboutEs['href'], 'lang=es') !== false, 'nav links preserve lang query');

sn_assert(preview_append_lang_query('/preview/about', 'en') === '/preview/about?lang=en', 'append lang includes en');
sn_assert(preview_append_lang_query('/preview/about', 'es') === '/preview/about?lang=es', 'append lang adds query');

echo "\nAll site_nav tests passed.\n";
