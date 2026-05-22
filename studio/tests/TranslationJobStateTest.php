<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;
use Studio\TranslationJobState;
use Studio\UploadedFile;

class TranslationJobStateTest extends TestCase
{
    private string $jobsDir;
    private JobManager $jobManager;
    private TranslationJobState $state;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-translation-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->jobManager = new JobManager($this->jobsDir);
        $this->state = new TranslationJobState($this->jobManager);

        $vttPath = $this->jobsDir . '/upload.vtt';
        file_put_contents($vttPath, 'WEBVTT');
        $this->jobManager->create(
            [
                'vimeo_id' => '123456789',
                'video_title' => 'Test Video',
                'sign_language' => 'lse',
                'edition' => 'valencia-2020',
                'subtitle_language' => 'es',
                'step' => 'translation',
            ],
            new UploadedFile($vttPath, 'draft.vtt')
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    public function test_initiate_with_no_targets_marks_job_done_immediately(): void
    {
        $this->state->initiate([]);

        $this->assertSame('done', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'done', 'languages' => []], $this->state->read());
    }

    public function test_initiate_writes_pending_state_for_all_target_languages(): void
    {
        $this->state->initiate(['en', 'fr']);

        $this->assertSame('pending', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('en'));
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('fr'));
    }

    public function test_markRunning_updates_top_level_status(): void
    {
        $this->state->initiate(['en']);
        $this->state->markRunning();

        $this->assertSame('running', $this->state->getTopLevelStatus());
    }

    public function test_markLanguageRunning_sets_per_language_status(): void
    {
        $this->state->initiate(['en', 'fr']);
        $this->state->markRunning();
        $this->state->markLanguageRunning('en');

        $this->assertSame(['status' => 'running'], $this->state->getLanguageStatus('en'));
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('fr'));
        $this->assertSame('running', $this->state->getTopLevelStatus());
    }

    public function test_markLanguageDone_marks_language_and_resolves_top_level_when_all_done(): void
    {
        $this->state->initiate(['en', 'fr']);
        $this->state->markRunning();
        $this->state->markLanguageDone('en');

        $this->assertSame('running', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'done'], $this->state->getLanguageStatus('en'));

        $this->state->markLanguageDone('fr');

        $this->assertSame('done', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'done'], $this->state->getLanguageStatus('fr'));
    }

    public function test_markLanguageError_marks_language_and_resolves_top_level_when_all_resolved(): void
    {
        $this->state->initiate(['en', 'fr']);
        $this->state->markRunning();
        $this->state->markLanguageDone('en');
        $this->state->markLanguageError('fr', 'Translation error: model timeout');

        $this->assertSame('done', $this->state->getTopLevelStatus());
        $this->assertSame(
            ['status' => 'error', 'message' => 'Translation error: model timeout'],
            $this->state->getLanguageStatus('fr')
        );
    }

    public function test_resetLanguage_resets_single_language_for_retry(): void
    {
        $this->state->initiate(['en', 'fr']);
        $this->state->markRunning();
        $this->state->markLanguageDone('en');
        $this->state->markLanguageError('fr', 'Translation error: model timeout');

        $this->state->resetLanguage('fr');

        $this->assertSame('running', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('fr'));
        $this->assertSame(['status' => 'done'], $this->state->getLanguageStatus('en'));
    }

    public function test_markLanguageReviewed_updates_done_language(): void
    {
        $this->state->initiate(['en']);
        $this->state->markRunning();
        $this->state->markLanguageDone('en');

        $this->state->markLanguageReviewed('en');

        $this->assertSame(['status' => 'reviewed'], $this->state->getLanguageStatus('en'));
    }

    public function test_read_returns_full_state(): void
    {
        $this->state->initiate(['en']);
        $this->state->markRunning();
        $this->state->markLanguageDone('en');

        $data = $this->state->read();

        $this->assertSame('done', $data['status']);
        $this->assertSame(['status' => 'done'], $data['languages']['en']);
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
