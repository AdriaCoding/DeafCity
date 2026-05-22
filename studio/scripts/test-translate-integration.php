<?php

/**
 * Integration smoke test — translates luis_02.es-MX.vtt (es→en) via the live Gemini API.
 * Run: php studio/scripts/test-translate-integration.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

use Studio\VttParser;
use Studio\GeminiTranslator;
use Studio\GeminiTranslationException;

$vttPath = __DIR__ . '/../../data/captions/luis_02.es-MX.vtt';

$parser = new VttParser();
$parsed = $parser->parse($vttPath);
$cues   = $parsed['cues'];

echo sprintf("Loaded %d cues from %s\n\n", count($cues), basename($vttPath));

$translator = new GeminiTranslator(GEMINI_API_KEY);

try {
    $translations = $translator->translate(
        array_column($cues, 'text'),
        'es',
        'en'
    );

    foreach ($translations as $i => $translated) {
        $src = $cues[$i]['text'] ?? '';
        echo sprintf("[%02d] SRC: %s\n     TRG: %s\n\n", $i + 1, $src, $translated);
    }

    echo "Done. " . count($translations) . " cues translated.\n";

} catch (GeminiTranslationException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
