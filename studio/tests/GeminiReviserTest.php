<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\GeminiRevisionException;
use Studio\GeminiReviser;

class GeminiReviserTest extends TestCase
{
    private function makeReviser(callable $httpFn, callable $sleepFn = null): GeminiReviser
    {
        return new GeminiReviser(
            apiKey: 'test-api-key',
            httpCallable: $httpFn,
            sleepCallable: $sleepFn ?? static fn(int $s) => null,
        );
    }

    private function okResponse(string $revisedSrt): array
    {
        $body = json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode(['revised_srt' => $revisedSrt]),
                    ]],
                ],
            ]],
        ]);
        return ['status' => 200, 'body' => $body];
    }

    public function test_happy_path_returns_revised_srt(): void
    {
        $reviser = $this->makeReviser(function () {
            return $this->okResponse("1\n00:00:01,000 --> 00:00:02,000\nHello");
        });

        $result = $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nhello", 'en');

        $this->assertStringStartsWith("1\n", $result);
        $this->assertStringNotContainsString('WEBVTT', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function test_retry_on_429_succeeds_on_second_attempt(): void
    {
        $callCount = 0;
        $sleepArgs = [];

        $reviser = $this->makeReviser(
            function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['status' => 429, 'body' => '{"error":"rate limited"}'];
                }
                return $this->okResponse("1\n00:00:01,000 --> 00:00:02,000\nHola\n");
            },
            function (int $s) use (&$sleepArgs) {
                $sleepArgs[] = $s;
            }
        );

        $result = $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'ca');

        $this->assertSame("1\n00:00:01,000 --> 00:00:02,000\nHola\n", $result);
        $this->assertSame(2, $callCount);
        $this->assertSame([1], $sleepArgs);
    }

    public function test_retry_on_500_uses_exponential_backoff(): void
    {
        $callCount = 0;
        $sleepArgs = [];

        $reviser = $this->makeReviser(
            function () use (&$callCount) {
                $callCount++;
                if ($callCount < 3) {
                    return ['status' => 500, 'body' => '{"error":"server error"}'];
                }
                return $this->okResponse("1\n00:00:01,000 --> 00:00:02,000\nHola\n");
            },
            function (int $s) use (&$sleepArgs) {
                $sleepArgs[] = $s;
            }
        );

        $result = $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'es');

        $this->assertSame("1\n00:00:01,000 --> 00:00:02,000\nHola\n", $result);
        $this->assertSame(3, $callCount);
        $this->assertSame([1, 2], $sleepArgs);
    }

    public function test_three_consecutive_429s_throw_exception(): void
    {
        $reviser = $this->makeReviser(function () {
            return ['status' => 429, 'body' => '{"error":"rate limited"}'];
        });

        $this->expectException(GeminiRevisionException::class);
        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'en');
    }

    public function test_4xx_other_than_429_fails_fast(): void
    {
        $callCount = 0;

        $reviser = $this->makeReviser(function () use (&$callCount) {
            $callCount++;
            return ['status' => 400, 'body' => '{"error":"bad request"}'];
        });

        $this->expectException(GeminiRevisionException::class);
        try {
            $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'en');
        } finally {
            $this->assertSame(1, $callCount);
        }
    }

    public function test_invalid_json_response_throws_exception(): void
    {
        $reviser = $this->makeReviser(function () {
            return ['status' => 200, 'body' => 'not valid json'];
        });

        $this->expectException(GeminiRevisionException::class);
        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'en');
    }

    public function test_missing_revised_srt_key_throws_exception(): void
    {
        $reviser = $this->makeReviser(function () {
            $body = json_encode([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(['wrong_key' => 'x'])]]],
                ]],
            ]);
            return ['status' => 200, 'body' => $body];
        });

        $this->expectException(GeminiRevisionException::class);
        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'en');
    }

    public function test_empty_revised_srt_throws_exception(): void
    {
        $reviser = $this->makeReviser(function () {
            return $this->okResponse('');
        });

        $this->expectException(GeminiRevisionException::class);
        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'en');
    }

    /**
     * The schema key alone does not make the model emit SubRip — WebVTT never
     * required sequential indices or comma separators, so the prompt has to say
     * so explicitly or the output comes back VTT-flavoured.
     */
    public function test_request_asks_for_subrip_in_both_schema_and_prompt(): void
    {
        $captured = null;
        $reviser = $this->makeReviser(function (string $url, array $payload) use (&$captured): array {
            $captured = $payload;
            return $this->okResponse("1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        });

        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'en');

        $schema = $captured['generationConfig']['responseSchema'];
        $this->assertArrayHasKey('revised_srt', $schema['properties']);
        $this->assertSame(['revised_srt'], $schema['required']);

        $prompt = $captured['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('SubRip', $prompt);
        $this->assertStringContainsString('comma', $prompt);
        $this->assertStringContainsString('Sequential index', $prompt);
        $this->assertStringContainsString('no header', $prompt);
        $this->assertStringNotContainsString('valid WebVTT file', $prompt);
    }

    public function test_language_name_override_replaces_prompt_language_lookup(): void
    {
        $captured = null;
        $reviser = $this->makeReviser(function (string $url, array $payload) use (&$captured): array {
            $captured = $payload;
            return $this->okResponse("1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        });

        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'es', 'Mexican Spanish (not Peninsular Spanish)');

        $prompt = $captured['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('Mexican Spanish (not Peninsular Spanish)', $prompt);
        $this->assertStringNotContainsString('a Spanish subtitle file', $prompt);
    }

    public function test_empty_language_name_override_falls_back_to_lookup(): void
    {
        $captured = null;
        $reviser = $this->makeReviser(function (string $url, array $payload) use (&$captured): array {
            $captured = $payload;
            return $this->okResponse("1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        });

        $reviser->revise("1\n00:00:01,000 --> 00:00:02,000\nHola\n", 'es', '');

        $prompt = $captured['systemInstruction']['parts'][0]['text'];
        $this->assertStringContainsString('a Spanish subtitle file', $prompt);
    }
}
