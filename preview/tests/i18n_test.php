<?php
// Run: php preview/tests/i18n_test.php

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
    'content.typology.acudits.label' => array(
        'section' => 'content',
        'translations' => array('en' => 'ACUDITS', 'es' => 'ACUDITS ES'),
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
assert_eq(false, isset($chrome['content.typology.acudits.label']), 'chrome map excludes content labels');

$completeness = preview_i18n_compute_completeness($entries, array('en', 'es'));
assert_eq(true, $completeness['en'], 'en complete when all chrome keys have en');
assert_eq(true, $completeness['es'], 'es complete when all chrome keys have es');

$incomplete = preview_i18n_compute_completeness($entries, array('fr'));
assert_eq(false, $incomplete['fr'], 'fr incomplete when chrome keys lack fr');

echo "All tests passed.\n";
