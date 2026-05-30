<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\IntakeHandler;
use Studio\IntakeSourceDetector;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\VimeoClient;
use Studio\VimeoIdParser;
use Studio\WebVttValidator;

class IntakeHandlerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private StudioConfig $config;
    private VimeoClient $vimeoClient;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-intake-handler-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
        $this->vimeoClient = $this->createMock(VimeoClient::class);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    public function test_requires_intake_file(): void
    {
        $handler = $this->handler();

        $result = $handler->handlePost($this->validPost(), []);

        $this->assertArrayHasKey('intake_file', $result['errors']);
    }

    public function test_detects_vtt_upload_and_creates_job(): void
    {
        $this->vimeoClient->method('getVideo')->willReturn('Test Video');
        $handler = $this->handler();
        $vttPath = $this->writeTemp("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHola\n", 'draft.vtt');

        $result = $handler->handlePost($this->validPost(), [
            'intake_file' => $this->uploadedFile($vttPath, 'draft.vtt'),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['created'] ?? false);
        $this->assertSame('upload', $result['values']['intake_mode']);
        $this->assertFileExists($this->jobsDir . '/current/draft.vtt');
    }

    public function test_detects_audio_upload_and_creates_generate_job(): void
    {
        $this->vimeoClient->method('getVideo')->willReturn('Test Video');
        $handler = $this->handler();
        $audioPath = $this->writeTemp("\xFF\xFB\x90\x00audio", 'interpreter.mp3');

        $result = $handler->handlePost($this->validPost(), [
            'intake_file' => $this->uploadedFile($audioPath, 'interpreter.mp3'),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['created'] ?? false);
        $this->assertSame('generate', $result['values']['intake_mode']);
        $this->assertFileExists($this->jobsDir . '/current/interpreter_audio.mp3');
    }

    private function handler(): IntakeHandler
    {
        return new IntakeHandler(
            new VimeoIdParser(),
            $this->vimeoClient,
            $this->config,
            $this->jobManager,
            new WebVttValidator(),
            new IntakeSourceDetector(),
        );
    }

    /** @return array<string, string> */
    private function validPost(): array
    {
        return [
            'vimeo_input' => '639494119',
            'sign_language' => 'lse',
            'edition' => 'valencia-2020',
            'subtitle_language' => 'es',
        ];
    }

    /** @return array{tmp_name: string, name: string, error: int} */
    private function uploadedFile(string $path, string $name): array
    {
        return [
            'tmp_name' => $path,
            'name' => $name,
            'error' => UPLOAD_ERR_OK,
        ];
    }

    private function writeTemp(string $contents, string $name): string
    {
        $path = $this->jobsDir . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
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
