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
}
