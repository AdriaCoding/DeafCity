<?php
/**
 * Benchmark: revise revision_input.vtt via Gemini and compare to revision_expected.vtt.
 *
 * Usage:
 *   php benchmark-revision.php [--output /path/to/actual.vtt] [--input /path/to/input.vtt]
 *                            [--expected /path/to/expected.vtt] [--lang en]
 *                            [--model gemini-3.5-flash]
 *                            [--models gemini-2.5-flash,gemini-2.5-pro,...]
 *                            [--quiet] [--timeout 600]
 *
 * Batch mode (--models): runs each model sequentially, writes
 *   studio/tests/fixtures/revision_actual_{model}.vtt
 * and studio/tests/fixtures/revision_benchmark_RESULTS.md
 *
 * Override the model by setting GEMINI_REVISION_MODEL (or pass --model).
 * Environment (all optional except the API key):
 *   GEMINI_API_KEY          — API key (falls back to config/config.php)
 *   GEMINI_REVISION_MODEL   — model id, e.g. gemini-3.5-flash (default)
 *   GEMINI_REVISION_TIMEOUT — HTTP timeout in seconds (default 300)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\GeminiRevisionException;
use Studio\GeminiReviser;
use Studio\VttParser;

/** @var array<string, array{input: float, output: float, label: string}> */
const MODEL_PRICING = [
    'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50, 'label' => '2.5 Flash'],
    'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40, 'label' => '2.5 Flash-Lite'],
    'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00, 'label' => '2.5 Pro'],
    'gemini-3-flash-preview' => ['input' => 0.50, 'output' => 3.00, 'label' => '3 Flash'],
    'gemini-3.5-flash' => ['input' => 1.50, 'output' => 9.00, 'label' => '3.5 Flash'],
    'gemini-3.1-flash-lite' => ['input' => 0.25, 'output' => 1.50, 'label' => '3.1 Flash-Lite'],
];

/**
 * @return array{prompt: int, output: int, thoughts: int, total: int}|null
 */
function extractUsageMetadata(string $body): ?array
{
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return null;
    }
    $usage = $data['usageMetadata'] ?? null;
    if (!is_array($usage)) {
        return null;
    }

    return [
        'prompt' => (int) ($usage['promptTokenCount'] ?? 0),
        'output' => (int) ($usage['candidatesTokenCount'] ?? 0),
        'thoughts' => (int) ($usage['thoughtsTokenCount'] ?? 0),
        'total' => (int) ($usage['totalTokenCount'] ?? 0),
    ];
}

function estimateCostUsd(string $model, array $usage): ?float
{
    $pricing = MODEL_PRICING[$model] ?? null;
    if ($pricing === null) {
        return null;
    }

    $billableOutput = $usage['output'] + $usage['thoughts'];

    return ($usage['prompt'] / 1_000_000) * $pricing['input']
        + ($billableOutput / 1_000_000) * $pricing['output'];
}

/**
 * @return callable(string, array): array{status: int, body: string}
 */
function makeUsageCapturingHttpCallable(int $timeoutSeconds, ?array &$capturedUsage): callable
{
    return static function (string $url, array $payload) use ($timeoutSeconds, &$capturedUsage): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new GeminiRevisionException("cURL error: $error");
        }
        curl_close($ch);
        $body = (string) $body;

        if ($status === 200) {
            $capturedUsage = extractUsageMetadata($body);
        }

        return ['status' => $status, 'body' => $body];
    };
}

/**
 * @param array<int, array<string, mixed>> $expectedCues
 * @return array{
 *   model: string,
 *   elapsed: float|null,
 *   usage: array{prompt: int, output: int, thoughts: int, total: int}|null,
 *   costUsd: float|null,
 *   overLimit: int|null,
 *   textMatches: string|null,
 *   exactMatches: string|null,
 *   error: string|null,
 *   outputPath: string|null
 * }
 */
