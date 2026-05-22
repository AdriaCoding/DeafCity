<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\GeminiTranslationException;
use Studio\GeminiTranslator;

class GeminiTranslatorTest extends TestCase
{
    private function makeTranslator(callable $httpFn, callable $sleepFn = null): GeminiTranslator
    {
        return new GeminiTranslator(
            apiKey: 'test-api-key',
            httpCallable: $httpFn,
            sleepCallable: $sleepFn ?? static fn(int $s) => null,
        );
    }

    private function okResponse(array $translations): array
    {
        $body = json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode(['translations' => $translations]),
                    ]],
                ],
            ]],
        ]);
        return ['status' => 200, 'body' => $body];
    }

    // ------------------------------------------------------------------ happy path

    public function test_happy_path_returns_translations_of_same_length(): void
    {
        $translator = $this->makeTranslator(function (string $url, array $payload) {
            return $this->okResponse(['Hola', 'Món']);
        });

        $result = $translator->translate(['Hello', 'World'], 'en', 'ca');

        $this->assertSame(['Hola', 'Món'], $result);
    }

    // ------------------------------------------------------------------ retry on 429

    public function test_retry_on_429_succeeds_on_second_attempt(): void
    {
        $callCount = 0;
        $sleepArgs = [];

        $translator = $this->makeTranslator(
            function (string $url, array $payload) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['status' => 429, 'body' => '{"error":"rate limited"}'];
                }
                return $this->okResponse(['Bonjour', 'Monde']);
            },
            function (int $s) use (&$sleepArgs) {
                $sleepArgs[] = $s;
            }
        );

        $result = $translator->translate(['Hello', 'World'], 'en', 'fr');

        $this->assertSame(['Bonjour', 'Monde'], $result);
        $this->assertSame(2, $callCount);
        $this->assertSame([1], $sleepArgs);
    }

    public function test_retry_on_500_uses_exponential_backoff(): void
    {
        $callCount = 0;
        $sleepArgs = [];

        $translator = $this->makeTranslator(
            function (string $url, array $payload) use (&$callCount) {
                $callCount++;
                if ($callCount < 3) {
                    return ['status' => 500, 'body' => '{"error":"server error"}'];
                }
                return $this->okResponse(['Ciao']);
            },
            function (int $s) use (&$sleepArgs) {
                $sleepArgs[] = $s;
            }
        );

        $result = $translator->translate(['Hello'], 'en', 'it');

        $this->assertSame(['Ciao'], $result);
        $this->assertSame(3, $callCount);
        $this->assertSame([1, 2], $sleepArgs);
    }

    public function test_three_consecutive_429s_throw_exception(): void
    {
        $translator = $this->makeTranslator(function () {
            return ['status' => 429, 'body' => '{"error":"rate limited"}'];
        });

        $this->expectException(GeminiTranslationException::class);
        $translator->translate(['Hello'], 'en', 'fr');
    }

    // ------------------------------------------------------------------ 4xx fast-fail

    public function test_4xx_other_than_429_fails_fast(): void
    {
        $callCount = 0;

        $translator = $this->makeTranslator(function () use (&$callCount) {
            $callCount++;
            return ['status' => 400, 'body' => '{"error":"bad request"}'];
        });

        $this->expectException(GeminiTranslationException::class);
        try {
            $translator->translate(['Hello'], 'en', 'fr');
        } finally {
            // Must have only been called once (no retries on 400)
            $this->assertSame(1, $callCount);
        }
    }

    // ------------------------------------------------------------------ count mismatch per-cue fallback

    public function test_count_mismatch_triggers_per_cue_fallback(): void
    {
        $callCount = 0;

        $translator = $this->makeTranslator(function (string $url, array $payload) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                // Batch call returns wrong count
                return $this->okResponse(['Only one translation']);
            }
            // Per-cue fallback calls — return one translation each
            $contents = $payload['contents'][0]['parts'][0]['text'];
            $decoded = json_decode($contents, true);
            $text = $decoded[0] ?? 'x';
            return $this->okResponse([$text . ' (translated)']);
        });

        $result = $translator->translate(['Hello', 'World'], 'en', 'fr');

        // 1 batch call + 2 per-cue calls
        $this->assertSame(3, $callCount);
        $this->assertCount(2, $result);
    }

    public function test_per_cue_fallback_failure_throws_exception(): void
    {
        // Always returns 2 items regardless of input size — wrong for 1-cue per-cue calls
        $translator = $this->makeTranslator(function () {
            return $this->okResponse(['Item one', 'Item two']);
        });

        $this->expectException(GeminiTranslationException::class);
        $translator->translate(['Hello', 'World', 'Three'], 'en', 'fr');
    }

    // ------------------------------------------------------------------ schema violation

    public function test_invalid_json_response_throws_exception(): void
    {
        $translator = $this->makeTranslator(function () {
            return ['status' => 200, 'body' => 'not valid json at all'];
        });

        $this->expectException(GeminiTranslationException::class);
        $translator->translate(['Hello'], 'en', 'fr');
    }

    public function test_missing_translations_key_throws_exception(): void
    {
        $translator = $this->makeTranslator(function () {
            $body = json_encode([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['wrong_key' => ['x']])]]],
                ]],
            ]);
            return ['status' => 200, 'body' => $body];
        });

        $this->expectException(GeminiTranslationException::class);
        $translator->translate(['Hello'], 'en', 'fr');
    }

    // ------------------------------------------------------------------ request structure

    public function test_request_url_contains_api_key(): void
    {
        $capturedUrl = null;

        $translator = $this->makeTranslator(function (string $url, array $payload) use (&$capturedUrl) {
            $capturedUrl = $url;
            return $this->okResponse(['Hola']);
        });

        $translator->translate(['Hello'], 'en', 'ca');

        $this->assertStringContainsString('test-api-key', $capturedUrl);
        $this->assertStringContainsString('generativelanguage.googleapis.com', $capturedUrl);
    }

    public function test_request_payload_has_system_instruction_and_contents(): void
    {
        $capturedPayload = null;

        $translator = $this->makeTranslator(function (string $url, array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;
            return $this->okResponse(['Hola']);
        });

        $translator->translate(['Hello'], 'en', 'ca');

        $this->assertArrayHasKey('systemInstruction', $capturedPayload);
        $this->assertArrayHasKey('contents', $capturedPayload);
        $this->assertArrayHasKey('generationConfig', $capturedPayload);
        $this->assertSame('application/json', $capturedPayload['generationConfig']['responseMimeType']);
    }
}
