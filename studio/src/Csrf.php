<?php

namespace Studio;

/**
 * Per-session CSRF token helper. Operates on a plain session array (passed
 * by reference) so it stays trivially unit-testable without a real PHP
 * session — mirrors the AuthGuard pattern.
 */
class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Returns the current session's CSRF token, generating and storing one
     * on first call. Stable for the lifetime of the session.
     *
     * @param array<string, mixed> $session
     */
    public static function issueToken(array &$session): string
    {
        $existing = $session[self::SESSION_KEY] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $session[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * @param array<string, mixed> $session
     */
    public static function validate(array &$session, ?string $token): bool
    {
        $expected = $session[self::SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }
}
