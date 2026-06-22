<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionFileIntegrityChecker;
use Studio\JobManager;
use Studio\SubtitleEditorHandler;
use Studio\VttParser;

class SubtitleEditorHandlerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private SubtitleEditorHandler $handler;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-handler-' . uniqid();
        mkdir($this->jobsDir);

        $this->jobManager = new JobManager($this->jobsDir);
        $this->handler = new SubtitleEditorHandler(
            new VttParser(),
            new CaptionFileIntegrityChecker(),
            $this->jobManager,
        );

        $currentDir = $this->jobsDir . '/current';
        mkdir($currentDir);
        file_put_contents($currentDir . '/job.json', json_encode([
            'job_type' => 'transcription',
            'subtitle_language' => 'ca',
        ]) . "\n");
        file_put_contents($currentDir . '/draft.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nOriginal\n");
    }

    public function test_handleForFilePath_writes_valid_cues_to_catalog_file_path(): void
    {
        $vttPath = sys_get_temp_dir() . '/catalog-caption-' . uniqid() . '.vtt';
        file_put_contents($vttPath, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nOriginal\n");

        $cues = [
            ['start' => 0.0, 'end' => 3.0, 'text' => 'Updated cue', 'opaque' => ''],
        ];

        $result = $this->handler->handleForFilePath($vttPath, $cues);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Updated cue', file_get_contents($vttPath));

        unlink($vttPath);
    }

    public function test_handleForFilePath_rejects_integrity_errors_without_modifying_file(): void
    {
        $vttPath = sys_get_temp_dir() . '/catalog-caption-' . uniqid() . '.vtt';
        file_put_contents($vttPath, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nOriginal\n");

        $cues = [
            ['start' => 0.0, 'end' => 5.0, 'text' => 'A', 'opaque' => ''],
            ['start' => 3.0, 'end' => 7.0, 'text' => 'B', 'opaque' => ''],
        ];

        $result = $this->handler->handleForFilePath($vttPath, $cues);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Original', file_get_contents($vttPath));

        unlink($vttPath);
    }
}
