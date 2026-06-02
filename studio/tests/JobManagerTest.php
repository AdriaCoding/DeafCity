<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;
use Studio\UploadedFile;

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

    public function test_create_writes_job_json_and_draft_vtt(): void
    {
        $vttPath = $this->writeVtt('WEBVTT\n\nCue');
        $this->manager->create(
            [
                'vimeo_id' => '123456789',
                'video_title' => 'Test Video',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'subtitle_language' => 'es',
                'step' => 'subtitle-editor',
            ],
            new UploadedFile($vttPath, 'draft.vtt')
        );

        $this->assertTrue($this->manager->exists());
        $job = $this->manager->read();
        $this->assertSame('123456789', $job['vimeo_id']);
        $this->assertSame('Test Video', $job['video_title']);
        $this->assertFileExists($this->jobsDir . '/current/draft.vtt');
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

    public function test_draftVttPathForLang_returns_expected_path(): void
    {
        $this->createSampleJob();

        $this->assertSame(
            $this->jobsDir . '/current/draft_en.vtt',
            $this->manager->draftVttPathForLang('en')
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
        $vttPath = $this->writeVtt('WEBVTT');
        $this->manager->create(
            [
                'vimeo_id' => '123456789',
                'video_title' => 'Test Video',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'subtitle_language' => 'es',
                'step' => 'subtitle-editor',
            ],
            new UploadedFile($vttPath, 'draft.vtt')
        );
    }

    private function writeVtt(string $contents): string
    {
        $path = $this->jobsDir . '/upload-' . uniqid() . '.vtt';
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
