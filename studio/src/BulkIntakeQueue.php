<?php

namespace Studio;

class BulkIntakeQueue
{
    private string $queuePath;
    private string $bulkTmpDir;
    private string $bulkOutputDir;
    private string $lockPath;

    public function __construct(string $jobsBaseDir)
    {
        $base = rtrim($jobsBaseDir, '/');
        $this->queuePath = $base . '/bulk-queue.json';
        $this->bulkTmpDir = $base . '/bulk-tmp';
        $this->bulkOutputDir = $base . '/bulk-output';
        $this->lockPath = $base . '/bulk-queue.lock';
    }

    public function bulkTmpDir(): string
    {
        return $this->bulkTmpDir;
    }

    public function bulkOutputDir(): string
    {
        return $this->bulkOutputDir;
    }

    /**
     * Path to the dedicated lock file (not the queue file itself — see
     * ProcessLock) guarding "launch a bulk-queue worker" sections against
     * a double launch.
     */
    public function lockFilePath(): string
    {
        return $this->lockPath;
    }

    public function exists(): bool
    {
        return is_file($this->queuePath);
    }

    /**
     * @param list<array{id: string, originalFilename: string, language: string, tmpAudioPath: string, kind?: string}> $items
     */
    public function create(array $items): void
    {
        $queueItems = [];
        foreach ($items as $item) {
            $queueItems[] = [
                'id' => $item['id'],
                'originalFilename' => $item['originalFilename'],
                'language' => $item['language'],
                'kind' => $item['kind'] ?? 'audio',
                'tmpAudioPath' => $item['tmpAudioPath'],
                'status' => 'pending',
            ];
        }

        $this->write([
            'items' => $queueItems,
            'completed' => false,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        $queue = $this->read();
        foreach ($queue['items'] as $item) {
            if ($item['status'] === 'pending') {
                return $item;
            }
        }

        return null;
    }

    public function markProcessing(string $id): void
    {
        $this->updateItem($id, static fn (array $item): array => array_merge($item, ['status' => 'processing']));
    }

    public function markDone(string $id, string $enSrtPath, string $srcSrtPath): void
    {
        $this->updateItem($id, static fn (array $item): array => array_merge($item, [
            'status'     => 'done',
            'enSrtPath'  => $enSrtPath,
            'srcSrtPath' => $srcSrtPath,
        ]));
    }

    public function markFailed(string $id, string $reason): void
    {
        $this->updateItem($id, static fn (array $item): array => array_merge($item, [
            'status' => 'failed',
            'reason' => $reason,
        ]));
    }

    /** @return array{items: list<array<string, mixed>>, completed: bool} */
    public function statusSnapshot(): array
    {
        $queue = $this->read();
        $items = [];
        foreach ($queue['items'] as $item) {
            $entry = [
                'id' => $item['id'],
                'originalFilename' => $item['originalFilename'],
                'language' => $item['language'],
                'kind' => $item['kind'] ?? 'audio',
                'status' => $item['status'],
            ];
            if (isset($item['reason'])) {
                $entry['reason'] = $item['reason'];
            }
            $items[] = $entry;
        }

        return [
            'items' => $items,
            'completed' => (bool) ($queue['completed'] ?? false),
        ];
    }

    public function destroy(): void
    {
        if (is_file($this->queuePath)) {
            unlink($this->queuePath);
        }
        if (is_dir($this->bulkTmpDir)) {
            $this->removeDir($this->bulkTmpDir);
        }
        if (is_dir($this->bulkOutputDir)) {
            $this->removeDir($this->bulkOutputDir);
        }
    }

    /**
     * Read, mutate, and rewrite the queue as one atomic critical section.
     * Without the lock here, two concurrent workers each calling
     * markDone()/markFailed()/markProcessing() around the same time can
     * both read the same pre-mutation queue, and whichever writes last
     * silently overwrites (loses) the other's update.
     *
     * Locked via a dedicated file (queuePath . '.lock'), not queuePath
     * itself, because write() commits via rename() — locking a path that
     * gets replaced by rename() would let a writer that opened its handle
     * just before the rename go on to read/lock the now-unlinked old inode
     * instead of the queue file current writers actually see (same
     * reasoning as CatalogEditor::withLockedCatalog()).
     *
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     */
    private function updateItem(string $id, callable $mutator): void
    {
        $lockFp = fopen($this->queuePath . '.lock', 'c');
        if ($lockFp === false) {
            throw new \RuntimeException('No s\'ha pogut bloquejar la cua en massa.');
        }

        try {
            flock($lockFp, LOCK_EX);

            $queue = $this->read();
            foreach ($queue['items'] as $i => $item) {
                if ($item['id'] === $id) {
                    $queue['items'][$i] = $mutator($item);
                    break;
                }
            }
            $queue['completed'] = $this->allFinished($queue['items']);
            $this->write($queue);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function allFinished(array $items): bool
    {
        if ($items === []) {
            return true;
        }
        foreach ($items as $item) {
            if (!in_array($item['status'], ['done', 'failed'], true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{originalFilename: string, language: string, enSrtPath: string, srcSrtPath: string}> */
    public function doneEntries(): array
    {
        $queue = $this->read();
        $entries = [];
        foreach ($queue['items'] as $item) {
            if (($item['status'] ?? '') === 'done' && isset($item['enSrtPath']) && is_file($item['enSrtPath'])) {
                $entries[] = [
                    'originalFilename' => $item['originalFilename'],
                    'language'         => $item['language'],
                    'enSrtPath'        => $item['enSrtPath'],
                    'srcSrtPath'       => $item['srcSrtPath'] ?? $item['enSrtPath'],
                ];
            }
        }

        return $entries;
    }

    /** @return array{items: list<array<string, mixed>>, completed: bool} */
    private function read(): array
    {
        if (!is_file($this->queuePath)) {
            throw new \RuntimeException('No hi ha cap cua en massa activa.');
        }

        $data = json_decode((string) file_get_contents($this->queuePath), true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            throw new \RuntimeException('La cua en massa no és vàlida.');
        }

        return $data;
    }

    /** @param array{items: list<array<string, mixed>>, completed: bool} $data */
    private function write(array $data): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('No s\'ha pogut codificar la cua en massa.');
        }

        $tmp = $this->queuePath . '.tmp';
        if (file_put_contents($tmp, $encoded . "\n") === false) {
            throw new \RuntimeException('No s\'ha pogut desar la cua en massa.');
        }
        if (!rename($tmp, $this->queuePath)) {
            @unlink($tmp);
            throw new \RuntimeException('No s\'ha pogut finalitzar l\'escriptura de la cua en massa.');
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
