<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeHandler;
use Studio\BulkIntakeQueue;
use Studio\JobManager;
use Studio\ProcessLock;
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

    public function test_concurrent_launch_is_refused_while_a_launch_is_already_in_flight(): void
    {
        // Simulate a second, near-simultaneous request racing a first one that
        // is still between its bulkQueue->exists() check and its queue->create()
        // call: hold the same launch lock BulkIntakeHandler acquires, and
        // confirm this request is refused rather than also creating a queue
        // and launching a second worker.
        $lock = ProcessLock::acquire($this->bulkQueue->lockFilePath());
        $this->assertNotNull($lock);

        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav'])],
        );

        $this->assertFalse($result['created'] ?? false);
        $this->assertArrayHasKey('_form', $result['errors']);
        $this->assertFalse($this->bulkQueue->exists());
        $this->assertEmpty($this->launched);

        $lock->release();
    }

    public function test_lock_is_released_after_a_successful_launch_so_a_later_request_can_proceed(): void
    {
        $first = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav'])],
        );
        $this->assertTrue($first['created'] ?? false);

        // The launch lock itself must be free again immediately afterward —
        // only the persisted queue file (a separate, legitimate check) should
        // now be what refuses a further concurrent launch.
        $lock = ProcessLock::acquire($this->bulkQueue->lockFilePath());
        $this->assertNotNull($lock);
        $lock->release();
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

    public function test_accepts_bulk_upload_with_subtitle_file(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHello\n");
        $upload = $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav']);
        $upload['name'][1] = 'session_es.vtt';
        $upload['tmp_name'][1] = $vtt;

        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $upload],
        );

        $this->assertTrue($result['created'] ?? false);
        $this->assertTrue($this->bulkQueue->exists());
        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertCount(2, $snap['items']);
        $kinds = array_column($snap['items'], 'kind');
        $this->assertContains('audio', $kinds);
        $this->assertContains('subtitle', $kinds);
    }

    public function test_accepts_subtitle_only_bulk_upload(): void
    {
        $vtt1 = tempnam(sys_get_temp_dir(), 'vtt');
        $vtt2 = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt1, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nOne\n");
        file_put_contents($vtt2, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nTwo\n");
        $upload = [
            'name' => ['talk_ca.vtt', 'session_es.srt'],
            'type' => ['text/vtt', 'application/x-subrip'],
            'tmp_name' => [$vtt1, $vtt2],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [20, 20],
        ];

        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $upload],
        );

        $this->assertTrue($result['created'] ?? false);
        $snap = $this->bulkQueue->statusSnapshot();
        $this->assertCount(2, $snap['items']);
        foreach ($snap['items'] as $item) {
            $this->assertSame('subtitle', $item['kind']);
        }
    }

    public function test_rejects_unknown_extension_in_bulk(): void
    {
        $upload = $this->multiFileUpload(['talk_ca.mp3', 'notes.txt']);
        $upload['name'][1] = 'notes.txt';

        $result = $this->handler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $upload],
        );

        $this->assertArrayHasKey('intake_file', $result['errors']);
        $this->assertFalse($this->bulkQueue->exists());
    }

    // ── allowAudio: false (Polir subtítols bulk mode) ──────────────────────────

    private function shortenHandler(?callable $captureCmd = null): BulkIntakeHandler
    {
        $launcher = new BackgroundJobLauncher('/srv/scripts', 'test-key', $captureCmd ?? function (string $cmd): void {
            $this->launched[] = $cmd;
        });

        return new BulkIntakeHandler(
            studioConfig: $this->config,
            jobManager: $this->jobManager,
            bulkQueue: $this->bulkQueue,
            launcher: $launcher,
            dataDir: dirname($this->jobsDir),
            allowAudio: false,
        );
    }

    public function test_allow_audio_false_accepts_subtitle_only_bulk_and_launches_shorten_script(): void
    {
        $vtt1 = tempnam(sys_get_temp_dir(), 'vtt');
        $vtt2 = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt1, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nOne\n");
        file_put_contents($vtt2, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nTwo\n");
        $upload = [
            'name' => ['talk_ca.vtt', 'session_es.srt'],
            'type' => ['text/vtt', 'application/x-subrip'],
            'tmp_name' => [$vtt1, $vtt2],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [20, 20],
        ];

        $result = $this->shortenHandler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $upload],
        );

        $this->assertTrue($result['created'] ?? false);
        $this->assertNotEmpty($this->launched);
        $this->assertStringContainsString('run_shorten_bulk.sh', $this->launched[0]);
    }

    public function test_allow_audio_false_rejects_audio_files(): void
    {
        $result = $this->shortenHandler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav'])],
        );

        $this->assertArrayHasKey('intake_file', $result['errors']);
        $this->assertFalse($this->bulkQueue->exists());
    }

    public function test_allow_audio_false_rejects_mixed_audio_and_subtitle(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHello\n");
        $upload = $this->multiFileUpload(['talk_ca.mp3', 'session_es.wav']);
        $upload['name'][1] = 'session_es.vtt';
        $upload['tmp_name'][1] = $vtt;

        $result = $this->shortenHandler()->handlePost(
            ['bulk_languages' => ['ca', 'es']],
            ['intake_file' => $upload],
        );

        $this->assertArrayHasKey('intake_file', $result['errors']);
        $this->assertFalse($this->bulkQueue->exists());
    }
}
