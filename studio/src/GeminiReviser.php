<?php

namespace Studio;

class GeminiReviser
{
    private const DEFAULT_MODEL = 'gemini-2.5-flash';
    private const DEFAULT_TIMEOUT_SECONDS = 300;
    private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_SECONDS = [1, 2, 4];

    /** Human-readable language names for Gemini prompts (ISO ids from studio-config). */
    private const PROMPT_LANGUAGE_NAMES = [
        'es' => 'Spanish',
        'en' => 'English',
        'it' => 'Italian',
        'fr' => 'French',
        'ca' => 'Catalan',
        'pt' => 'Portuguese',
        'arq' => 'Algerian Darija (Algerian Arabic dialect, Arabic script)',
        'aeb' => 'Tunisian Arabic (Tunisian Derja dialect, Arabic script)',
    ];

    private const SYSTEM_PROMPT_TEMPLATE = <<<'PROMPT'
# Role & Context
You are an expert subtitle editor. Your task is to clean, re-segment, and format a %s subtitle file to meet professional broadcasting standards.

The content is humorous in nature. You must optimize for comedic timing—ensure punchlines land on their own dedicated cues whenever the timing allows.

---

# Objective
Review the provided subtitle text and output a perfectly timed, semantically coherent, and strictly formatted WebVTT file.

---

# Strict Constraints

### 1. Formatting & Length
* **Single Line Only:** Each subtitle cue MUST consist of a single line of text (absolutely no internal line breaks/newlines within a cue).
* **Character Limit:** Each line MUST be less than or equal to 60 characters (<= 60 chars).

### 2. Linguistic Segmentation
* **Avoid Fragmentation:** Do not keep unnatural, mid-sentence breaks. Merge consecutive cues if they form a single coherent sentence or idea, provided they stay under the 60-character limit.
* **Natural Splits:** If a caption exceeds 60 characters and must be split, the split must occur only at natural linguistic boundaries (e.g., clauses, commas, or phrase boundaries).
* **No Hanging Connectors:** NEVER end a subtitle line with a conjunction (e.g., and, but, or), a relative pronoun/adverb (e.g., that, when, who, which), or a preposition (e.g., about, with, for) if the phrase or clause continues onto the next line. Either push the connector word to the beginning of the next line, or pull enough text up to complete the grammatical thought.

### 3. Timing & Chronology
* **Merged Cues:** New Start Timestamp = Start of first cue | New End Timestamp = End of last cue.
* **Split Cues:** The End timestamp of the first part MUST perfectly match the Start timestamp of the next part.
* **Proportional Splitting:** Divide the time duration proportionally based on the character count of each split relative to the total character count of the original cue.
* **Overlap Correction:** Scan for, flag, and eliminate any chronological timestamp overlaps between consecutive cues.

### 4. Text Integrity (CRITICAL)
* Do NOT change any wording.
* Do NOT paraphrase.
* Do NOT add, omit, or remove any spoken words.
* Correct missing or inconsistent punctuation (periods, commas, colons).

---

# Output Format
Return JSON only. Use the following schema:
{"revised_vtt": "<the complete corrected WebVTT file as a string>"}
The value of revised_vtt must be a valid WebVTT file beginning with WEBVTT.
PROMPT;

    private string $apiKey;
    private string $model;
    private int $timeoutSeconds;
    /** @var callable(string, array): array{status: int, body: string} */
    private $httpCallable;
    /** @var callable(int): void */
    private $sleepCallable;

    /**
     * @param callable(string, array): array{status: int, body: string} $httpCallable
     * @param callable(int): void $sleepCallable
     */
    public function __construct(
        string $apiKey,
        ?callable $httpCallable = null,
        ?callable $sleepCallable = null,
        string $model = self::DEFAULT_MODEL,
        ?int $timeoutSeconds = null,
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeoutSeconds = $timeoutSeconds ?? self::DEFAULT_TIMEOUT_SECONDS;
        $this->httpCallable = $httpCallable ?? $this->defaultHttpCallable();
        $this->sleepCallable = $sleepCallable ?? static fn(int $s) => sleep($s);
    }

    /**
     * @throws GeminiRevisionException
     */
    public function revise(string $vtt, string $sourceLang): string
    {
        return $this->callWithRetry($vtt, $sourceLang);
    }

    /**
     * @throws GeminiRevisionException
     */
    private function callWithRetry(string $vtt, string $sourceLang): string
    {
        $url = sprintf(self::ENDPOINT_TEMPLATE, $this->model) . '?key=' . urlencode($this->apiKey);
        $payload = $this->buildPayload($vtt, $sourceLang);
        $lastError = '';

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 0) {
                ($this->sleepCallable)(self::BACKOFF_SECONDS[$attempt - 1]);
            }

            $response = ($this->httpCallable)($url, $payload);
            $status = $response['status'];
            $body = $response['body'];

            if ($status === 200) {
                return $this->parseResponse($body);
            }

            if ($status === 429 || $status >= 500) {
                $lastError = "HTTP $status: $body";
                continue;
            }

            throw new GeminiRevisionException("Gemini API error HTTP $status: $body");
        }

        throw new GeminiRevisionException("Gemini API failed after " . self::MAX_ATTEMPTS . " attempts: $lastError");
    }

    private function buildPayload(string $vtt, string $sourceLang): array
    {
        $systemPrompt = sprintf(self::SYSTEM_PROMPT_TEMPLATE, $this->promptLanguageName($sourceLang));

        return [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'parts' => [['text' => $vtt]],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'revised_vtt' => ['type' => 'STRING'],
                    ],
                    'required' => ['revised_vtt'],
                ],
            ],
        ];
    }

    /**
     * @throws GeminiRevisionException
     */
    private function parseResponse(string $body): string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new GeminiRevisionException("Gemini response is not valid JSON: $body");
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text)) {
            throw new GeminiRevisionException("Gemini response missing expected text field: $body");
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['revised_vtt']) || !is_string($parsed['revised_vtt'])) {
            throw new GeminiRevisionException("Gemini response JSON missing 'revised_vtt' string: $text");
        }

        if ($parsed['revised_vtt'] === '') {
            throw new GeminiRevisionException("Gemini returned empty revised_vtt");
        }

        return $parsed['revised_vtt'];
    }

    private function promptLanguageName(string $code): string
    {
        return self::PROMPT_LANGUAGE_NAMES[$code] ?? $code;
    }

    private function defaultHttpCallable(): callable
    {
        $timeout = $this->timeoutSeconds;

        return static function (string $url, array $payload) use ($timeout): array {
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
            if ($body === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new GeminiRevisionException("cURL error: $error");
            }
            curl_close($ch);
            return ['status' => $status, 'body' => (string) $body];
        };
    }
}
