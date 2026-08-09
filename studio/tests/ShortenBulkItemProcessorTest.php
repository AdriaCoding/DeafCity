<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeQueue;
use Studio\JobManager;
use Studio\ShortenBulkItemProcessor;
use Studio\TranslationJobState;

class ShortenBulkItemProcessorTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private BulkIntakeQueue $bulkQueue;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-shorten-bulk-proc-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        mkdir($this->jobsDir . '/bulk-tmp', 0777, true);
        mkdir($this->jobsDir . '/bulk-output', 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->bulkQueue = new BulkIntakeQueue($this->jobsDir);
    }

    protected function tearDown(): void
    {
        $this->jobManager->cancel();
        if ($this->bulkQueue->exists()) {
            $this->bulkQueue->destroy();
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

    private function processor(?callable $waitForCompletion = null, ?callable $exec = null): ShortenBulkItemProcessor
    {
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'test-key', $exec ?? function () {});

        return new ShortenBulkItemProcessor(
            bulkQueue: $this->bulkQueue,
            jobManager: $this->jobManager,
            launcher: $launcher,
            translationState: new TranslationJobState($this->jobManager),
            waitForCompletion: $waitForCompletion ?? function (): array {
                return ['success' => true];
            },
        );
    }

    public function test_subtitle_item_produces_single_output_file_and_marks_done(): void
    {
        $id = 'item-vtt';
        $vttPath = $this->jobsDir . "/bulk-tmp/$id.vtt";
        file_put_contents($vttPath, "1\n00:00:01,000 --> 00:00:04,000\nHola\n");
        $this->bulkQueue->create([[
            'id' => $id,
            'originalFilename' => 'talk_ca',
            'language' => 'ca',
            'kind' => 'subtitle',
            'tmpAudioPath' => $vttPath,
        ]]);

        $launched = [];
        $processor = $this->processor(
            waitForCompletion: function (): array {
                file_put_contents($this->jobManager->draftPath(), "1\n00:00:01,000 --> 00:00:04,000\nHola escurçada\n");
                return ['success' => true];
            },
            exec: function (string $cmd) use (&$launched): void { $launched[] = $cmd; },
        );

        $processor->processNext();

        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertSame('done', $snap['items'][0]['status']);
        $this->assertTrue(is_file($this->jobsDir . "/bulk-output/{$id}.srt"));
        $this->assertFalse($this->jobManager->exists());
        $this->assertNotEmpty($launched);
        $this->assertStringContainsString('run_revise.sh', $launched[0]);
        $this->assertStringContainsString(escapeshellarg(''), $launched[0]);

        $doneEntries = $this->bulkQueue->doneEntries();
        $this->assertSame('ca', $doneEntries[0]['language']);
    }

    public function test_srt_item_is_stored_as_srt_and_marks_done(): void
    {
        $id = 'item-srt';
        $srtPath = $this->jobsDir . "/bulk-tmp/$id.srt";
        file_put_contents($srtPath, "1\n00:00:01,000 --> 00:00:04,000\nHola\n");
        $this->bulkQueue->create([[
            'id' => $id,
            'originalFilename' => 'talk_ca',
            'language' => 'ca',
            'kind' => 'subtitle',
            'tmpAudioPath' => $srtPath,
        ]]);

        $processor = $this->processor(function (): array {
            return ['success' => true];
        });

        $processor->processNext();

        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertSame('done', $snap['items'][0]['status']);
        $output = file_get_contents($this->jobsDir . "/bulk-output/{$id}.srt");
        $this->assertStringNotContainsString('WEBVTT', $output);
        $this->assertStringContainsString('Hola', $output);
    }

    public function test_revision_error_marks_item_failed(): void
    {
        $id = 'item-rev-err';
        $vttPath = $this->jobsDir . "/bulk-tmp/$id.vtt";
        file_put_contents($vttPath, "1\n00:00:01,000 --> 00:00:04,000\nHola\n");
        $this->bulkQueue->create([[
            'id' => $id,
            'originalFilename' => 'talk_ca',
            'language' => 'ca',
            'kind' => 'subtitle',
            'tmpAudioPath' => $vttPath,
        ]]);

        $jobManager = $this->jobManager;
        $processor = new ShortenBulkItemProcessor(
            bulkQueue: $this->bulkQueue,
            jobManager: $this->jobManager,
            launcher: new BackgroundJobLauncher('/srv/scripts', 'test-key', function () use ($jobManager): void {
                file_put_contents($jobManager->revisionStatePath(), json_encode([
                    'status' => 'error',
                    'message' => 'Gemini bad JSON',
                ]) . "\n");
            }),
            translationState: new TranslationJobState($this->jobManager),
            waitForCompletion: null,
            pollTimeoutSeconds: 4,
        );

        $started = time();
        $processor->processNext();
        $elapsed = time() - $started;

        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertSame('failed', $snap['items'][0]['status']);
        $this->assertStringContainsString('revisió', $snap['items'][0]['reason']);
        $this->assertLessThan(4, $elapsed);
        $this->assertFalse($this->jobManager->exists());
    }
}
