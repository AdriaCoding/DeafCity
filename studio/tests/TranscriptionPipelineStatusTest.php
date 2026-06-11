<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;
use Studio\TranscriptionPipelineStatus;

class TranscriptionPipelineStatusTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-tps-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->createBaseJob();
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

    private function createBaseJob(): void
    {
        mkdir($this->jobsDir . '/current', 0777, true);
        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'job_type'          => 'transcription',
            'subtitle_language' => 'ca',
            'original_filename' => 'talk',
            'intake_mode'       => 'generate',
        ]));
        file_put_contents($this->jobsDir . '/current/transcription.json', json_encode(['status' => 'pending']));
    }

    public function test_transcribing_when_no_draft_vtt(): void
    {
        $this->assertSame('transcribing', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translating_when_draft_vtt_exists_and_translation_pending(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n");
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'pending',
            'languages' => ['en' => ['status' => 'pending']],
        ]));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translating_when_translation_running(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n");
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'running',
            'languages' => ['en' => ['status' => 'running']],
        ]));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translating_when_no_translation_json_yet(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n");

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translation_error_when_english_failed(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n");
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'done',
            'languages' => ['en' => ['status' => 'error', 'message' => 'Gemini timeout']],
        ]));

        $this->assertSame('translation_error', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_download_ready_when_both_vtts_exist_and_translation_done(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.vtt',    "WEBVTT\n");
        file_put_contents($this->jobsDir . '/current/draft_en.vtt', "WEBVTT\n");
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'done',
            'languages' => ['en' => ['status' => 'done']],
        ]));

        $this->assertSame('download_ready', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_download_ready_when_english_source_and_draft_vtt_exists(): void
    {
        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'job_type'          => 'transcription',
            'subtitle_language' => 'en',
            'original_filename' => 'talk',
            'intake_mode'       => 'generate',
        ]));
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n");

        $this->assertSame('download_ready', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translation_error_when_en_vtt_absent_despite_done_status(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n");
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'done',
            'languages' => ['en' => ['status' => 'error', 'message' => 'x']],
        ]));

        $this->assertSame('translation_error', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }
}
