<?php

namespace Studio;

class VimeoRateLimitedException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?int $resetAt = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** Seconds to wait until reset (at least 1). */
    public function secondsUntilReset(?int $now = null): int
    {
        $now ??= time();
        if ($this->resetAt === null) {
            return 60;
        }
        return max(1, $this->resetAt - $now);
    }
}
