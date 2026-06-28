<?php
// Run: php preview/tests/language_resolver_test.php

require_once dirname(__DIR__) . '/lib/language_resolver.php';

function assert_eq($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n  expected: " . var_export($expected, true) . "\n  actual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$available = array('es', 'en', 'it', 'fr', 'ca', 'pt', 'arq', 'aeb');
$arabicOrder = array('arq', 'aeb');
$complete = array(
    'en' => true,
    'es' => true,
    'fr' => true,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'arq' => true,
    'aeb' => false,
);

assert_eq('es', vpc_resolve_language('it,es;q=0.9', null, $available, $complete, $arabicOrder), 'quality order skips incomplete it, picks es');
assert_eq('fr', vpc_resolve_language('fr-FR', null, $available, $complete, $arabicOrder), 'fr-FR maps to fr');

$completePt = $complete;
$completePt['pt'] = true;
assert_eq('pt', vpc_resolve_language('pt-BR', null, $available, $completePt, $arabicOrder), 'pt-BR maps to pt');
assert_eq('pt', vpc_resolve_language('pt-PT;q=0.8', null, $available, $completePt, $arabicOrder), 'pt-PT maps to pt');

assert_eq('arq', vpc_resolve_language('ar-DZ', null, $available, $complete, $arabicOrder), 'ar-DZ maps to arq');

$completeAeb = $complete;
$completeAeb['aeb'] = true;
assert_eq('aeb', vpc_resolve_language('ar-TN', null, $available, $completeAeb, $arabicOrder), 'ar-TN maps to aeb');

assert_eq('en', vpc_resolve_language('ar-TN', null, $available, $complete, $arabicOrder), 'ar-TN with incomplete aeb falls through to en');

assert_eq('arq', vpc_resolve_language('ar', null, $available, $complete, $arabicOrder), 'bare ar picks first ready Arabic in config order');

$completeArqOnly = array(
    'en' => true,
    'es' => false,
    'fr' => false,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'arq' => true,
    'aeb' => false,
);
assert_eq('arq', vpc_resolve_language('ar', null, $available, $completeArqOnly, $arabicOrder), 'bare ar picks arq when only arq is ready');

$completeAebOnly = array(
    'en' => true,
    'es' => false,
    'fr' => false,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'arq' => false,
    'aeb' => true,
);
assert_eq('aeb', vpc_resolve_language('ar', null, $available, $completeAebOnly, $arabicOrder), 'bare ar picks aeb when only aeb is ready');

assert_eq('it', vpc_resolve_language('en', 'it', $available, $complete, $arabicOrder), '?lang=it bypasses completeness gate');
assert_eq('es', vpc_resolve_language('es', 'xx', $available, $complete, $arabicOrder), 'unknown ?lang= ignored, auto-detect applies');

$completeEnOnly = array(
    'en' => true,
    'es' => false,
    'fr' => false,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'arq' => false,
    'aeb' => false,
);
assert_eq('en', vpc_resolve_language('es', null, $available, $completeEnOnly, $arabicOrder), 'incomplete language falls back to en');
assert_eq('en', vpc_resolve_language('', null, $available, $completeEnOnly, $arabicOrder), 'empty Accept-Language falls back to en');

echo "All tests passed.\n";
