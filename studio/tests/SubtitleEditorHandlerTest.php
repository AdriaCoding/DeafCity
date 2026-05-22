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

        // Bootstrap a current job
        $currentDir = $this->jobsDir . '/current';
        mkdir($currentDir);
        file_put_contents($currentDir . '/job.json', json_encode([
            'step' => 'subtitle-editor',
            'vimeo_id' => '123456',
        ]) . "\n");
        file_put_contents($currentDir . '/draft.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nOriginal\n");
    }

    public function test_malformed_json_does_not_write_file(): void
    {
        $result = $this->handler->handleRawJson('not json at all');

        $this->assertFalse($result['ok']);

        $draftContent = file_get_contents($this->jobsDir . '/current/draft.vtt');
        $this->assertStringContainsString('Original', $draftContent);
    }

    public function test_overlapping_cues_do_not_write_file_and_return_errors(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 5.0, 'text' => 'A', 'opaque' => ''],
            ['start' => 3.0, 'end' => 7.0, 'text' => 'B', 'opaque' => ''],
        ];

        $result = $this->handler->handle($cues);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);

        // draft.vtt must not have changed
        $draftContent = file_get_contents($this->jobsDir . '/current/draft.vtt');
        $this->assertStringContainsString('Original', $draftContent);

        $job = $this->jobManager->read();
        $this->assertSame('subtitle-editor', $job['step']);
    }

    public function test_valid_cues_overwrite_draft_vtt_and_advance_step(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 3.0, 'text' => 'Updated cue', 'opaque' => ''],
        ];

        $result = $this->handler->handle($cues);

        $this->assertTrue($result['ok']);

        $draftPath = $this->jobsDir . '/current/draft.vtt';
        $writtenContent = file_get_contents($draftPath);
        $this->assertStringContainsString('Updated cue', $writtenContent);

        $job = $this->jobManager->read();
        $this->assertSame('translation', $job['step']);
    }
}
