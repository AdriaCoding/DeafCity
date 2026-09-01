<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\AuthGuard;

// These constants mirror what config.php will define in production.
define('STUDIO_PASSWORD', 'test-secret');
define('STUDIO_SESSION_LIFETIME', 3600);

class AuthGuardTest extends TestCase
{
    public function test_fresh_guard_is_not_authenticated(): void
    {
        $session = [];
        $guard = new AuthGuard($session);

        $this->assertFalse($guard->isAuthenticated());
    }

    public function test_correct_password_authenticates(): void
    {
        $session = [];
        $guard = new AuthGuard($session);

        $result = $guard->login('test-secret');

        $this->assertTrue($result);
        $this->assertTrue($guard->isAuthenticated());
    }

    public function test_wrong_password_does_not_authenticate(): void
    {
        $session = [];
        $guard = new AuthGuard($session);

        $result = $guard->login('wrong-password');

        $this->assertFalse($result);
        $this->assertFalse($guard->isAuthenticated());
    }

    public function test_logout_clears_authentication(): void
    {
        $session = [];
        $guard = new AuthGuard($session);
        $guard->login('test-secret');

        $guard->logout();

        $this->assertFalse($guard->isAuthenticated());
    }

    public function test_session_expires_after_configured_lifetime(): void
    {
        $now = 1_000_000;
        $clock = function () use (&$now) { return $now; };

        $session = [];
        $guard = new AuthGuard($session, $clock);
        $guard->login('test-secret');

        // Advance time past the lifetime
        $now = 1_000_000 + STUDIO_SESSION_LIFETIME + 1;
        $this->assertFalse($guard->isAuthenticated());
    }

    public function test_wrong_password_still_rejected_when_lengths_differ(): void
    {
        // Regression guard for the switch to hash_equals(): a naive
        // strlen-sensitive comparison must not accidentally accept or
        // choke on a candidate password of a different length.
        $session = [];
        $guard = new AuthGuard($session);

        $this->assertFalse($guard->login('short'));
        $this->assertFalse($guard->login('a-much-longer-candidate-password-than-the-real-one'));
        $this->assertFalse($guard->isAuthenticated());
    }

    public function test_failed_logins_are_recorded_in_the_session(): void
    {
        $session = [];
        $guard = new AuthGuard($session);

        $guard->login('wrong-1');
        $guard->login('wrong-2');

        $this->assertSame(2, $guard->sessionFailureCount());
    }

    public function test_session_failure_count_prunes_attempts_outside_the_window(): void
    {
        $now = 1_000_000;
        $clock = function () use (&$now) { return $now; };

        $session = [];
        $guard = new AuthGuard($session, $clock);

        $guard->login('wrong-1');
        $now += 901; // just past the 15-minute window
        $guard->login('wrong-2');

        $this->assertSame(1, $guard->sessionFailureCount());
    }

    public function test_successful_login_clears_session_failure_count(): void
    {
        $session = [];
        $guard = new AuthGuard($session);

        $guard->login('wrong-1');
        $guard->login('test-secret');

        $this->assertSame(0, $guard->sessionFailureCount());
    }
}
