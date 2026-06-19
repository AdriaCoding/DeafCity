<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\ActiveJobBanner;
use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeQueue;
use Studio\Container;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\UploadedFile;

class ActiveJobBannerTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/studio-job-banner-' . uniqid();
        mkdir($this->dataDir, 0777, true);
        mkdir($this->dataDir . '/jobs', 0777, true);
        file_put_contents($this->dataDir . '/studio-config.json', '{}');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dataDir);
    }

    public function test_returns_null_when_no_job(): void
    {
        $this->assertNull(ActiveJobBanner::resolve($this->container()));
    }

    public function test_bulk_queue_takes_precedence(): void
    {
        $queue = new BulkIntakeQueue($this->dataDir . '/jobs');
        $queue->create([
            [
                'id' => 'item-1',
                'originalFilename' => 'a.mp3',
                'language' => 'ca',
                'tmpAudioPath' => $this->dataDir . '/a.mp3',
            ],
        ]);

        $banner = ActiveJobBanner::resolve($this->container());

        $this->assertNotNull($banner);
        $this->assertSame('Transcripció en massa', $banner['title']);
        $this->assertSame('?action=bulk-progress', $banner['resumeUrl']);
    }

    public function test_transcription_job_links_to_resume_job(): void
    {
        mkdir($this->dataDir . '/jobs/current', 0777, true);
        file_put_contents($this->dataDir . '/jobs/current/job.json', json_encode([
            'job_type' => 'transcription',
            'original_filename' => 'entrevista.mp3',
            'subtitle_language' => 'ca',
            'step' => 'transcription',
        ], JSON_THROW_ON_ERROR));

        $banner = ActiveJobBanner::resolve($this->container());

        $this->assertSame('entrevista.mp3', $banner['title']);
        $this->assertSame('?action=resume-job', $banner['resumeUrl']);
    }

    public function test_pipeline_job_links_to_current_step(): void
    {
        $vttPath = $this->dataDir . '/draft.vtt';
        file_put_contents($vttPath, "WEBVTT\n\n");
        (new JobManager($this->dataDir . '/jobs'))->create(
            [
                'video_title' => 'El meu vídeo',
                'edition' => '2020-valencia',
                'step' => 'tagging',
                'intake_mode' => 'upload',
            ],
            new UploadedFile($vttPath, 'draft.vtt'),
        );

        $banner = ActiveJobBanner::resolve($this->container());

        $this->assertSame('El meu vídeo', $banner['title']);
        $this->assertSame('?action=tagging', $banner['resumeUrl']);
    }

    private function container(): Container
    {
        return new Container(
            dataDir: $this->dataDir,
            baseUrl: '/studio/',
            jobManager: new JobManager($this->dataDir . '/jobs'),
            studioConfig: new StudioConfig($this->dataDir . '/studio-config.json'),
            launcher: new BackgroundJobLauncher(__DIR__ . '/../scripts', ''),
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
