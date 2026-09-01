<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BulkIntakeQueue;

class BulkIntakeQueueTest extends TestCase
{
    private string $jobsDir;
    private BulkIntakeQueue $queue;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-bulk-queue-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->queue = new BulkIntakeQueue($this->jobsDir);
    }

    protected function tearDown(): void
    {
        if ($this->queue->exists()) {
            $this->queue->destroy();
        }
        $this->removeDir($this->jobsDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = "$dir/$e";
            is_dir($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    private function sampleItems(): array
    {
        return [
            [
                'id' => 'item-1',
                'originalFilename' => 'talk_ca',
                'language' => 'ca',
                'tmpAudioPath' => $this->jobsDir . '/bulk-tmp/item-1.mp3',
            ],
            [
                'id' => 'item-2',
                'originalFilename' => 'talk_es',
                'language' => 'es',
                'tmpAudioPath' => $this->jobsDir . '/bulk-tmp/item-2.mp3',
            ],
        ];
    }

    public function test_create_initialises_pending_items(): void
    {
        $this->queue->create($this->sampleItems());

        $this->assertTrue($this->queue->exists());
        $snap = $this->queue->statusSnapshot();
        $this->assertFalse($snap['completed']);
        $this->assertCount(2, $snap['items']);
        $this->assertSame('pending', $snap['items'][0]['status']);
        $this->assertSame('pending', $snap['items'][1]['status']);
    }

    public function test_current_returns_first_pending_item(): void
    {
        $this->queue->create($this->sampleItems());

        $current = $this->queue->current();
        $this->assertSame('item-1', $current['id']);
    }

    public function test_mark_processing_updates_status(): void
    {
        $this->queue->create($this->sampleItems());
        $this->queue->markProcessing('item-1');

        $snap = $this->queue->statusSnapshot();
        $this->assertSame('processing', $snap['items'][0]['status']);
        $this->assertSame('pending', $snap['items'][1]['status']);
    }

    public function test_mark_done_advances_current(): void
    {
        $this->queue->create($this->sampleItems());
        $this->queue->markProcessing('item-1');
        $this->queue->markDone('item-1', '/out/item-1_EN.srt', '/out/item-1_SRC.srt');

        $this->assertSame('item-2', $this->queue->current()['id']);
        $snap = $this->queue->statusSnapshot();
        $this->assertSame('done', $snap['items'][0]['status']);
    }

    public function test_mark_failed_skips_to_next_item(): void
    {
        $this->queue->create($this->sampleItems());
        $this->queue->markProcessing('item-1');
        $this->queue->markFailed('item-1', 'Error de transcripció');

        $this->assertSame('item-2', $this->queue->current()['id']);
        $snap = $this->queue->statusSnapshot();
        $this->assertSame('failed', $snap['items'][0]['status']);
        $this->assertSame('Error de transcripció', $snap['items'][0]['reason']);
    }

    public function test_completed_when_all_items_finished(): void
    {
        $this->queue->create($this->sampleItems());
        $this->queue->markProcessing('item-1');
        $this->queue->markDone('item-1', '/out/item-1_EN.srt', '/out/item-1_SRC.srt');
        $this->queue->markProcessing('item-2');
        $this->queue->markFailed('item-2', 'Fitxer corrupte');

        $snap = $this->queue->statusSnapshot();
        $this->assertTrue($snap['completed']);
        $this->assertNull($this->queue->current());
    }

    public function test_concurrent_updates_to_different_items_do_not_lose_either_update(): void
    {
        // Three items, so each of the two concurrent workers below marks a
        // different one done — without a lock around the read-modify-write
        // cycle, both processes read the same pre-mutation queue and
        // whichever writes last silently discards the other's update.
        $this->queue->create([
            ['id' => 'item-1', 'originalFilename' => 'a', 'language' => 'ca', 'tmpAudioPath' => '/x/a.mp3'],
            ['id' => 'item-2', 'originalFilename' => 'b', 'language' => 'es', 'tmpAudioPath' => '/x/b.mp3'],
            ['id' => 'item-3', 'originalFilename' => 'c', 'language' => 'en', 'tmpAudioPath' => '/x/c.mp3'],
        ]);

        $pids = [];
        foreach (['item-1' => '/out/1_EN.srt', 'item-2' => '/out/2_EN.srt'] as $id => $enPath) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                (new BulkIntakeQueue($this->jobsDir))->markDone($id, $enPath, $enPath);
                exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $snap = $this->queue->statusSnapshot();
        $statuses = array_column($snap['items'], 'status', 'id');
        $this->assertSame('done', $statuses['item-1'], 'concurrent update to item-1 must not be lost');
        $this->assertSame('done', $statuses['item-2'], 'concurrent update to item-2 must not be lost');
        $this->assertSame('pending', $statuses['item-3']);
    }

    public function test_destroy_removes_queue_and_tmp_dir(): void
    {
        $this->queue->create($this->sampleItems());
        mkdir($this->jobsDir . '/bulk-tmp', 0777, true);
        file_put_contents($this->jobsDir . '/bulk-tmp/item-1.mp3', 'audio');

        $this->queue->destroy();

        $this->assertFalse($this->queue->exists());
        $this->assertFalse(is_dir($this->jobsDir . '/bulk-tmp'));
    }
}
