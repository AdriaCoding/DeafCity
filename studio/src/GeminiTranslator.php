<?php

namespace Studio;

class GeminiTranslator
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_SECONDS = [1, 2, 4];

    private string $apiKey;
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
    ) {
        $this->apiKey = $apiKey;
        $this->httpCallable = $httpCallable ?? $this->defaultHttpCallable();
        $this->sleepCallable = $sleepCallable ?? static fn(int $s) => sleep($s);
    }

    /**
     * @param string[] $texts  Plain cue texts to translate.
     * @return string[]        Translated texts, same count and order.
     * @throws GeminiTranslationException
     */
    public function translate(array $texts, string $srcLang, string $tgtLang): array
    {
        $n = count($texts);

        $translations = $this->callWithRetry($texts, $srcLang, $tgtLang, $n);

        if (count($translations) !== $n) {
            // Count mismatch — fall back to per-cue translation
            $translations = $this->translatePerCue($texts, $srcLang, $tgtLang);
        }

        return $translations;
    }

    /**
     * @param string[] $texts
     * @return string[]
     * @throws GeminiTranslationException
     */
    private function callWithRetry(array $texts, string $srcLang, string $tgtLang, int $expectedCount): array
    {
        $url = self::ENDPOINT . '?key=' . urlencode($this->apiKey);
        $payload = $this->buildPayload($texts, $srcLang, $tgtLang);
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
                // Retryable
                $lastError = "HTTP $status: $body";
                continue;
            }

            // 4xx other than 429 — fail fast
            throw new GeminiTranslationException("Gemini API error HTTP $status: $body");
        }

        throw new GeminiTranslationException("Gemini API failed after " . self::MAX_ATTEMPTS . " attempts: $lastError");
    }

    /**
     * Per-cue fallback: one API call per cue.
     *
     * @param string[] $texts
     * @return string[]
     * @throws GeminiTranslationException
     */
    private function translatePerCue(array $texts, string $srcLang, string $tgtLang): array
    {
        $results = [];
        foreach ($texts as $text) {
            $translations = $this->callWithRetry([$text], $srcLang, $tgtLang, 1);
            if (count($translations) !== 1) {
                throw new GeminiTranslationException(
                    "Per-cue fallback: expected 1 translation, got " . count($translations)
                );
            }
            $results[] = $translations[0];
        }
        return $results;
    }

    /**
     * @param string[] $texts
     */
    private function buildPayload(array $texts, string $srcLang, string $tgtLang): array
    {
        $n = count($texts);
        $systemPrompt = sprintf(
            "You translate video subtitle cues from %s to %s.\n" .
            "Hard constraints:\n" .
            "- Return exactly %d translations in the same order as the inputs\n" .
            "- Keep each translation within ~1.15x the source character count when natural\n" .
            "- Preserve speaker tags like [Music], (laughs) verbatim\n" .
            "- Preserve punctuation conventions of the target language\n" .
            "- Do not merge, split, or reorder cues\n" .
            "Return JSON only.",
            $srcLang,
            $tgtLang,
            $n,
        );

        return [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'parts' => [[
                    'text' => json_encode(array_values($texts), JSON_UNESCAPED_UNICODE),
                ]],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'translations' => [
                            'type' => 'ARRAY',
                            'items' => ['type' => 'STRING'],
                        ],
                    ],
                    'required' => ['translations'],
                ],
            ],
        ];
    }

    /**
     * @return string[]
     * @throws GeminiTranslationException
     */
    private function parseResponse(string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new GeminiTranslationException("Gemini response is not valid JSON: $body");
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($text)) {
            throw new GeminiTranslationException("Gemini response missing expected text field: $body");
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['translations']) || !is_array($parsed['translations'])) {
            throw new GeminiTranslationException("Gemini response JSON missing 'translations' array: $text");
        }

        return array_map('strval', $parsed['translations']);
    }

    private function defaultHttpCallable(): callable
    {
        return static function (string $url, array $payload): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT => 60,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new GeminiTranslationException("cURL error: $error");
            }
            curl_close($ch);
            return ['status' => $status, 'body' => (string) $body];
        };
    }
}
