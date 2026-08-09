<?php

/**
 * A/B evaluation for the SubRip revision prompt (M6).
 *
 * Runs the same input through revision twice on the same model:
 *
 *   control — the pre-M6 WebVTT prompt and schema, replicated verbatim below,
 *             with SubRip converted in and out the way revise.php bridged it
 *   subrip  — the current GeminiReviser, native SubRip end to end
 *
 * and scores both mechanically. This exists because the prompt rewrite is the
 * one place in the VTT→SRT migration where output *quality* could shift, and
 * quality is not something the test suite can assert.
 *
 * Gates (all must hold for the SubRip arm):
 *   1. Structural validity — parses as SubRip, indices 1..N, comma separators,
 *      no WEBVTT header. Binary, and the failure mode the rewrite actually risks.
 *   2. Cue length — cues over the repo's display limit (60 + 5 tolerance) must
 *      not exceed the control arm.
 *   3. Text fidelity — for the fixture that has one, per-cue matches against the
 *      expected reference must be >= control - 1.
 *   4. Cross-arm agreement — both arms revise identical input, so their word
 *      streams should closely agree. Wide divergence means the prompt changed
 *      behaviour, not just format.
 *   5. Timing envelope — first start and last end preserved against the input,
 *      cues monotonic and non-overlapping.
 *
 * Costs real API calls. Sample size is deliberately small; pass --limit to
 * shrink it further while iterating.
 *
 * Usage:
 *   php studio/scripts/benchmark_revision_format.php [--limit N] [--model X] [--arm both|subrip]
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CaptionReader;
use Studio\GeminiReviser;
use Studio\SrtParser;
use Studio\VttParser;
use Studio\VttToSrtConverter;

const DISPLAY_LIMIT = 65; // vpc_caption_display_max_length(): 60 + 5 tolerance
const TIMING_TOLERANCE_SECONDS = 0.5;

$opts = getopt('', ['limit:', 'model:', 'arm:']);
$limit = isset($opts['limit']) ? max(1, (int) $opts['limit']) : 8;
$model = $opts['model'] ?? 'gemini-3.5-flash';
$arms  = ($opts['arm'] ?? 'both') === 'subrip' ? ['subrip'] : ['control', 'subrip'];

$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not set.\n");
    exit(1);
}

/** The pre-M6 prompt, kept verbatim so the control arm is the real previous behaviour. */
const CONTROL_PROMPT_TAIL = <<<'PROMPT'

---

# Output Format
Return JSON only. Use the following schema:
{"revised_vtt": "<the complete corrected WebVTT file as a string>"}
The value of revised_vtt must be a valid WebVTT file beginning with WEBVTT.
PROMPT;

/**
 * Rebuilds the control prompt from the current one: the shared body is
 * identical, only the format framing changed, so this stays honest as the
 * linguistic rules evolve.
 */
function controlPrompt(string $sourceLangName): string
{
    $ref = new ReflectionClass(GeminiReviser::class);
    $template = $ref->getReflectionConstant('SYSTEM_PROMPT_TEMPLATE')->getValue();
    $current = sprintf($template, $sourceLangName);

    // Restore the WebVTT objective line.
    $body = str_replace(
        'and strictly formatted SubRip (.srt) file.',
        'and strictly formatted WebVTT file.',
        $current
    );

    // Drop the SubRip structure section, then close the gap it leaves in the numbering.
    $body = preg_replace('/### 1\. SubRip Structure.*?(### 2\. Formatting)/s', '$1', $body) ?? $body;
    foreach ([[2, 1], [3, 2], [4, 3], [5, 4]] as [$from, $to]) {
        $body = str_replace("### $from. ", "### $to. ", $body);
    }

    $cut = strpos($body, '# Output Format');

    return rtrim($cut === false ? $body : substr($body, 0, $cut)) . CONTROL_PROMPT_TAIL;
}

/** @return array{status: int, body: string} */
function httpPost(string $url, array $payload, int $timeout = 300): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body === false ? '' : (string) $body];
}

