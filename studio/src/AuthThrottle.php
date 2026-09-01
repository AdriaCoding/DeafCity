<?php

namespace Studio;

/**
 * File-based, IP-keyed failed-login counter for the Studio login gate.
 *
 * Deliberately independent of the PHP session: a lockout tracked only in
 * the session could be trivially bypassed by dropping the session cookie
 * and retrying. This store persists one small JSON file per client IP
 * under its storage directory (typically data/auth-throttle/), so the
 * lockout survives across sessions/cookies for that IP.
 *
 * Injectable/testable: point the constructor at a temp directory and pass
 * a fake clock to exercise the 15-minute window deterministically.
 */
class AuthThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 15 * 60;

    /** @var callable(): int */
    private $clock;

    public function __construct(
        private string $storeDir,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? fn() => time();
    }

    public function recordFailure(string $ip): void
    {
        $now = ($this->clock)();
        $attempts = $this->readAttempts($ip, $now);
        $attempts[] = $now;
        $this->write($ip, $attempts);
    }

    public function isLockedOut(string $ip): bool
    {
        return count($this->readAttempts($ip, ($this->clock)())) >= self::MAX_ATTEMPTS;
    }

    public function reset(string $ip): void
    {
        $path = $this->pathFor($ip);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Seconds remaining before the lockout for this IP clears, or 0 if not locked out. */
    public function secondsUntilUnlock(string $ip): int
    {
        $now = ($this->clock)();
        $attempts = $this->readAttempts($ip, $now);
        if (count($attempts) < self::MAX_ATTEMPTS) {
            return 0;
        }
        $oldest = min($attempts);

        return max(0, self::WINDOW_SECONDS - ($now - $oldest));
    }

    /** @return list<int> */
    private function readAttempts(string $ip, int $now): array
    {
        $path = $this->pathFor($ip);
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        $attempts = is_array($decoded) ? $decoded : [];

        // Prune attempts that have aged out of the window so the file
        // doesn't grow forever and old failures don't count forever.
        return array_values(array_filter(
            $attempts,
            static fn($t) => is_int($t) && ($now - $t) <= self::WINDOW_SECONDS,
        ));
    }

    /** @param list<int> $attempts */
    private function write(string $ip, array $attempts): void
    {
        if (!is_dir($this->storeDir)) {
            @mkdir($this->storeDir, 0775, true);
        }
        @file_put_contents($this->pathFor($ip), json_encode($attempts), LOCK_EX);
    }

    private function pathFor(string $ip): string
    {
        // Hash rather than use the raw IP as a filename: keeps the
        // filename safe (no colons from IPv6, no path separators) and
        // avoids writing raw client IPs straight into the filesystem.
        return $this->storeDir . '/' . hash('sha256', $ip) . '.json';
    }
}
