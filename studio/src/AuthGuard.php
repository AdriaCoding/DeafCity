<?php

namespace Studio;

class AuthGuard
{
    private const FAILURE_WINDOW_SECONDS = 900; // 15 minutes

    private array $session;
    private $clock;

    public function __construct(array &$session, ?callable $clock = null)
    {
        $this->session = &$session;
        $this->clock = $clock ?? fn() => time();
    }

    public function isAuthenticated(): bool
    {
        if (!isset($this->session['studio_auth'])) return false;
        if (($this->clock)() - $this->session['studio_auth']['login_time'] > STUDIO_SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        return true;
    }

    public function login(string $password): bool
    {
        if (!hash_equals(STUDIO_PASSWORD, $password)) {
            $this->recordSessionFailure();
            return false;
        }
        $this->session['studio_auth'] = ['login_time' => ($this->clock)()];
        unset($this->session['login_failures']);
        return true;
    }

    public function logout(): void
    {
        unset($this->session['studio_auth']);
    }

    /**
     * Session-side failed-login counter (15-minute window), tracked
     * alongside the IP-keyed on-disk counter in Studio\AuthThrottle. This
     * one is a convenience/secondary signal only — it's trivially reset by
     * dropping the session cookie, so it must never be the sole gate on a
     * lockout. See AuthThrottle for the tamper-resistant counter.
     */
    private function recordSessionFailure(): void
    {
        $now = ($this->clock)();
        $failures = $this->session['login_failures'] ?? [];
        $failures = array_values(array_filter(
            $failures,
            fn($t) => ($now - $t) <= self::FAILURE_WINDOW_SECONDS,
        ));
        $failures[] = $now;
        $this->session['login_failures'] = $failures;
    }

    public function sessionFailureCount(): int
    {
        $now = ($this->clock)();
        $failures = $this->session['login_failures'] ?? [];

        return count(array_filter(
            $failures,
            fn($t) => ($now - $t) <= self::FAILURE_WINDOW_SECONDS,
        ));
    }
}
