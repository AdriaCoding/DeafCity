<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeQueue;
use Studio\BulkItemProcessor;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\TranslationJobState;
use Studio\UploadedFile;

class BulkItemProcessorTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private BulkIntakeQueue $bulkQueue;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-bulk-proc-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        mkdir($this->jobsDir . '/bulk-tmp', 0777, true);
        mkdir($this->jobsDir . '/bulk-output', 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->bulkQueue = new BulkIntakeQueue($this->jobsDir);
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
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

    private function seedQueueItem(string $id = 'item-1'): array
    {
        $audioPath = $this->jobsDir . "/bulk-tmp/$id.mp3";
        file_put_contents($audioPath, "\xFF\xFB\x90\x00audio");

        $item = [
            'id' => $id,
            'originalFilename' => 'talk_ca',
            'language' => 'ca',
            'tmpAudioPath' => $audioPath,
        ];
        $this->bulkQueue->create([$item]);
        return $item;
    }

    /**
     * @param array{result: string, message?: string} $orchestratorResult
     */
    private function processor(array $orchestratorResult, ?callable $waitForCompletion = null): BulkItemProcessor
    {
        $fakeOrchestrator = new class ($orchestratorResult) {
            public function __construct(private array $result) {}
            public function run(): array
            {
                return $this->result;
            }
        };

        $launcher = new BackgroundJobLauncher('/srv/scripts', 'test-key', function () {});

        return new BulkItemProcessor(
            bulkQueue: $this->bulkQueue,
            jobManager: $this->jobManager,
            orchestrator: $fakeOrchestrator,
            launcher: $launcher,
            translationState: new TranslationJobState($this->jobManager),
            waitForCompletion: $waitForCompletion ?? function (): array {
                return ['success' => true];
            },
        );
    }

    public function test_pipeline_transcribed_marks_item_done_and_saves_both_vtts(): void
    {
        $item = $this->seedQueueItem();
        $processor = $this->processor(['result' => 'pipeline_transcribed'], function (): array {
            file_put_contents($this->jobManager->draftVttPath(), "WEBVTT\n\n");
            file_put_contents($this->jobManager->draftVttPathForLang('en'), "WEBVTT EN\n\n");
            return ['success' => true];
        });

        $processor->processNext();

        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertSame('done', $snap['items'][0]['status']);
        $this->assertTrue(is_file($this->jobsDir . '/bulk-output/item-1_EN.vtt'));
        $this->assertTrue(is_file($this->jobsDir . '/bulk-output/item-1_SRC.vtt'));
        $this->assertFalse($this->jobManager->exists());
    }

    public function test_orchestrator_error_marks_item_failed(): void
    {
        $this->seedQueueItem();
        $processor = $this->processor(['result' => 'error', 'message' => 'Format d\'àudio no reconegut.']);

        $processor->processNext();

        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertSame('failed', $snap['items'][0]['status']);
        $this->assertStringContainsString('Format d\'àudio', $snap['items'][0]['reason']);
        $this->assertFalse($this->jobManager->exists());
    }

    public function test_non_english_without_en_vtt_marks_failed_not_source_vtt(): void
    {
        $this->seedQueueItem();
        $processor = $this->processor(['result' => 'pipeline_transcribed'], function (): array {
            file_put_contents($this->jobManager->draftVttPath(), "WEBVTT source only\n\n");
            return ['success' => true];
        });

        $processor->processNext();

        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertSame('failed', $snap['items'][0]['status']);
        $this->assertFalse(is_file($this->jobsDir . '/bulk-output/item-1_EN.vtt'));
    }
}
