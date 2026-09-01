<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\ProcessLock;

class ProcessLockTest extends TestCase
{
    private string $lockFile;

    protected function setUp(): void
    {
        $this->lockFile = sys_get_temp_dir() . '/process-lock-' . uniqid() . '.lock';
    }

    protected function tearDown(): void
    {
        if (is_file($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    public function test_acquire_succeeds_when_unlocked(): void
    {
        $lock = ProcessLock::acquire($this->lockFile);

        $this->assertNotNull($lock);
        $this->assertFileExists($this->lockFile);
    }

    public function test_second_acquire_is_refused_while_first_is_held(): void
    {
        $first = ProcessLock::acquire($this->lockFile);
        $this->assertNotNull($first);

        $second = ProcessLock::acquire($this->lockFile);

        $this->assertNull($second);
    }

    public function test_acquire_succeeds_again_after_release(): void
    {
        $first = ProcessLock::acquire($this->lockFile);
        $this->assertNotNull($first);
        $first->release();

        $second = ProcessLock::acquire($this->lockFile);

        $this->assertNotNull($second);
    }

    public function test_lock_is_released_when_object_is_destroyed(): void
    {
        $first = ProcessLock::acquire($this->lockFile);
        $this->assertNotNull($first);
        $first = null; // triggers __destruct, releasing the flock

        $second = ProcessLock::acquire($this->lockFile);

        $this->assertNotNull($second);
    }

    public function test_creates_missing_parent_directory(): void
    {
        $nested = sys_get_temp_dir() . '/process-lock-dir-' . uniqid() . '/sub/lock.lock';

        $lock = ProcessLock::acquire($nested);

        $this->assertNotNull($lock);
        $this->assertFileExists($nested);

        unlink($nested);
        rmdir(dirname($nested));
        rmdir(dirname(dirname($nested)));
    }

    public function test_release_is_idempotent(): void
    {
        $lock = ProcessLock::acquire($this->lockFile);
        $this->assertNotNull($lock);

        $lock->release();
        $lock->release(); // must not error on a second release

        $reacquired = ProcessLock::acquire($this->lockFile);
        $this->assertNotNull($reacquired);
    }
}
