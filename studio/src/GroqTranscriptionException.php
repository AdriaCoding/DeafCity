<?php

namespace Studio;

/**
 * Thrown by GroqTranscriber. Carries a category the orchestrator switches on:
 *   transport  — network / timeout / 5xx / 429-after-retries / malformed body
 *   auth       — 401 / 403 (operator must fix the key)
 *   bad_input  — 400 unsupported / unreadable audio
 *   empty      — 200 OK but no usable cues
 */
class GroqTranscriptionException extends \RuntimeException
{
    public const CATEGORY_TRANSPORT = 'transport';
    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_BAD_INPUT = 'bad_input';
    public const CATEGORY_EMPTY = 'empty';

    private string $category;

    public function __construct(string $category, string $message)
    {
        parent::__construct($message);
        $this->category = $category;
    }

    public function category(): string
    {
        return $this->category;
    }
}
