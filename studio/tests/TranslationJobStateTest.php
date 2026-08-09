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
        $this->jobManager->createWithContent(
            [
                'vimeo_id' => '123456789',
                'video_title' => 'Test Video',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'subtitle_language' => 'es',
                'step' => 'translation',
            ],
            file_get_contents($vttPath)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    public function test_initiate_with_no_targets_marks_job_done_immediately(): void
    {
        $this->state->initiate([], 'en');

        $this->assertSame('done', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'done', 'languages' => [], 'master' => 'en'], $this->state->read());
    }

    public function test_initiate_writes_pending_state_for_all_target_languages(): void
    {
        $this->state->initiate(['en', 'fr'], 'en');

        $this->assertSame('pending', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('en'));
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('fr'));
    }

    public function test_markRunning_updates_top_level_status(): void
    {
        $this->state->initiate(['en'], 'en');
        $this->state->markRunning();

        $this->assertSame('running', $this->state->getTopLevelStatus());
    }

    public function test_markLanguageRunning_sets_per_language_status(): void
    {
        $this->state->initiate(['en', 'fr'], 'en');
        $this->state->markRunning();
        $this->state->markLanguageRunning('en');

        $this->assertSame(['status' => 'running'], $this->state->getLanguageStatus('en'));
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('fr'));
        $this->assertSame('running', $this->state->getTopLevelStatus());
    }

    public function test_markLanguageDone_marks_language_and_resolves_top_level_when_all_done(): void
    {
        $this->state->initiate(['en', 'fr'], 'en');
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
        $this->state->initiate(['en', 'fr'], 'en');
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
        $this->state->initiate(['en', 'fr'], 'en');
        $this->state->markRunning();
        $this->state->markLanguageDone('en');
        $this->state->markLanguageError('fr', 'Translation error: model timeout');

        $this->state->resetLanguage('fr');

        $this->assertSame('running', $this->state->getTopLevelStatus());
        $this->assertSame(['status' => 'pending'], $this->state->getLanguageStatus('fr'));
        $this->assertSame(['status' => 'done'], $this->state->getLanguageStatus('en'));
    }

    public function test_resetLanguage_clears_stale_markSaved_addendum(): void
    {
        $this->state->initiate(['en', 'fr'], 'en');
        $this->state->markRunning();
        $this->state->markLanguageDone('en');
        $this->state->markLanguageDone('fr');
        $this->state->markSaved(['en', 'fr'], []);

        $this->state->resetLanguage('fr');

        $data = $this->state->read();
        $this->assertArrayNotHasKey('savedLangs', $data);
        $this->assertArrayNotHasKey('errorLangs', $data);
        $this->assertSame('running', $data['status']);
    }

    public function test_markSaved_records_status_and_lang_lists(): void
    {
        $this->state->initiate(['en', 'fr'], 'en');
        $this->state->markRunning();
        $this->state->markLanguageDone('en');
        $this->state->markLanguageError('fr', 'boom');

        $this->state->markSaved(['en'], ['fr']);

        $data = $this->state->read();
        $this->assertSame('saved', $data['status']);
        $this->assertSame(['en'], $data['savedLangs']);
        $this->assertSame(['fr'], $data['errorLangs']);
        // The per-language statuses from the worker run are untouched.
        $this->assertSame(['status' => 'done'], $data['languages']['en']);
    }

    public function test_markLanguageReviewed_updates_done_language(): void
    {
        $this->state->initiate(['en'], 'en');
        $this->state->markRunning();
        $this->state->markLanguageDone('en');

        $this->state->markLanguageReviewed('en');

        $this->assertSame(['status' => 'reviewed'], $this->state->getLanguageStatus('en'));
    }

    public function test_read_returns_full_state(): void
    {
        $this->state->initiate(['en'], 'en');
        $this->state->markRunning();
        $this->state->markLanguageDone('en');

        $data = $this->state->read();

        $this->assertSame('done', $data['status']);
        $this->assertSame(['status' => 'done'], $data['languages']['en']);
    }

    public function test_is_stale_for_returns_false_when_master_matches(): void
    {
        $this->state->initiate(['fr'], 'es');

        $this->assertFalse($this->state->isStaleFor('es'));
    }

    public function test_is_stale_for_returns_true_when_master_changed(): void
    {
        $this->state->initiate(['fr'], 'es');

        $this->assertTrue($this->state->isStaleFor('en'));
    }

    public function test_is_stale_for_returns_true_for_legacy_state_without_master_field(): void
    {
        // Simulate a job file written before the 'master' field existed.
        file_put_contents($this->jobManager->translationStatePath(), json_encode([
            'status' => 'saved',
            'languages' => [],
            'savedLangs' => ['fr'],
        ]));

        $this->assertTrue($this->state->isStaleFor('es'));
    }

    public function test_cancel_removes_the_job_directory(): void
    {
        $this->state->initiate(['fr'], 'es');
        $this->assertTrue($this->jobManager->exists());

        $this->state->cancel();

        $this->assertFalse($this->jobManager->exists());
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
