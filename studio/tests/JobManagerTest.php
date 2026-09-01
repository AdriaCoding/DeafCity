<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;

class JobManagerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $manager;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-jobs-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->manager = new JobManager($this->jobsDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    public function test_exists_is_false_when_no_job(): void
    {
        $this->assertFalse($this->manager->exists());
    }

    public function test_create_writes_job_json_and_draft(): void
    {
        $this->manager->createWithContent(
            [
                'vimeo_id' => '123456789',
                'video_title' => 'Test Video',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'subtitle_language' => 'es',
                'step' => 'subtitle-editor',
            ],
            "1\n00:00:01,000 --> 00:00:02,000\nCue\n"
        );

        $this->assertTrue($this->manager->exists());
        $job = $this->manager->read();
        $this->assertSame('123456789', $job['vimeo_id']);
        $this->assertSame('Test Video', $job['video_title']);
        $this->assertFileExists($this->jobsDir . '/current/draft.srt');
    }

    public function test_update_merges_fields_into_job_json(): void
    {
        $this->createSampleJob();
        $this->manager->update(['step' => 'tagging']);

        $job = $this->manager->read();
        $this->assertSame('tagging', $job['step']);
        $this->assertSame('123456789', $job['vimeo_id']);
    }

    public function test_cancel_removes_current_job_directory(): void
    {
        $this->createSampleJob();
        $this->manager->cancel();

        $this->assertFalse($this->manager->exists());
        $this->assertDirectoryDoesNotExist($this->jobsDir . '/current');
    }

    public function test_colliding_create_is_rejected_atomically_even_if_precheck_missed_it(): void
    {
        // Simulate two concurrent requests both passing the exists() check
        // before either has created the directory: create the directory
        // out-of-band (as a racing second process would), bypassing this
        // manager's own exists() pre-check, then assert createWithContent()
        // still refuses rather than silently overwriting the winner's job.
        mkdir($this->jobsDir . '/current', 0775, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ja hi ha una feina en curs.');

        $this->manager->createWithContent(
            [
                'vimeo_id' => '999',
                'video_title' => 'Colliding Job',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'subtitle_language' => 'es',
                'step' => 'subtitle-editor',
            ],
            "1\n00:00:01,000 --> 00:00:02,000\nCue\n"
        );
    }

    public function test_colliding_create_does_not_clobber_the_winning_jobs_files(): void
    {
        // The winner of the race writes its job.json first...
        mkdir($this->jobsDir . '/current', 0775, true);
        file_put_contents($this->jobsDir . '/current/job.json', json_encode(['vimeo_id' => 'winner']));

        try {
            $this->manager->createWithContent(
                ['vimeo_id' => 'loser', 'video_title' => 'Loser', 'sign_language' => 'lse', 'edition' => '2020-valencia', 'subtitle_language' => 'es', 'step' => 'subtitle-editor'],
                "1\n00:00:01,000 --> 00:00:02,000\nCue\n"
            );
            $this->fail('Expected the colliding create to throw.');
        } catch (\RuntimeException) {
            // expected
        }

        // ...and the loser must never have touched the winner's job.json or draft.
        $this->assertSame('winner', json_decode(file_get_contents($this->jobsDir . '/current/job.json'), true)['vimeo_id']);
        $this->assertFileDoesNotExist($this->jobsDir . '/current/draft.srt');
    }

    public function test_draftPathForLang_returns_expected_path(): void
    {
        $this->createSampleJob();

        $this->assertSame(
            $this->jobsDir . '/current/draft_en.srt',
            $this->manager->draftPathForLang('en')
        );
    }

    public function test_translationStatePath_returns_expected_path(): void
    {
        $this->createSampleJob();

        $this->assertSame(
            $this->jobsDir . '/current/translation.json',
            $this->manager->translationStatePath()
        );
    }

    private function createSampleJob(): void
    {
        $this->manager->createWithContent(
            [
                'vimeo_id' => '123456789',
                'video_title' => 'Test Video',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'subtitle_language' => 'es',
                'step' => 'subtitle-editor',
            ],
            "1\n00:00:01,000 --> 00:00:02,000\nCue\n"
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
