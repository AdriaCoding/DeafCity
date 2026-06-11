<?php
/**
 * Benchmark: revise revision_input.vtt via Gemini and compare to revision_expected.vtt.
 *
 * Usage:
 *   php benchmark-revision.php [--output /path/to/actual.vtt] [--input /path/to/input.vtt]
 *                            [--expected /path/to/expected.vtt] [--lang en]
 *                            [--model gemini-2.5-flash]
 *
 * Override the model by setting GEMINI_REVISION_MODEL (or pass --model).
 * Environment (all optional except the API key):
 *   GEMINI_API_KEY          — API key (falls back to config/config.php)
 *   GEMINI_REVISION_MODEL   — model id, e.g. gemini-2.5-flash (default)
 *   GEMINI_REVISION_TIMEOUT — HTTP timeout in seconds (default 300)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\GeminiRevisionException;
use Studio\GeminiReviser;
use Studio\VttParser;

$opts = getopt('', ['output:', 'input:', 'expected:', 'lang:', 'model:', 'timeout:']);

$outputPath   = $opts['output']   ?? __DIR__ . '/../tests/fixtures/revision_actual.vtt';
$inputPath    = $opts['input']    ?? __DIR__ . '/../tests/fixtures/revision_input.vtt';
$expectedPath = $opts['expected'] ?? __DIR__ . '/../tests/fixtures/revision_expected.vtt';
$sourceLang   = $opts['lang']     ?? 'en';
$model        = $opts['model']
    ?? getenv('GEMINI_REVISION_MODEL')
    ?: 'gemini-2.5-flash';
$timeout      = isset($opts['timeout'])
    ? (int) $opts['timeout']
    : (int) (getenv('GEMINI_REVISION_TIMEOUT') ?: 300);

$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not set.\n");
    fwrite(STDERR, "Set it in config/config.php or export GEMINI_API_KEY=... before running.\n");
    exit(1);
}

if (!is_file($inputPath)) {
    fwrite(STDERR, "Input file not found: $inputPath\n");
    exit(1);
}

$inputVtt = file_get_contents($inputPath);
$expectedVtt = is_file($expectedPath) ? file_get_contents($expectedPath) : null;

echo "Model:    $model\n";
echo "Timeout:  {$timeout}s\n";
echo "API key:  " . (getenv('GEMINI_API_KEY') ? 'env' : 'config/config.php') . "\n";
echo "Input:    $inputPath\n";
echo "Expected: " . ($expectedVtt !== null ? $expectedPath : '(none)') . "\n";
echo "Output:   $outputPath\n";
echo "Calling Gemini revision...\n";

$reviser = new GeminiReviser(
    apiKey: $apiKey,
    model: $model,
    timeoutSeconds: $timeout,
);

try {
    $start = microtime(true);
    $revised = (new VttParser())->canonicalize($reviser->revise($inputVtt, $sourceLang));
    $elapsed = microtime(true) - $start;
    echo sprintf("Done in %.1f s\n\n", $elapsed);
} catch (GeminiRevisionException $e) {
    fwrite(STDERR, "\nRevision failed: " . $e->getMessage() . "\n");
    if (str_contains($e->getMessage(), 'timed out')) {
        fwrite(STDERR, "Try a longer timeout: --timeout 600 or GEMINI_REVISION_TIMEOUT=600\n");
    }
    exit(1);
}

file_put_contents($outputPath, $revised);
echo "Wrote actual output to $outputPath\n\n";

$parser = new VttParser();
$inputCues    = $parser->parse($inputPath)['cues'];
$actualCues   = $parser->parse($outputPath)['cues'];
$expectedCues = $expectedVtt !== null ? $parser->parse($expectedPath)['cues'] : [];

echo "Cue counts: input=" . count($inputCues);
if ($expectedCues !== []) {
    echo " expected=" . count($expectedCues);
}
echo " actual=" . count($actualCues) . "\n";

$maxLen = 0;
$overLimit = 0;
foreach ($actualCues as $cue) {
    $len = mb_strlen($cue['text']);
    $maxLen = max($maxLen, $len);
    if ($len > 60) {
        $overLimit++;
    }
}
echo "Max line length: $maxLen chars; lines over 60: $overLimit\n";

if ($expectedCues !== []) {
    $exactMatches = 0;
    $textMatches = 0;
    $limit = min(count($expectedCues), count($actualCues));
    for ($i = 0; $i < $limit; $i++) {
        $exp = $expectedCues[$i];
        $act = $actualCues[$i];
        if ($exp['text'] === $act['text']) {
            $textMatches++;
        }
        if ($exp['text'] === $act['text'] && $exp['start'] === $act['start'] && $exp['end'] === $act['end']) {
            $exactMatches++;
        }
    }
    echo "Text match vs expected (first $limit cues): $textMatches/$limit\n";
    echo "Exact match (text+timing) vs expected: $exactMatches/$limit\n";
}

if ($overLimit > 0) {
    echo "\nLines over 60 characters:\n";
    foreach ($actualCues as $i => $cue) {
        $len = mb_strlen($cue['text']);
        if ($len > 60) {
            echo sprintf("  cue %d (%d chars): %s\n", $i + 1, $len, $cue['text']);
        }
    }
}

if ($expectedCues !== []) {
    $diffs = 0;
    echo "\nFirst differences vs expected:\n";
    for ($i = 0; $i < max(count($expectedCues), count($actualCues)); $i++) {
        $exp = $expectedCues[$i] ?? null;
        $act = $actualCues[$i] ?? null;
        if ($exp === null || $act === null
            || $exp['text'] !== $act['text']
            || $exp['start'] !== $act['start']
            || $exp['end'] !== $act['end']
        ) {
            $diffs++;
            if ($diffs <= 8) {
                echo "  cue " . ($i + 1) . ":\n";
                if ($exp !== null) {
                    echo "    expected: {$exp['start']} --> {$exp['end']} | {$exp['text']}\n";
                }
                if ($act !== null) {
                    echo "    actual:   {$act['start']} --> {$act['end']} | {$act['text']}\n";
                }
            }
        }
    }
    if ($diffs === 0) {
        echo "  (none — perfect match)\n";
    } elseif ($diffs > 8) {
        echo "  ... and " . ($diffs - 8) . " more\n";
    }
}