/** Control arm: WebVTT in, WebVTT out, converted back to SubRip like revise.php used to. */
function reviseControl(string $srt, string $langName, string $model, string $apiKey): string
{
    $vttParser = new VttParser();
    $cues = (new SrtParser())->parseString($srt)['cues'];
    $vttIn = $vttParser->write(['header' => 'WEBVTT', 'opaque_blocks' => [], 'cues' => $cues]);

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        $model,
        urlencode($apiKey)
    );
    $payload = [
        'systemInstruction' => ['parts' => [['text' => controlPrompt($langName)]]],
        'contents' => [['parts' => [['text' => $vttIn]]]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => ['revised_vtt' => ['type' => 'STRING']],
                'required' => ['revised_vtt'],
            ],
        ],
    ];

    for ($attempt = 0; $attempt < 3; $attempt++) {
        if ($attempt > 0) {
            sleep([1, 2, 4][$attempt - 1]);
        }
        $res = httpPost($url, $payload);
        if ($res['status'] !== 200) {
            continue;
        }
        $data = json_decode($res['body'], true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text)) {
            continue;
        }
        $parsed = json_decode($text, true);
        if (!isset($parsed['revised_vtt']) || !is_string($parsed['revised_vtt'])) {
            continue;
        }
        $canonical = $vttParser->canonicalize($parsed['revised_vtt']);

        return (new VttToSrtConverter())->writeCues($vttParser->parseString($canonical)['cues']);
    }

    throw new RuntimeException('control arm failed after 3 attempts');
}

/**
 * @return array{ok: bool, problems: list<string>, cues: int, overLimit: int,
 *               firstStart: float, lastEnd: float, words: list<string>}
 */
function score(string $srt): array
{
    $problems = [];

    if (str_contains($srt, 'WEBVTT')) {
        $problems[] = 'contains a WEBVTT header';
    }

    try {
        $cues = (new SrtParser())->parseString($srt)['cues'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'problems' => ['does not parse as SubRip: ' . $e->getMessage()],
                'cues' => 0, 'overLimit' => 0, 'firstStart' => 0.0, 'lastEnd' => 0.0, 'words' => []];
    }

    foreach ($cues as $i => $cue) {
        if ($cue['id'] !== (string) ($i + 1)) {
            $problems[] = sprintf('cue %d has index "%s"', $i + 1, $cue['id']);
            break;
        }
    }

    $overLimit = 0;
    $words = [];
    $prevEnd = null;
    foreach ($cues as $i => $cue) {
        foreach (explode("\n", $cue['text']) as $line) {
            if (mb_strlen($line) > DISPLAY_LIMIT) {
                $overLimit++;
            }
        }
        if ($cue['end'] < $cue['start']) {
            $problems[] = sprintf('cue %d ends before it starts', $i + 1);
        }
        if ($prevEnd !== null && $cue['start'] < $prevEnd - 0.001) {
            $problems[] = sprintf('cue %d overlaps the previous cue', $i + 1);
        }
        $prevEnd = $cue['end'];
        foreach (preg_split('/\s+/u', mb_strtolower(trim($cue['text']))) ?: [] as $w) {
            $w = preg_replace('/[^\p{L}\p{N}]/u', '', $w) ?? '';
            if ($w !== '') {
                $words[] = $w;
            }
        }
    }

    return [
        'ok' => $problems === [],
        'problems' => array_slice($problems, 0, 3),
        'cues' => count($cues),
        'overLimit' => $overLimit,
        'firstStart' => $cues[0]['start'] ?? 0.0,
        'lastEnd' => $cues[count($cues) - 1]['end'] ?? 0.0,
        'words' => $words,
    ];
}

/** Jaccard-style agreement over the word multiset, order-insensitive. */
function agreement(array $a, array $b): float
{
    if ($a === [] && $b === []) {
        return 1.0;
    }
    $ca = array_count_values($a);
    $cb = array_count_values($b);
    $shared = 0;
    foreach ($ca as $w => $n) {
        $shared += min($n, $cb[$w] ?? 0);
    }
    $total = max(count($a), count($b));

    return $total === 0 ? 0.0 : $shared / $total;
}

/**
 * Per-cue exact matches against a reference, denominator matching the older
 * benchmark (min of the two cue counts) so the numbers stay comparable to
 * revision_benchmark_RESULTS.md.
 */
function textMatches(string $srt, ?array $expectedCues): ?string
{
    if ($expectedCues === null) {
        return null;
    }
    try {
        $cues = (new SrtParser())->parseString($srt)['cues'];
    } catch (\Throwable) {
        return '0/0';
    }
    $limit = min(count($cues), count($expectedCues));
    $hits = 0;
    for ($i = 0; $i < $limit; $i++) {
        if (trim($cues[$i]['text']) === trim($expectedCues[$i]['text'])) {
            $hits++;
        }
    }

    return "$hits/$limit";
}

