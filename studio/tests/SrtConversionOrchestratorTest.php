<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\JobManager;
use Studio\SrtConversionOrchestrator;
use Studio\UploadedFile;

class SrtConversionOrchestratorTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private ?string $capturedCmd = null;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-srt-conv-orch-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->capturedCmd = null;

        $srtPath = $this->jobsDir . '/intake.srt';
        file_put_contents($srtPath, "1\n00:00:01,000 --> 00:00:04,000\nHola\n");
        $this->jobManager->createWithSrt([
            'vimeo_id' => '123',
            'video_title' => 'Test',
            'sign_language' => 'lse',
            'edition' => '2020-valencia',
            'subtitle_language' => 'es',
            'step' => 'subtitle-editor',
            'intake_mode' => 'upload',
        ], new UploadedFile($srtPath, 'intake.srt'));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    public function test_run_launches_background_conversion_and_returns_loading(): void
    {
        $launcher = new BackgroundJobLauncher(
            __DIR__ . '/../scripts',
            '',
            function (string $cmd): void {
                $this->capturedCmd = $cmd;
            },
        );
        $orchestrator = new SrtConversionOrchestrator($this->jobManager, $launcher);

        $result = $orchestrator->run();

        $this->assertSame('loading', $result['result']);
        $this->assertNotNull($this->capturedCmd);
        $this->assertStringContainsString('run_convert_srt.sh', $this->capturedCmd);
        $this->assertStringContainsString(
            escapeshellarg($this->jobsDir . '/current/intake.srt'),
            $this->capturedCmd,
        );
        $this->assertStringContainsString(
            escapeshellarg($this->jobsDir . '/current/draft.vtt'),
            $this->capturedCmd,
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
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
