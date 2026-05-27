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

        $draftContent = file_get_contents($this->jobsDir . '/current/draft.vtt');
        $this->assertStringContainsString('Original', $draftContent);

        $job = $this->jobManager->read();
        $this->assertSame('subtitle-editor', $job['step']);
    }

    public function test_save_draft_overwrites_master_vtt_without_advancing_step(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 3.0, 'text' => 'Updated cue', 'opaque' => ''],
        ];

        $result = $this->handler->handle($cues);

        $this->assertTrue($result['ok']);
        $this->assertArrayNotHasKey('translate', $result);

        $writtenContent = file_get_contents($this->jobsDir . '/current/draft.vtt');
        $this->assertStringContainsString('Updated cue', $writtenContent);

        $job = $this->jobManager->read();
        $this->assertSame('subtitle-editor', $job['step']);
    }

    public function test_save_and_translate_advances_step_and_returns_translate_flag(): void
    {
        $cues = [
            ['start' => 0.0, 'end' => 3.0, 'text' => 'Updated cue', 'opaque' => ''],
        ];

        $result = $this->handler->handleRawJson(json_encode([
            'cues' => $cues,
            'translate' => true,
        ]));

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['translate']);

        $job = $this->jobManager->read();
        $this->assertSame('translation', $job['step']);
    }

    public function test_translation_review_saves_to_language_specific_path_without_step_change(): void
    {
        $enPath = $this->jobManager->draftVttPathForLang('en');
        file_put_contents($enPath, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nEnglish original\n");

        $cues = [
            ['start' => 0.0, 'end' => 3.0, 'text' => 'English updated', 'opaque' => ''],
        ];

        $result = $this->handler->handle($cues, ['lang' => 'en']);

        $this->assertTrue($result['ok']);

        $writtenContent = file_get_contents($enPath);
        $this->assertStringContainsString('English updated', $writtenContent);

        $masterContent = file_get_contents($this->jobsDir . '/current/draft.vtt');
        $this->assertStringContainsString('Original', $masterContent);

        $job = $this->jobManager->read();
        $this->assertSame('subtitle-editor', $job['step']);
    }

    public function test_integrity_errors_rejected_in_translation_review_mode(): void
    {
        $enPath = $this->jobManager->draftVttPathForLang('en');
        file_put_contents($enPath, "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nEnglish original\n");

        $cues = [
            ['start' => 0.0, 'end' => 5.0, 'text' => 'A', 'opaque' => ''],
            ['start' => 3.0, 'end' => 7.0, 'text' => 'B', 'opaque' => ''],
        ];

        $result = $this->handler->handle($cues, ['lang' => 'en']);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertArrayHasKey('cueErrors', $result);

        $writtenContent = file_get_contents($enPath);
        $this->assertStringContainsString('English original', $writtenContent);
    }
}
