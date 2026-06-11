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

    private function okResponse(string $revisedVtt): array
    {
        $body = json_encode([
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'text' => json_encode(['revised_vtt' => $revisedVtt]),
                    ]],
                ],
            ]],
        ]);
        return ['status' => 200, 'body' => $body];
    }

    public function test_happy_path_returns_revised_vtt(): void
    {
        $reviser = $this->makeReviser(function () {
            return $this->okResponse("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello");
        });

        $result = $reviser->revise("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nhello", 'en');

        $this->assertStringContainsString('WEBVTT', $result);
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
                return $this->okResponse("WEBVTT\n");
            },
            function (int $s) use (&$sleepArgs) {
                $sleepArgs[] = $s;
            }
        );

        $result = $reviser->revise("WEBVTT\n", 'ca');

        $this->assertSame("WEBVTT\n", $result);
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
                return $this->okResponse("WEBVTT\n");
            },
            function (int $s) use (&$sleepArgs) {
                $sleepArgs[] = $s;
            }
        );

        $result = $reviser->revise("WEBVTT\n", 'es');

        $this->assertSame("WEBVTT\n", $result);
        $this->assertSame(3, $callCount);
        $this->assertSame([1, 2], $sleepArgs);
    }

    public function test_three_consecutive_429s_throw_exception(): void
    {
        $reviser = $this->makeReviser(function () {
            return ['status' => 429, 'body' => '{"error":"rate limited"}'];
        });

        $this->expectException(GeminiRevisionException::class);
        $reviser->revise("WEBVTT\n", 'en');
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
            $reviser->revise("WEBVTT\n", 'en');
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
        $reviser->revise("WEBVTT\n", 'en');
    }

    public function test_missing_revised_vtt_key_throws_exception(): void
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
        $reviser->revise("WEBVTT\n", 'en');
    }

    public function test_empty_revised_vtt_throws_exception(): void
    {
        $reviser = $this->makeReviser(function () {
            return $this->okResponse('');
        });

        $this->expectException(GeminiRevisionException::class);
        $reviser->revise("WEBVTT\n", 'en');
    }
}