/**
 * Exact per-cue matching collapses to zero the moment a model re-segments,
 * which says nothing about fidelity. Word-level agreement with the reference
 * survives re-segmentation and is measured identically for both arms.
 */
function expectedAgreement(string $srt, ?array $expectedCues): ?float
{
    if ($expectedCues === null) {
        return null;
    }
    try {
        $cues = (new SrtParser())->parseString($srt)['cues'];
    } catch (\Throwable) {
        return 0.0;
    }

    $words = static function (array $cues): array {
        $out = [];
        foreach ($cues as $cue) {
            foreach (preg_split('/\s+/u', mb_strtolower(trim($cue['text']))) ?: [] as $w) {
                $w = preg_replace('/[^\p{L}\p{N}]/u', '', $w) ?? '';
                if ($w !== '') {
                    $out[] = $w;
                }
            }
        }
        return $out;
    };

    return agreement($words($cues), $words($expectedCues));
}

// ---------------------------------------------------------------- corpus ----

$reader = new CaptionReader();
$corpus = [];

$fixtureInput = __DIR__ . '/../tests/fixtures/revision_input.vtt';
$fixtureExpected = __DIR__ . '/../tests/fixtures/revision_expected.vtt';
if (is_file($fixtureInput)) {
    $corpus[] = [
        'name' => 'revision_input (fixture)',
        'srt' => (new VttToSrtConverter())->convert($fixtureInput),
        'lang' => 'English',
        'expected' => is_file($fixtureExpected) ? (new VttParser())->parse($fixtureExpected)['cues'] : null,
    ];
}

/*
 * Real captions: one per language, each from a *different* video and drawn
 * from the longer files. Picking the median filename per language collapsed the
 * corpus onto a single title in eight languages, which is barely a sample at
 * all — re-segmentation quality is what is being measured, and short files
 * barely re-segment.
 */
$captionsDir = realpath(__DIR__ . '/../../data/captions');
$langNames = ['ES' => 'Spanish', 'EN' => 'English', 'CA' => 'Catalan', 'FR' => 'French',
              'IT' => 'Italian', 'PT' => 'Portuguese', 'AR' => 'Arabic'];

$candidates = [];
foreach (glob($captionsDir . '/*.srt') ?: [] as $path) {
    if (!preg_match('/^(.*)_([A-Z]{2,3})\.srt$/', basename($path), $m) || !isset($langNames[$m[2]])) {
        continue;
    }
    try {
        $cueCount = count($reader->read($path)['cues']);
    } catch (\Throwable) {
        continue;
    }
    $candidates[] = ['path' => $path, 'title' => $m[1], 'lang' => $m[2], 'cues' => $cueCount];
}

// Longest first, then take one per language and never the same video twice.
usort($candidates, fn($a, $b) => $b['cues'] <=> $a['cues']);
$usedLang = [];
$usedTitle = [];
foreach ($candidates as $c) {
    if (count($corpus) >= $limit) {
        break;
    }
    if (isset($usedLang[$c['lang']]) || isset($usedTitle[$c['title']])) {
        continue;
    }
    $usedLang[$c['lang']] = true;
    $usedTitle[$c['title']] = true;
    $corpus[] = [
        'name' => basename($c['path']) . " ({$c['cues']} cues)",
        'srt' => file_get_contents($c['path']),
        'lang' => $langNames[$c['lang']],
        'expected' => null,
    ];
}
$corpus = array_slice($corpus, 0, $limit);

printf("Revision format A/B — model=%s, %d input(s), arms: %s\n\n", $model, count($corpus), implode(' + ', $arms));

// ------------------------------------------------------------------ run ----

$rows = [];
$failures = [];

