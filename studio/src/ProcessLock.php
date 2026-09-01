<?php

namespace Studio;

/**
 * A non-blocking, file-based mutex guarding "launch a background worker"
 * sections against double-launch: two near-simultaneous requests that both
 * pass a check-then-act guard (e.g. "no bulk queue exists yet") could
 * otherwise both spawn a worker and duplicate the work.
 *
 * Backed by flock(LOCK_EX|LOCK_NB) on a dedicated lock file, so it works
 * across separate PHP-FPM worker processes, not just within one request.
 * The lock is released explicitly via release() or implicitly when the
 * ProcessLock object is destroyed (end of request, for the typical
 * "acquire, launch, let the request end" usage).
 */
final class ProcessLock
{
    /** @var resource|null */
    private $handle;

    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Try to acquire the lock at $lockFilePath. Returns null immediately
     * (never blocks) if another process already holds it — the caller
     * should treat that as "a launch is already in flight" and refuse to
     * start a second one, rather than duplicating work.
     */
    public static function acquire(string $lockFilePath): ?self
    {
        $dir = dirname($lockFilePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Could not create lock directory: $dir");
        }

        $handle = fopen($lockFilePath, 'c');
        if ($handle === false) {
            throw new \RuntimeException("Could not open lock file: $lockFilePath");
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return new self($handle);
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
