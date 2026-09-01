<?php
// Run: php8.4 tests/i18n_test.php

require_once dirname(__DIR__) . '/lib/i18n.php';

function assert_eq($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n  expected: " . var_export($expected, true) . "\n  actual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$entries = array(
    'player.filter.all_cities' => array(
        'section' => 'player',
        'translations' => array('en' => 'All cities', 'es' => 'Todas las ciudades'),
    ),
    'player.filter.all_typologies' => array(
        'section' => 'player',
        'translations' => array('en' => 'All typologies', 'es' => 'Todas las tipologías'),
    ),
    'player.aria.filters' => array(
        'section' => 'player',
        'translations' => array('en' => 'Filters', 'es' => 'Filtros'),
    ),
    'content.typology.acudits' => array(
        'section' => 'content',
        'translations' => array('en' => 'Jokes', 'es' => 'Chistes'),
    ),
);

$i18nEs = new PreviewI18n($entries, 'es');
assert_eq('Todas las ciudades', $i18nEs->t('player.filter.all_cities'), 'returns active-language value');
assert_eq('Todas las tipologías', $i18nEs->t('player.filter.all_typologies'), 'returns es translation when present');
$i18nFr = new PreviewI18n($entries, 'fr');
assert_eq('Filters', $i18nFr->t('player.aria.filters'), 'falls back to en for key without fr');
assert_eq('missing.key', $i18nEs->t('missing.key'), 'renders raw key when absent entirely');

$chrome = $i18nEs->chromeMap();
assert_eq('Todas las ciudades', $chrome['player.filter.all_cities'], 'chrome map uses active language');
assert_eq('Todas las tipologías', $chrome['player.filter.all_typologies'], 'chrome map uses es translation');
assert_eq(false, isset($chrome['content.typology.acudits']), 'chrome map excludes content labels');

$completeness = preview_i18n_compute_completeness($entries, array('en', 'es'));
assert_eq(true, $completeness['en'], 'en complete when all chrome keys have en');
assert_eq(true, $completeness['es'], 'es complete when all chrome keys have es');

$incomplete = preview_i18n_compute_completeness($entries, array('fr'));
assert_eq(false, $incomplete['fr'], 'fr incomplete when chrome keys lack fr');

require_once dirname(__DIR__) . '/lib/preview_locale.php';

assert_eq('2028', preview_edition_year_from_id('2028-salamanca'), 'edition year from id');
assert_eq('2020 València', preview_edition_full_label('2020-valencia', 'València', '2020 València'), 'edition full label year prefix');
assert_eq('Salamanca 2028', preview_edition_full_label('2028-salamanca', 'Salamanca', 'Salamanca 2028'), 'edition full label year suffix');

$preview_i18n = new PreviewI18n(array(), 'fr');
$opts = preview_localize_filter_options(array(
    array('value' => '2020-valencia', 'label' => '2020 València (live config)', 'short_label' => 'València'),
), 'edition');
assert_eq('2020 València (live config)', $opts[0]['label'], 'edition without translation keeps studio-config label');

$preview_i18n = new PreviewI18n(array(
    'content.edition.2020-valencia' => array(
        'section' => 'content',
        'translations' => array('en' => 'València', 'es' => 'Valencia'),
    ),
), 'es');
$opts = preview_localize_filter_options(array(
    array('value' => '2020-valencia', 'label' => '2020 València', 'short_label' => 'València'),
), 'edition');
assert_eq('Valencia', $opts[0]['short_label'], 'edition short_label is translated city');
assert_eq('2020 Valencia', $opts[0]['label'], 'edition label prepends year to translated city');

$preview_i18n = new PreviewI18n(array(
    'content.edition.2028-salamanca' => array(
        'section' => 'content',
        'translations' => array('en' => 'Salamanca', 'es' => 'Salamanca'),
    ),
), 'es');
$opts = preview_localize_filter_options(array(
    array('value' => '2028-salamanca', 'label' => 'Salamanca 2028', 'short_label' => 'Salamanca'),
), 'edition');
assert_eq('Salamanca 2028', $opts[0]['label'], 'edition label appends year when English uses suffix');

$preview_i18n = new PreviewI18n(array(
    'content.typology.anecdotes' => array(
        'section' => 'content',
        'translations' => array('en' => 'Anecdotes', 'es' => 'Anécdotas'),
    ),
), 'es');
$tyOpts = preview_localize_filter_options(array(
    array('value' => 'anecdotes', 'label' => 'ANÈCDOTES', 'short_label' => 'Anècdotes'),
), 'typology');
assert_eq('Anécdotas', $tyOpts[0]['short_label'], 'typology short_label uses canonical translation');
assert_eq('ANÉCDOTAS', $tyOpts[0]['label'], 'typology label is ALL CAPS of canonical translation');

$preview_i18n = new PreviewI18n(array(
    'content.typology.acudits' => array(
        'section' => 'content',
        'translations' => array('en' => 'Jokes', 'es' => 'Chistes'),
    ),
), 'en');
$enTyOpts = preview_localize_filter_options(array(
    array('value' => 'acudits', 'label' => 'ACUDITS', 'short_label' => 'Acudits'),
), 'typology');
assert_eq('Jokes', $enTyOpts[0]['short_label'], 'english typology short_label uses store en translation');
assert_eq('JOKES', $enTyOpts[0]['label'], 'english typology label is ALL CAPS of en translation');

echo "All tests passed.\n";
