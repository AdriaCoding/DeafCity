<?php
// Run: php8.4 preview/tests/language_resolver_test.php

require_once dirname(__DIR__) . '/lib/language_resolver.php';

function assert_eq($expected, $actual, $label)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\n  expected: " . var_export($expected, true) . "\n  actual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$available = array('es', 'en', 'it', 'fr', 'ca', 'pt', 'ar');
$complete = array(
    'en' => true,
    'es' => true,
    'fr' => true,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'ar' => true,
);

assert_eq('es', vpc_resolve_language('it,es;q=0.9', null, $available, $complete), 'quality order skips incomplete it, picks es');
assert_eq('fr', vpc_resolve_language('fr-FR', null, $available, $complete), 'fr-FR maps to fr');

$completePt = $complete;
$completePt['pt'] = true;
assert_eq('pt', vpc_resolve_language('pt-BR', null, $available, $completePt), 'pt-BR maps to pt');
assert_eq('pt', vpc_resolve_language('pt-PT;q=0.8', null, $available, $completePt), 'pt-PT maps to pt');

assert_eq('ar', vpc_resolve_language('ar-DZ', null, $available, $complete), 'ar-DZ maps to international ar');
assert_eq('ar', vpc_resolve_language('ar-TN', null, $available, $complete), 'ar-TN maps to international ar');
assert_eq('ar', vpc_resolve_language('ar', null, $available, $complete), 'bare ar maps to ar when complete');

$completeArOnly = array(
    'en' => true,
    'es' => false,
    'fr' => false,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'ar' => true,
);
assert_eq('ar', vpc_resolve_language('ar', null, $available, $completeArOnly), 'bare ar resolves when ar is ready');

$completeNoAr = $complete;
$completeNoAr['ar'] = false;
assert_eq('en', vpc_resolve_language('ar', null, $available, $completeNoAr), 'incomplete ar falls through to en');

assert_eq('it', vpc_resolve_language('en', 'it', $available, $complete), '?lang=it bypasses completeness gate');
assert_eq('es', vpc_resolve_language('es', 'xx', $available, $complete), 'unknown ?lang= ignored, auto-detect applies');

$completeEnOnly = array(
    'en' => true,
    'es' => false,
    'fr' => false,
    'it' => false,
    'ca' => false,
    'pt' => false,
    'ar' => false,
);
assert_eq('en', vpc_resolve_language('es', null, $available, $completeEnOnly), 'incomplete language falls back to en');
assert_eq('en', vpc_resolve_language('', null, $available, $completeEnOnly), 'empty Accept-Language falls back to en');

$completeBoth = array('en' => true, 'es' => true, 'fr' => false, 'it' => false, 'ca' => false, 'pt' => false, 'ar' => false);
assert_eq('en', vpc_resolve_language('en,es', null, $available, $completeBoth), 'equal-q tags preserve header order (en before es)');

assert_eq('en', vpc_resolve_language('ca', 'en', $available, $completeBoth), 'explicit ?lang=en persists over Accept-Language');

echo "All tests passed.\n";
