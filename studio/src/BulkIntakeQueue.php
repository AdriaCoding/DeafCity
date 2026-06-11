<?php

namespace Studio;

class BulkIntakeQueue
{
    private string $queuePath;
    private string $bulkTmpDir;
    private string $bulkOutputDir;

    public function __construct(string $jobsBaseDir)
    {
        $base = rtrim($jobsBaseDir, '/');
        $this->queuePath = $base . '/bulk-queue.json';
        $this->bulkTmpDir = $base . '/bulk-tmp';
        $this->bulkOutputDir = $base . '/bulk-output';
    }

    public function bulkTmpDir(): string
    {
        return $this->bulkTmpDir;
    }

    public function bulkOutputDir(): string
    {
        return $this->bulkOutputDir;
    }

    public function exists(): bool
    {
        return is_file($this->queuePath);
    }

    /**
     * @param list<array{id: string, originalFilename: string, language: string, tmpAudioPath: string}> $items
     */
    public function create(array $items): void
    {
        $queueItems = [];
        foreach ($items as $item) {
            $queueItems[] = [
                'id' => $item['id'],
                'originalFilename' => $item['originalFilename'],
                'language' => $item['language'],
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

    public function markDone(string $id, string $vttPath): void
    {
        $this->updateItem($id, static fn (array $item): array => array_merge($item, [
            'status' => 'done',
            'vttPath' => $vttPath,
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

    /** @param callable(array<string, mixed>): array<string, mixed> $mutator */
    private function updateItem(string $id, callable $mutator): void
    {
        $queue = $this->read();
        foreach ($queue['items'] as $i => $item) {
            if ($item['id'] === $id) {
                $queue['items'][$i] = $mutator($item);
                break;
            }
        }
        $queue['completed'] = $this->allFinished($queue['items']);
        $this->write($queue);
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

    /** @return list<array{originalFilename: string, vttPath: string}> */
    public function doneEntries(): array
    {
        $queue = $this->read();
        $entries = [];
        foreach ($queue['items'] as $item) {
            if (($item['status'] ?? '') === 'done' && isset($item['vttPath']) && is_file($item['vttPath'])) {
                $entries[] = [
                    'originalFilename' => $item['originalFilename'],
                    'vttPath' => $item['vttPath'],
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