function runBenchmark(
    string $apiKey,
    string $model,
    string $inputVtt,
    string $inputPath,
    string $outputPath,
    string $sourceLang,
    int $timeout,
    ?array $expectedCues,
    bool $quiet,
): array {
    $result = [
        'model' => $model,
        'elapsed' => null,
        'usage' => null,
        'costUsd' => null,
        'overLimit' => null,
        'textMatches' => null,
        'exactMatches' => null,
        'error' => null,
        'outputPath' => null,
    ];

    $usage = null;
    $httpCallable = makeUsageCapturingHttpCallable($timeout, $usage);

    $reviser = new GeminiReviser(
        apiKey: $apiKey,
        httpCallable: $httpCallable,
        model: $model,
        timeoutSeconds: $timeout,
    );

    if (!$quiet) {
        echo "Model:    $model\n";
        echo "Timeout:  {$timeout}s\n";
        echo "Output:   $outputPath\n";
        echo "Calling Gemini revision...\n";
    } else {
        echo "Running $model... ";
    }

    try {
        $start = microtime(true);
        $revised = (new VttParser())->canonicalize($reviser->revise($inputVtt, $sourceLang));
        $elapsed = microtime(true) - $start;
        $result['elapsed'] = $elapsed;
        $result['usage'] = $usage;
        $result['costUsd'] = $usage !== null ? estimateCostUsd($model, $usage) : null;
    } catch (GeminiRevisionException $e) {
        $result['error'] = $e->getMessage();
        if ($quiet) {
            echo "FAILED\n";
        } else {
            fwrite(STDERR, "\nRevision failed: " . $e->getMessage() . "\n");
        }
        return $result;
    }

    file_put_contents($outputPath, $revised);
    $result['outputPath'] = $outputPath;

    $parser = new VttParser();
    $actualCues = $parser->parse($outputPath)['cues'];

    $overLimit = 0;
    foreach ($actualCues as $cue) {
        if (mb_strlen($cue['text']) > 60) {
            $overLimit++;
        }
    }
    $result['overLimit'] = $overLimit;

    if ($expectedCues !== null && $expectedCues !== []) {
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
        $result['textMatches'] = "$textMatches/$limit";
        $result['exactMatches'] = "$exactMatches/$limit";
    }

    if ($quiet) {
        $costStr = $result['costUsd'] !== null ? sprintf('$%.6f', $result['costUsd']) : 'n/a';
        $tokensStr = $usage !== null
            ? sprintf('%d in / %d out', $usage['prompt'], $usage['output'] + $usage['thoughts'])
            : 'n/a';
        echo sprintf(
            "done in %.1fs, %s, est. %s\n",
            $elapsed,
            $tokensStr,
            $costStr,
        );
    } else {
        echo sprintf("Done in %.1f s\n\n", $elapsed);
        if ($usage !== null) {
            echo "Tokens:   prompt={$usage['prompt']} output={$usage['output']}";
            if ($usage['thoughts'] > 0) {
                echo " thoughts={$usage['thoughts']}";
            }
            echo " total={$usage['total']}\n";
            if ($result['costUsd'] !== null) {
                echo sprintf("Est. cost: $%.6f USD\n", $result['costUsd']);
            }
        }
        echo "Wrote actual output to $outputPath\n\n";

        $inputCues = $parser->parse($inputPath)['cues'];
        echo "Cue counts: input=" . count($inputCues);
        if ($expectedCues !== null && $expectedCues !== []) {
            echo " expected=" . count($expectedCues);
        }
        echo " actual=" . count($actualCues) . "\n";
        echo "Lines over 60: $overLimit\n";

        if ($result['textMatches'] !== null) {
            echo "Text match vs expected: {$result['textMatches']}\n";
            echo "Exact match (text+timing) vs expected: {$result['exactMatches']}\n";
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

        if ($expectedCues !== null && $expectedCues !== []) {
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
    }

    return $result;
}

/**
 * @param array<int, array<string, mixed>> $results
 */
function writeResultsMarkdown(string $path, string $inputPath, string $expectedPath, array $results): void
{
    $lines = [
        '# Revision benchmark — Gemini models on revision_input.vtt',
        '',
        '**Input:** `' . basename($inputPath) . '`',
        '**Expected reference:** `' . basename($expectedPath) . '`',
        '**Pricing:** Google Gemini API standard tier (text), June 2026.',
        '',
        'Est. cost = (prompt tokens × input $/M) + ((output + thoughts) tokens × output $/M).',
        '',
        '## Results',
        '',
        '| Model | Wall (s) | Prompt tokens | Output tokens | Thoughts tokens | Total tokens | Est. cost (USD) | Lines >60 | Text match | Error |',
        '|---|---|---|---|---|---|---|---|---|---|',
    ];

    foreach ($results as $r) {
        $usage = $r['usage'];
        $error = $r['error'] ?? '';
        if (strlen($error) > 60) {
            $error = substr($error, 0, 57) . '...';
        }
        $error = str_replace('|', '\\|', $error);

        $lines[] = sprintf(
            '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
            $r['model'],
            $r['elapsed'] !== null ? sprintf('%.1f', $r['elapsed']) : '',
            $usage !== null ? (string) $usage['prompt'] : '',
            $usage !== null ? (string) $usage['output'] : '',
            $usage !== null ? (string) $usage['thoughts'] : '',
            $usage !== null ? (string) $usage['total'] : '',
            $r['costUsd'] !== null ? sprintf('%.6f', $r['costUsd']) : '',
            $r['overLimit'] !== null ? (string) $r['overLimit'] : '',
            $r['textMatches'] ?? '',
            $error,
        );
    }

    $lines[] = '';
    file_put_contents($path, implode("\n", $lines) . "\n");
}

/**
 * @param array<int, array<string, mixed>> $results
 */
function printSummaryTable(array $results): void
{
    echo "\n=== Summary ===\n";
    printf(
        "%-28s %8s %12s %12s %14s\n",
        'Model',
        'Wall(s)',
        'Prompt tok',
        'Output tok',
        'Est. USD',
    );
    echo str_repeat('-', 78) . "\n";

    foreach ($results as $r) {
        $usage = $r['usage'];
        if ($r['error'] !== null) {
            printf("%-28s %8s %12s %12s %14s  ERROR\n", $r['model'], '', '', '', '');
            continue;
        }
        $billable = $usage !== null ? $usage['output'] + $usage['thoughts'] : 0;
        printf(
            "%-28s %8.1f %12d %12d %14.6f\n",
            $r['model'],
            $r['elapsed'] ?? 0.0,
            $usage['prompt'] ?? 0,
            $billable,
            $r['costUsd'] ?? 0.0,
        );
    }
}

// --- main ---

$opts = getopt('', ['output:', 'input:', 'expected:', 'lang:', 'model:', 'models:', 'timeout:', 'quiet', 'results:']);

$fixturesDir = __DIR__ . '/../tests/fixtures';
$inputPath = $opts['input'] ?? $fixturesDir . '/revision_input.vtt';
$expectedPath = $opts['expected'] ?? $fixturesDir . '/revision_expected.vtt';
$sourceLang = $opts['lang'] ?? 'en';
$timeout = isset($opts['timeout'])
    ? (int) $opts['timeout']
    : (int) (getenv('GEMINI_REVISION_TIMEOUT') ?: 300);
$quiet = array_key_exists('quiet', $opts);
$resultsPath = $opts['results'] ?? $fixturesDir . '/revision_benchmark_RESULTS.md';

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
$expectedCues = $expectedVtt !== null ? (new VttParser())->parse($expectedPath)['cues'] : null;

if (isset($opts['models'])) {
    $models = array_values(array_filter(array_map('trim', explode(',', $opts['models']))));
    if ($models === []) {
        fwrite(STDERR, "--models requires a comma-separated list of model ids.\n");
        exit(1);
    }

    if (!$quiet) {
        echo "Batch benchmark: " . count($models) . " models\n";
        echo "Input:    $inputPath\n";
        echo "Expected: " . ($expectedVtt !== null ? $expectedPath : '(none)') . "\n";
        echo "Results:  $resultsPath\n\n";
    }

    $results = [];
    foreach ($models as $model) {
        $outputPath = $fixturesDir . '/revision_actual_' . $model . '.vtt';
        $results[] = runBenchmark(
            $apiKey,
            $model,
            $inputVtt,
            $inputPath,
            $outputPath,
            $sourceLang,
            $timeout,
            $expectedCues,
            true,
        );
    }

    writeResultsMarkdown($resultsPath, $inputPath, $expectedPath, $results);
    printSummaryTable($results);
    echo "\nWrote results to $resultsPath\n";

    $successes = array_filter($results, static fn(array $r): bool => $r['error'] === null);
    exit($successes === [] ? 1 : 0);
}

$outputPath = $opts['output'] ?? $fixturesDir . '/revision_actual.vtt';
$model = $opts['model']
    ?? getenv('GEMINI_REVISION_MODEL')
    ?: 'gemini-3.5-flash';

if (!$quiet) {
    echo "API key:  " . (getenv('GEMINI_API_KEY') ? 'env' : 'config/config.php') . "\n";
    echo "Input:    $inputPath\n";
    echo "Expected: " . ($expectedVtt !== null ? $expectedPath : '(none)') . "\n";
}

$result = runBenchmark(
    $apiKey,
    $model,
    $inputVtt,
    $inputPath,
    $outputPath,
    $sourceLang,
    $timeout,
    $expectedCues,
    $quiet,
);

if ($result['error'] !== null) {
    if (str_contains($result['error'], 'timed out')) {
        fwrite(STDERR, "Try a longer timeout: --timeout 600 or GEMINI_REVISION_TIMEOUT=600\n");
    }
    exit(1);
}

exit(0);
