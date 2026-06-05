<?php

namespace Studio;

/**
 * Sends Interpreter audio to the Groq OpenAI-compatible transcription endpoint
 * and returns a clamped cue array (start, end, text, opaque=''), applying the
 * same monotonic clamp as the local Python path.
 *
 * The HTTP transport is an injected seam so tests never hit the network. It is
 * a callable taking the request descriptor and returning
 * ['status' => int, 'body' => string]. A status of 0 signals a transport-level
 * failure (connect/read timeout, DNS, etc.).
 */
class GroqTranscriber
{
    private const MAX_ATTEMPTS = 2; // 1 try + 1 retry
    private const BACKOFF_SECONDS = 1;

    private string $apiKey;
    private string $baseUrl;
    private int $timeoutSeconds;
    /** @var callable(array): array{status: int, body: string} */
    private $httpCallable;
    /** @var callable(int): void */
    private $sleepCallable;

    /**
     * @param callable(array): array{status: int, body: string}|null $httpCallable
     * @param callable(int): void|null $sleepCallable
     */
    public function __construct(
        string $apiKey,
        string $baseUrl,
        int $timeoutSeconds,
        ?callable $httpCallable = null,
        ?callable $sleepCallable = null,
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutSeconds = $timeoutSeconds;
        $this->httpCallable = $httpCallable ?? $this->defaultHttpCallable();
        $this->sleepCallable = $sleepCallable ?? static fn(int $s) => sleep($s);
    }

    /**
     * @return list<array{start: float, end: float, text: string, opaque: string}>
     * @throws GroqTranscriptionException
     */
    public function transcribe(string $audioPath, string $model, string $language): array
    {
        $request = [
            'url' => $this->baseUrl . '/audio/transcriptions',
            'apiKey' => $this->apiKey,
            'audioPath' => $audioPath,
            'model' => $model,
            'language' => $language,
            'temperature' => 0,
            'response_format' => 'verbose_json',
            'timeout' => $this->timeoutSeconds,
        ];

        $response = $this->callWithRetry($request);

        return $this->parseSegments($response['body']);
    }

    /**
     * @param array<string, mixed> $request
     * @return array{status: int, body: string}
     * @throws GroqTranscriptionException
     */
    private function callWithRetry(array $request): array
    {
        $lastStatus = 0;
        $lastBody = '';

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 0) {
                ($this->sleepCallable)(self::BACKOFF_SECONDS);
            }

            $response = ($this->httpCallable)($request);
            $status = (int) ($response['status'] ?? 0);
            $body = (string) ($response['body'] ?? '');
            $lastStatus = $status;
            $lastBody = $body;

            if ($status === 200) {
                return ['status' => $status, 'body' => $body];
            }

            if ($this->isRetryable($status)) {
                continue; // 429 / 5xx / transport (status 0) — retry once
            }

            // Non-retryable HTTP error — categorise and fail now.
            throw $this->httpError($status, $body);
        }

        // Exhausted retries on a retryable failure ⇒ transport.
        throw new GroqTranscriptionException(
            GroqTranscriptionException::CATEGORY_TRANSPORT,
            "Groq transcription unavailable after " . self::MAX_ATTEMPTS . " attempts (HTTP $lastStatus): $lastBody",
        );
    }

    private function isRetryable(int $status): bool
    {
        return $status === 0 || $status === 429 || $status >= 500;
    }

    private function httpError(int $status, string $body): GroqTranscriptionException
    {
        if ($status === 401 || $status === 403) {
            return new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_AUTH,
                "Groq authentication failed (HTTP $status): $body",
            );
        }

        if ($status === 413) {
            // Residual oversize after FLAC preprocessing — treat as availability.
            return new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT,
                "Groq rejected the upload as too large (HTTP 413): $body",
            );
        }

        // 400 and any other 4xx — bad input the local engine would also reject.
        return new GroqTranscriptionException(
            GroqTranscriptionException::CATEGORY_BAD_INPUT,
            "Groq rejected the audio (HTTP $status): $body",
        );
    }

    /**
     * @return list<array{start: float, end: float, text: string, opaque: string}>
     * @throws GroqTranscriptionException
     */
    private function parseSegments(string $body): array
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            // 200 OK but unparseable — a broken response, treat as transport.
            throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_TRANSPORT,
                "Groq response is not valid JSON: $body",
            );
        }

        $rawWords = $data['words'] ?? null;
        if (!is_array($rawWords)) {
            $rawWords = [];
        }

        $words = [];
        foreach ($rawWords as $w) {
            if (!is_array($w) || !isset($w['start'], $w['end'])) {
                continue;
            }
            $text = trim((string) ($w['word'] ?? ''));
            if ($text === '') {
                continue;
            }
            $words[] = [
                'start' => (float) $w['start'],
                'end' => (float) $w['end'],
                'text' => $text,
            ];
        }

        if ($words === []) {
            throw new GroqTranscriptionException(
                GroqTranscriptionException::CATEGORY_EMPTY,
                'Groq returned no usable words',
            );
        }

        $cues = (new CueChunker())->chunk($words);

        return array_map(static fn(array $c) => [...$c, 'opaque' => ''], $cues);
    }

    private function defaultHttpCallable(): callable
    {
        return static function (array $request): array {
            $ch = curl_init($request['url']);
            $postFields = [
                'model' => $request['model'],
                'temperature' => (string) $request['temperature'],
                'response_format' => $request['response_format'],
                'language' => $request['language'],
                'timestamp_granularities[]' => 'word',
                'file' => new \CURLFile($request['audioPath']),
            ];
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $request['apiKey']],
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_TIMEOUT => (int) $request['timeout'],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body === false) {
                curl_close($ch);
                // Transport failure (timeout, DNS, connect) — status 0 ⇒ retryable.
                return ['status' => 0, 'body' => ''];
            }
            curl_close($ch);
            return ['status' => $status, 'body' => (string) $body];
        };
    }
}