foreach ($corpus as $item) {
    $input = score($item['srt']);
    $row = ['name' => $item['name'], 'inputCues' => $input['cues']];

    $outputs = [];
    foreach ($arms as $arm) {
        printf("  %-42s %-8s ", $item['name'], $arm);
        $t0 = microtime(true);
        try {
            $outputs[$arm] = $arm === 'control'
                ? reviseControl($item['srt'], $item['lang'], $model, $apiKey)
                : (new GeminiReviser(apiKey: $apiKey, model: $model))->revise($item['srt'], $item['lang']);
        } catch (\Throwable $e) {
            printf("ERROR %s\n", $e->getMessage());
            $failures[] = "{$item['name']} [$arm]: " . $e->getMessage();
            continue;
        }
        $s = score($outputs[$arm]);
        $row[$arm] = $s;
        $row[$arm]['wall'] = microtime(true) - $t0;
        $row[$arm]['matches'] = textMatches($outputs[$arm], $item['expected']);
        $row[$arm]['expAgree'] = expectedAgreement($outputs[$arm], $item['expected']);
        printf("%5.1fs  %2d cues  %s\n", $row[$arm]['wall'], $s['cues'], $s['ok'] ? 'valid' : 'INVALID');
    }

    if (isset($row['control'], $row['subrip'])) {
        $row['agreement'] = agreement($row['control']['words'], $row['subrip']['words']);
    }
    $row['input'] = $input;
    $rows[] = $row;
}

// ----------------------------------------------------------------- gates ----

echo "\n";
printf("%-42s %7s %7s %7s %7s %7s\n", 'input', 'ctl cue', 'srt cue', 'ctl >65', 'srt >65', 'agree');
echo str_repeat('-', 82) . "\n";
foreach ($rows as $r) {
    printf(
        "%-42s %7s %7s %7s %7s %7s\n",
        substr($r['name'], 0, 42),
        isset($r['control']) ? $r['control']['cues'] : '-',
        isset($r['subrip']) ? $r['subrip']['cues'] : '-',
        isset($r['control']) ? $r['control']['overLimit'] : '-',
        isset($r['subrip']) ? $r['subrip']['overLimit'] : '-',
        isset($r['agreement']) ? sprintf('%.2f', $r['agreement']) : '-'
    );
}

$gate = [];

$invalid = array_filter($rows, fn($r) => isset($r['subrip']) && !$r['subrip']['ok']);
$gate['1 structural validity'] = [
    count($invalid) === 0,
    count($invalid) . ' invalid of ' . count($rows),
];
foreach ($invalid as $r) {
    $failures[] = $r['name'] . ': ' . implode('; ', $r['subrip']['problems']);
}

$worse = array_filter($rows, fn($r) => isset($r['control'], $r['subrip']) && $r['subrip']['overLimit'] > $r['control']['overLimit']);
$gate['2 cue length discipline'] = [count($worse) === 0, count($worse) . ' worse than control'];

$matchRows = array_filter($rows, fn($r) => isset($r['subrip']['expAgree'], $r['control']['expAgree']));
$matchOk = true;
$matchDetail = 'no reference available';
foreach ($matchRows as $r) {
    $matchDetail = sprintf(
        'vs expected: subrip %.2f (%s exact) / control %.2f (%s exact)',
        $r['subrip']['expAgree'],
        $r['subrip']['matches'],
        $r['control']['expAgree'],
        $r['control']['matches']
    );
    $matchOk = $r['subrip']['expAgree'] >= $r['control']['expAgree'] - 0.05;
}
$gate['3 text fidelity'] = [$matchOk, $matchDetail];

$agreements = array_column(array_filter($rows, fn($r) => isset($r['agreement'])), 'agreement');
$minAgree = $agreements === [] ? 1.0 : min($agreements);
$gate['4 cross-arm agreement'] = [$minAgree >= 0.55, sprintf('min %.2f', $minAgree)];

$envelopeBad = [];
foreach ($rows as $r) {
    if (!isset($r['subrip']) || !$r['subrip']['ok']) {
        continue;
    }
    if (abs($r['subrip']['firstStart'] - $r['input']['firstStart']) > TIMING_TOLERANCE_SECONDS
        || abs($r['subrip']['lastEnd'] - $r['input']['lastEnd']) > TIMING_TOLERANCE_SECONDS) {
        $envelopeBad[] = $r['name'];
    }
}
$gate['5 timing envelope'] = [$envelopeBad === [], count($envelopeBad) . ' drifted'];

echo "\nGates\n" . str_repeat('-', 82) . "\n";
$allPass = true;
foreach ($gate as $name => [$pass, $detail]) {
    printf("  %-26s %-5s  %s\n", $name, $pass ? 'PASS' : 'FAIL', $detail);
    $allPass = $allPass && $pass;
}

if ($failures !== []) {
    echo "\nProblems\n" . str_repeat('-', 82) . "\n";
    foreach (array_slice($failures, 0, 12) as $f) {
        echo "  - $f\n";
    }
}

echo "\n" . ($allPass ? "ALL GATES PASS\n" : "GATES FAILED\n");
exit($allPass ? 0 : 1);
