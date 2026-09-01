<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\AuthThrottle;

class AuthThrottleTest extends TestCase
{
    private string $storeDir;

    protected function setUp(): void
    {
        $this->storeDir = sys_get_temp_dir() . '/studio-auth-throttle-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->storeDir);
    }

    public function test_fresh_ip_is_not_locked_out(): void
    {
        $throttle = new AuthThrottle($this->storeDir);

        $this->assertFalse($throttle->isLockedOut('1.2.3.4'));
    }

    public function test_locks_out_after_five_failures_within_the_window(): void
    {
        $throttle = new AuthThrottle($this->storeDir);

        for ($i = 0; $i < 4; $i++) {
            $throttle->recordFailure('1.2.3.4');
        }
        $this->assertFalse($throttle->isLockedOut('1.2.3.4'));

        $throttle->recordFailure('1.2.3.4');
        $this->assertTrue($throttle->isLockedOut('1.2.3.4'));
    }

    public function test_lockout_is_keyed_per_ip(): void
    {
        $throttle = new AuthThrottle($this->storeDir);

        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('1.2.3.4');
        }

        $this->assertTrue($throttle->isLockedOut('1.2.3.4'));
        $this->assertFalse($throttle->isLockedOut('9.9.9.9'));
    }

    public function test_lockout_clears_once_the_window_elapses(): void
    {
        $now = 1_000_000;
        $clock = function () use (&$now) { return $now; };
        $throttle = new AuthThrottle($this->storeDir, $clock);

        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('1.2.3.4');
        }
        $this->assertTrue($throttle->isLockedOut('1.2.3.4'));

        $now += AuthThrottle::WINDOW_SECONDS + 1;
        $this->assertFalse($throttle->isLockedOut('1.2.3.4'));
    }

    public function test_reset_clears_the_lockout(): void
    {
        $throttle = new AuthThrottle($this->storeDir);

        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('1.2.3.4');
        }
        $this->assertTrue($throttle->isLockedOut('1.2.3.4'));

        $throttle->reset('1.2.3.4');

        $this->assertFalse($throttle->isLockedOut('1.2.3.4'));
    }

    public function test_lockout_survives_a_new_throttle_instance_same_ip(): void
    {
        // Simulates the lockout being IP-keyed on disk rather than
        // session-bound: a brand new AuthThrottle instance pointed at the
        // same store dir (as would happen on a fresh session / dropped
        // cookie) still sees the lockout.
        $first = new AuthThrottle($this->storeDir);
        for ($i = 0; $i < 5; $i++) {
            $first->recordFailure('1.2.3.4');
        }

        $second = new AuthThrottle($this->storeDir);

        $this->assertTrue($second->isLockedOut('1.2.3.4'));
    }

    public function test_seconds_until_unlock_is_zero_when_not_locked_out(): void
    {
        $throttle = new AuthThrottle($this->storeDir);

        $this->assertSame(0, $throttle->secondsUntilUnlock('1.2.3.4'));
    }

    public function test_seconds_until_unlock_reflects_remaining_window(): void
    {
        $now = 1_000_000;
        $clock = function () use (&$now) { return $now; };
        $throttle = new AuthThrottle($this->storeDir, $clock);

        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('1.2.3.4');
        }

        $now += 100;
        $remaining = $throttle->secondsUntilUnlock('1.2.3.4');

        $this->assertSame(AuthThrottle::WINDOW_SECONDS - 100, $remaining);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
