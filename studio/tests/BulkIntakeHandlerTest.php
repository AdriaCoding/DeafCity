<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeHandler;
use Studio\BulkIntakeQueue;
use Studio\JobManager;
use Studio\StudioConfig;

class BulkIntakeHandlerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private BulkIntakeQueue $bulkQueue;
    private StudioConfig $config;
    /** @var list<string> */
    private array $launched = [];

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-bulk-handler-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->bulkQueue = new BulkIntakeQueue($this->jobsDir);
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
        $this->launched = [];
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

    private function handler(): BulkIntakeHandler
    {
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'test-key', function (string $cmd): void {
            $this->launched[] = $cmd;
        });

        return new BulkIntakeHandler(
            studioConfig: $this->config,
            jobManager: $this->jobManager,
            bulkQueue: $this->bulkQueue,
            launcher: $launcher,
            dataDir: dirname($this->jobsDir),
        );
    }

    private function audioUpload(string $name, int $error = UPLOAD_ERR_OK): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($tmp, "\xFF\xFB\x90\x00audio");
        return ['tmp_name' => $tmp, 'name' => $name, 'error' => $error, 'size' => 5];
    }

    private function multiFileUpload(array $names): array
    {
        $upload = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach ($names as $name) {
            $file = $this->audioUpload($name);
            $upload['name'][] = $file['name'];
            $upload['type'][] = 'audio/mpeg';
            $upload['tmp_name'][] = $file['tmp_name'];
            $upload['error'][] = $file['error'];
            $upload['size'][] = $file['size'];
        }
        return $upload;
    }

    public function test_valid_multi_file_post_creates_queue_and_launches_worker(): void
    {
        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav'])],
        );

        $this->assertTrue($result['created'] ?? false);
        $this->assertTrue($this->bulkQueue->exists());
        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertCount(2, $snap['items']);
        $this->assertNotEmpty($this->launched);
        $this->assertStringContainsString('run_bulk.sh', $this->launched[0]);
    }

    public function test_invalid_language_returns_errors_without_queue(): void
    {
        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['zz', 'es']],
            ['intake_file' => $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav'])],
        );

        $this->assertFalse($result['created'] ?? false);
        $this->assertArrayHasKey('_form', $result['errors']);
        $this->assertFalse($this->bulkQueue->exists());
    }

    public function test_rejects_when_single_job_exists(): void
    {
        mkdir($this->jobsDir . '/current', 0777, true);
        file_put_contents($this->jobsDir . '/current/job.json', json_encode(['step' => 'x']));

        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav'])],
        );

        $this->assertArrayHasKey('_form', $result['errors']);
        $this->assertFalse($this->bulkQueue->exists());
    }
}
