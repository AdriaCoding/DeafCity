<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\GroqTranscriber;
use Studio\GroqTranscriptionException;

class GroqTranscriberTest extends TestCase
{
    private string $audioPath;

    protected function setUp(): void
    {
        // A real file path so the transport is the only thing under test;
        // the injected transport never reads it.
        $this->audioPath = tempnam(sys_get_temp_dir(), 'groqtest') ?: '';
        file_put_contents($this->audioPath, 'fake flac bytes');
    }

    protected function tearDown(): void
    {
        if ($this->audioPath !== '' && is_file($this->audioPath)) {
            unlink($this->audioPath);
        }
    }

    private function transcriber(callable $http, ?callable $sleep = null): GroqTranscriber
    {
        return new GroqTranscriber(
            apiKey: 'test-key',
            baseUrl: 'https://api.groq.com/openai/v1',
            timeoutSeconds: 20,
            httpCallable: $http,
            sleepCallable: $sleep ?? static fn(int $s) => null,
        );
    }

    /** @param list<array{word: string, start: float, end: float}> $words */
    private function wordsJson(array $words): string
    {
        return json_encode(['text' => 'whatever', 'words' => $words]);
    }

    public function test_words_are_chunked_into_cues(): void
    {
        $http = fn(array $req) => [
            'status' => 200,
            'body' => $this->wordsJson([
                ['word' => ' Hola', 'start' => 0.0, 'end' => 0.5],
                ['word' => ' món',  'start' => 0.6, 'end' => 1.0],
            ]),
        ];

        $cues = $this->transcriber($http)->transcribe($this->audioPath, 'whisper-large-v3-turbo', 'ca');

        // Both words fall within pause_threshold → one cue; trim strips leading spaces.
        $this->assertCount(1, $cues);
        $this->assertSame('Hola món', $cues[0]['text']);
        $this->assertSame(0.0, $cues[0]['start']);
        $this->assertSame(1.0, $cues[0]['end']);
        $this->assertSame('', $cues[0]['opaque']);
    }

    public function test_pause_between_words_produces_two_cues(): void
    {
        $http = fn(array $req) => [
            'status' => 200,
            'body' => $this->wordsJson([
                ['word' => 'one', 'start' => 0.0, 'end' => 0.5],
                ['word' => 'two', 'start' => 1.5, 'end' => 2.0], // 1s gap > threshold
            ]),
        ];

        $cues = $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');

        $this->assertSame(['one', 'two'], array_column($cues, 'text'));
    }

    public function test_401_throws_auth(): void
    {
        $http = fn(array $req) => ['status' => 401, 'body' => '{"error":"invalid key"}'];

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
            $this->fail('Expected GroqTranscriptionException');
        } catch (GroqTranscriptionException $e) {
            $this->assertSame(GroqTranscriptionException::CATEGORY_AUTH, $e->category());
        }
    }

    public function test_400_throws_bad_input(): void
    {
        $http = fn(array $req) => ['status' => 400, 'body' => '{"error":"unsupported"}'];

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
            $this->fail('Expected GroqTranscriptionException');
        } catch (GroqTranscriptionException $e) {
            $this->assertSame(GroqTranscriptionException::CATEGORY_BAD_INPUT, $e->category());
        }
    }

    public function test_500_after_retry_throws_transport(): void
    {
        $calls = 0;
        $http = function (array $req) use (&$calls) {
            $calls++;
            return ['status' => 500, 'body' => 'server error'];
        };

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
            $this->fail('Expected GroqTranscriptionException');
        } catch (GroqTranscriptionException $e) {
            $this->assertSame(GroqTranscriptionException::CATEGORY_TRANSPORT, $e->category());
        }
        $this->assertSame(2, $calls, 'should make exactly 2 attempts (1 retry) on 5xx');
    }

    public function test_429_then_success_retries_once(): void
    {
        $calls = 0;
        $sleeps = [];
        $http = function (array $req) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                return ['status' => 429, 'body' => 'rate limited'];
            }
            return ['status' => 200, 'body' => $this->wordsJson([
                ['word' => 'ok', 'start' => 0.0, 'end' => 1.0],
            ])];
        };
        $sleep = function (int $s) use (&$sleeps) {
            $sleeps[] = $s;
        };

        $cues = $this->transcriber($http, $sleep)->transcribe($this->audioPath, 'm', 'ca');

        $this->assertSame(['ok'], array_column($cues, 'text'));
        $this->assertSame(2, $calls);
        $this->assertSame([1], $sleeps, 'one 1s backoff before the retry');
    }

    public function test_transport_failure_status_zero_retries_then_throws_transport(): void
    {
        $calls = 0;
        $http = function (array $req) use (&$calls) {
            $calls++;
            return ['status' => 0, 'body' => '']; // connect/read timeout
        };

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
            $this->fail('Expected GroqTranscriptionException');
        } catch (GroqTranscriptionException $e) {
            $this->assertSame(GroqTranscriptionException::CATEGORY_TRANSPORT, $e->category());
        }
        $this->assertSame(2, $calls);
    }

    public function test_200_with_no_words_throws_empty(): void
    {
        $http = fn(array $req) => ['status' => 200, 'body' => $this->wordsJson([])];

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
            $this->fail('Expected GroqTranscriptionException');
        } catch (GroqTranscriptionException $e) {
            $this->assertSame(GroqTranscriptionException::CATEGORY_EMPTY, $e->category());
        }
    }

    public function test_malformed_json_body_throws_transport(): void
    {
        $http = fn(array $req) => ['status' => 200, 'body' => 'not json at all'];

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
            $this->fail('Expected GroqTranscriptionException');
        } catch (GroqTranscriptionException $e) {
            $this->assertSame(GroqTranscriptionException::CATEGORY_TRANSPORT, $e->category());
        }
    }

    public function test_400_does_not_retry(): void
    {
        $calls = 0;
        $http = function (array $req) use (&$calls) {
            $calls++;
            return ['status' => 400, 'body' => 'bad'];
        };

        try {
            $this->transcriber($http)->transcribe($this->audioPath, 'm', 'ca');
        } catch (GroqTranscriptionException) {
            // expected
        }
        $this->assertSame(1, $calls, '4xx (non-429) must not retry');
    }
}
