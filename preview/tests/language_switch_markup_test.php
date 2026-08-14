<?php
// Run: php8.4 preview/tests/language_switch_markup_test.php
//
// The in-session Website language switch repaints server-rendered strings by walking
// data-i18n-* markers. A missing marker leaves a stale string on screen; a marker
// naming a key that does not exist leaves a raw key. Both are invisible to the PHP
// render and to the pure-function tests, so they are checked here.

require_once dirname(__DIR__) . '/lib/preview_locale.php';

function assert_true($cond, $label)
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$homePage = dirname(__DIR__) . '/index.php';
ob_start();
include $homePage;
$html = ob_get_clean();

// ── Every localized control the switch must repaint carries a marker ──────────
$requiredMarkers = array(
    'data-i18n-generic="player.filter.sign_language"' => 'sign language picker face is refreshable',
    'data-i18n-generic="player.filter.city_edition"' => 'edition picker face is refreshable',
    'data-i18n-generic="player.filter.typology"' => 'typology picker face is refreshable',
    'data-i18n-generic="player.nav.language"' => 'language picker face is refreshable',
    'data-i18n-text="player.transport.reset_short"' => 'reset button text is refreshable',
    'data-i18n-aria="player.transport.play"' => 'play control accessible name is refreshable',
    'data-i18n-aria="player.transport.prev"' => 'previous control accessible name is refreshable',
    'data-i18n-aria="player.transport.next"' => 'next control accessible name is refreshable',
    'data-i18n-aria="player.transport.reset"' => 'reset control accessible name is refreshable',
    'data-i18n-aria="player.aria.playback"' => 'playback group accessible name is refreshable',
    'data-i18n-aria="player.aria.filters"' => 'filter group accessible name is refreshable',
    // Regressions found in browser verification: both stayed in the previous language.
    'data-i18n-aria="player.transport.play_or_pause"' => 'video overlay accessible name is refreshable',
    'data-i18n-generic="player.nav.participants"' => 'participants nav label is refreshable',
    'data-i18n-aria="player.nav.participants"' => 'participants nav accessible name is refreshable',
);
foreach ($requiredMarkers as $needle => $label) {
    assert_true(strpos($html, $needle) !== false, $label);
}

// The switch selects by language id rather than parsing hrefs.
assert_true(strpos($html, 'data-lang-id=') !== false, 'language options expose their language id');

// ── Every marked key resolves in the payload the switch will receive ──────────
// A typo here would repaint a control with a raw key like "player.transport.rest".
$payload = preview_build_locale_payload('en');
$strings = $payload['strings'];

preg_match_all('~data-i18n(?:-text|-aria|-generic|-hint-key)="([^"]+)"~', $html, $m);
assert_true(count($m[1]) > 0, 'rendered page carries i18n markers to check');

$unknown = array();
foreach (array_unique($m[1]) as $key) {
    if (!isset($strings[$key])) {
        $unknown[] = $key;
    }
}
assert_true(
    $unknown === array(),
    'every marked key exists in the locale payload' . ($unknown ? ' (missing: ' . implode(', ', $unknown) . ')' : '')
);

// ── The JS composition rule agrees with the PHP one ──────────────────────────
// Accessible names are composed as "<label> (<hint>)" in two places: bottom_bar.php
// renders them, and applyI18nMarkedStrings() recomposes them on a language switch.
// If those rules ever diverge, a switch would silently rewrite every transport
// control's accessible name into a different shape. Recompute the JS rule here from
// the markers alone and require it to reproduce exactly what PHP rendered.
preg_match_all('~<[^>]*\sdata-i18n-aria="([^"]+)"[^>]*>~', $html, $tags, PREG_SET_ORDER);
assert_true(count($tags) > 0, 'found marked elements to cross-check');

$composeMismatch = array();
foreach ($tags as $tag) {
    $el = $tag[0];
    $key = $tag[1];

    $hint = '';
    if (preg_match('~\sdata-i18n-hint-key="([^"]+)"~', $el, $hk)) {
        $hint = $strings[$hk[1]];
    } elseif (preg_match('~\sdata-i18n-hint="([^"]*)"~', $el, $hl)) {
        $hint = html_entity_decode($hl[1], ENT_QUOTES, 'UTF-8');
    }

    $composed = $hint !== '' ? $strings[$key] . ' (' . $hint . ')' : $strings[$key];

    if (!preg_match('~\saria-label="([^"]*)"~', $el, $am)) {
        continue;
    }
    $rendered = html_entity_decode($am[1], ENT_QUOTES, 'UTF-8');
    if ($rendered !== $composed) {
        $composeMismatch[] = "{$key}: rendered '{$rendered}' vs recomposed '{$composed}'";
    }
}
assert_true(
    $composeMismatch === array(),
    'switch recomposes accessible names identically to the server'
        . ($composeMismatch ? ' — ' . implode('; ', $composeMismatch) : '')
);

echo "language_switch_markup_test.php: all passed\n";
