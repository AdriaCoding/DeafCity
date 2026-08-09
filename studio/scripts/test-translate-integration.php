<?php

/**
 * Integration smoke test — translates a real Spanish caption (es→en) via the
 * live Gemini API.
 *
 * Run: php studio/scripts/test-translate-integration.php [path/to/captions.srt]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../../config/config.php';

use Studio\CaptionReader;
use Studio\GeminiTranslator;
use Studio\GeminiTranslationException;

/* Any Spanish caption will do; the previous hardcoded name predated a rename. */
$captionPath = $argv[1] ?? (glob(__DIR__ . '/../../data/captions/*_ES.srt')[0] ?? null);
if ($captionPath === null || !is_file($captionPath)) {
    fwrite(STDERR, "No Spanish caption found; pass one as an argument.\n");
    exit(1);
}

$cues = (new CaptionReader())->read($captionPath)['cues'];

echo sprintf("Loaded %d cues from %s\n\n", count($cues), basename($captionPath));

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
