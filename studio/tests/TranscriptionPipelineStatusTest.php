<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;
use Studio\StudioConfig;
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

    public function test_transcribing_when_no_draft(): void
    {
        $this->assertSame('transcribing', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_revising_when_revision_pending(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'pending']));

        $this->assertSame('revising', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_revising_when_revision_running(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'running']));

        $this->assertSame('revising', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_revision_error_when_revision_failed(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode([
            'status' => 'error',
            'message' => 'Gemini timeout',
        ]));

        $this->assertSame('revision_error', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_legacy_job_without_revision_status_skips_to_translating(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'pending',
            'languages' => ['en' => ['status' => 'pending']],
        ]));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translating_when_draft_exists_and_translation_pending(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'pending',
            'languages' => ['en' => ['status' => 'pending']],
        ]));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translating_when_translation_running(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'running',
            'languages' => ['en' => ['status' => 'running']],
        ]));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translating_when_no_translation_json_yet(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translation_error_when_english_failed(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'done',
            'languages' => ['en' => ['status' => 'error', 'message' => 'Gemini timeout']],
        ]));

        $this->assertSame('translation_error', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_download_ready_when_both_drafts_exist_and_translation_done(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt',    "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/draft_en.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'done',
            'languages' => ['en' => ['status' => 'done']],
        ]));

        $this->assertSame('download_ready', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_download_ready_when_english_source_and_revision_done(): void
    {
        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'job_type'          => 'transcription',
            'subtitle_language' => 'en',
            'original_filename' => 'talk',
            'intake_mode'       => 'generate',
        ]));
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));

        $this->assertSame('download_ready', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_download_ready_when_english_source_legacy_without_revision_file(): void
    {
        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'job_type'          => 'transcription',
            'subtitle_language' => 'en',
            'original_filename' => 'talk',
            'intake_mode'       => 'generate',
        ]));
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");

        $this->assertSame('download_ready', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_always_skip_translation_goes_straight_to_download_ready_for_non_english(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));

        $status = new TranscriptionPipelineStatus($this->jobManager, alwaysSkipTranslation: true);
        $this->assertSame('download_ready', $status->getState());
    }

    public function test_always_skip_translation_still_reports_revising_while_pending(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'pending']));

        $status = new TranscriptionPipelineStatus($this->jobManager, alwaysSkipTranslation: true);
        $this->assertSame('revising', $status->getState());
    }

    public function test_always_skip_translation_still_reports_revision_error(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'error']));

        $status = new TranscriptionPipelineStatus($this->jobManager, alwaysSkipTranslation: true);
        $this->assertSame('revision_error', $status->getState());
    }

    public function test_default_behavior_unchanged_when_flag_omitted(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));

        $this->assertSame('translating', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_translation_error_when_en_draft_absent_despite_done_status(): void
    {
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));
        file_put_contents($this->jobsDir . '/current/translation.json', json_encode([
            'status'    => 'done',
            'languages' => ['en' => ['status' => 'error', 'message' => 'x']],
        ]));

        $this->assertSame('translation_error', (new TranscriptionPipelineStatus($this->jobManager))->getState());
    }

    public function test_download_ready_when_dialect_source_reduces_to_english(): void
    {
        $configPath = sys_get_temp_dir() . '/studio-tps-config-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $configPath);
        $studioConfig = new StudioConfig($configPath);
        $studioConfig->addInputLanguage('en-us', 'Anglès (EUA)', 'en');

        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'job_type'          => 'transcription',
            'subtitle_language' => 'en-us',
            'original_filename' => 'talk',
            'intake_mode'       => 'generate',
        ]));
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));

        $status = new TranscriptionPipelineStatus($this->jobManager, $studioConfig);
        $this->assertSame('download_ready', $status->getState());

        unlink($configPath);
        @unlink($configPath . '.lock');
    }

    public function test_translating_when_dialect_source_does_not_reduce_to_english(): void
    {
        $configPath = sys_get_temp_dir() . '/studio-tps-config-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $configPath);
        $studioConfig = new StudioConfig($configPath);
        $studioConfig->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'job_type'          => 'transcription',
            'subtitle_language' => 'es-mx',
            'original_filename' => 'talk',
            'intake_mode'       => 'generate',
        ]));
        file_put_contents($this->jobsDir . '/current/draft.srt', "1\n00:00:01,000 --> 00:00:02,000\nHola\n");
        file_put_contents($this->jobsDir . '/current/revision_status.json', json_encode(['status' => 'done']));

        $status = new TranscriptionPipelineStatus($this->jobManager, $studioConfig);
        $this->assertSame('translating', $status->getState());

        unlink($configPath);
        @unlink($configPath . '.lock');
    }
}
