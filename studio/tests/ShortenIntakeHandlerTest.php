<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\JobManager;
use Studio\ShortenIntakeHandler;
use Studio\StudioConfig;
use Studio\TranslationJobState;

class ShortenIntakeHandlerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-shorten-intake-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
    }

    protected function tearDown(): void
    {
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

    private function audioUpload(string $name = 'audio.mp3'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($tmp, "\xFF\xFB\x90\x00audio");
        return ['tmp_name' => $tmp, 'name' => $name, 'error' => UPLOAD_ERR_OK, 'size' => 5];
    }

    private function vttUpload(string $name = 'draft_ca.vtt', string $content = "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHello\n"): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($tmp, $content);
        return ['tmp_name' => $tmp, 'name' => $name, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    private function srtUpload(string $name = 'draft_ca.srt', string $content = "1\n00:00:01,000 --> 00:00:04,000\nHello\n"): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'srt');
        file_put_contents($tmp, $content);
        return ['tmp_name' => $tmp, 'name' => $name, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    /**
     * @param callable|null $captureCmd
     */
    private function handler(?callable $captureCmd = null): ShortenIntakeHandler
    {
        $launcher = new BackgroundJobLauncher(
            '/srv/scripts',
            'test-gemini-key',
            $captureCmd ?? function ($cmd) {},
        );

        return new ShortenIntakeHandler(
            studioConfig: $this->config,
            jobManager: $this->jobManager,
            launcher: $launcher,
            translationState: new TranslationJobState($this->jobManager),
        );
    }

    public function test_rejects_when_no_file_uploaded(): void
    {
        $result = $this->handler()->handlePost(['subtitle_language' => 'ca'], []);
        $this->assertArrayHasKey('intake_file', $result['errors']);
    }

    public function test_rejects_when_subtitle_language_is_empty(): void
    {
        $result = $this->handler()->handlePost([], ['intake_file' => $this->vttUpload()]);
        $this->assertArrayHasKey('subtitle_language', $result['errors']);
    }

    public function test_rejects_when_subtitle_language_not_in_config(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'zz'],
            ['intake_file' => $this->vttUpload()]
        );
        $this->assertArrayHasKey('subtitle_language', $result['errors']);
    }

    public function test_rejects_if_job_already_exists(): void
    {
        mkdir($this->jobsDir . '/current', 0777, true);
        file_put_contents($this->jobsDir . '/current/job.json', json_encode(['step' => 'x']));

        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->vttUpload()]
        );
        $this->assertArrayHasKey('_form', $result['errors']);
    }

    public function test_rejects_audio_files(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->audioUpload()]
        );
        $this->assertArrayHasKey('intake_file', $result['errors']);
        $this->assertFalse($this->jobManager->exists());
    }

    public function test_rejects_unknown_file_extension(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($tmp, 'hello');
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => ['tmp_name' => $tmp, 'name' => 'notes.txt', 'error' => UPLOAD_ERR_OK, 'size' => 5]]
        );
        $this->assertArrayHasKey('intake_file', $result['errors']);
    }

    public function test_vtt_upload_creates_shorten_job_and_launches_revision_without_targets(): void
    {
        $launched = null;
        $result = $this->handler(function ($cmd) use (&$launched) { $launched = $cmd; })->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->vttUpload('talk_ca.vtt')]
        );

        $this->assertTrue($result['created'] ?? false);
        $job = $this->jobManager->read();
        $this->assertSame('shorten', $job['job_type']);
        $this->assertSame('ca', $job['subtitle_language']);
        $this->assertSame('talk_ca', $job['original_filename']);
        $this->assertFileExists($this->jobManager->draftVttPath());

        $this->assertNotNull($launched);
        $this->assertStringContainsString('run_revise.sh', $launched);
        $this->assertStringContainsString(escapeshellarg(''), $launched);

        $state = json_decode($this->jobManager->readTranslationState() ?? '{}', true);
        $this->assertSame('done', $state['status'] ?? null);
        $this->assertSame([], $state['languages'] ?? null);
    }

    public function test_srt_upload_converts_to_vtt(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->srtUpload('talk_ca.srt')]
        );

        $this->assertTrue($result['created'] ?? false);
        $this->assertStringStartsWith("WEBVTT\n", (string) file_get_contents($this->jobManager->draftVttPath()));
    }

    public function test_rejects_invalid_vtt_upload(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->vttUpload('bad.vtt', 'not a vtt file')]
        );

        $this->assertArrayHasKey('intake_file', $result['errors']);
        $this->assertFalse($this->jobManager->exists());
    }

    public function test_non_english_source_never_translates(): void
    {
        $result = $this->handler()->handlePost(
            ['subtitle_language' => 'es'],
            ['intake_file' => $this->vttUpload('talk_es.vtt')]
        );

        $this->assertTrue($result['created'] ?? false);
        $state = json_decode($this->jobManager->readTranslationState() ?? '{}', true);
        $this->assertSame('done', $state['status'] ?? null);
        $this->assertSame([], $state['languages'] ?? null);
    }

    public function test_writes_revision_status_pending_before_launch(): void
    {
        $this->handler()->handlePost(
            ['subtitle_language' => 'ca'],
            ['intake_file' => $this->vttUpload()]
        );

        $revisionPath = $this->jobManager->revisionStatePath();
        $this->assertFileExists($revisionPath);
        $revision = json_decode(file_get_contents($revisionPath), true);
        $this->assertSame('pending', $revision['status'] ?? null);
    }
}
