<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;
use Studio\TaggingHandler;
use Studio\UploadedFile;

class TaggingHandlerTest extends TestCase
{
    private string $jobsDir;
    private JobManager $manager;
    private TaggingHandler $handler;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/tagging-test-' . uniqid();
        mkdir($this->jobsDir, 0777, true);
        $this->manager = new JobManager($this->jobsDir);
        $this->handler = new TaggingHandler($this->manager);
        $this->createJob();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
    }

    public function test_rejects_empty_tag_list(): void
    {
        $result = $this->handler->handle(['tags' => []]);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_tags_containing_only_whitespace(): void
    {
        $result = $this->handler->handle(['tags' => ['   ', '']]);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_trims_whitespace_from_tags(): void
    {
        $result = $this->handler->handle(['tags' => ['  humor  ', '  installation  ']]);
        $this->assertTrue($result['ok']);
        $job = $this->manager->read();
        $this->assertSame(['humor', 'installation'], $job['tags']);
    }

    public function test_deduplicates_tags(): void
    {
        $result = $this->handler->handle(['tags' => ['humor', 'humor', 'installation']]);
        $this->assertTrue($result['ok']);
        $job = $this->manager->read();
        $this->assertSame(['humor', 'installation'], $job['tags']);
    }

    public function test_persists_tags_and_advances_step_to_publication(): void
    {
        $result = $this->handler->handle(['tags' => ['humor', 'ciudad']]);
        $this->assertTrue($result['ok']);
        $job = $this->manager->read();
        $this->assertSame(['humor', 'ciudad'], $job['tags']);
        $this->assertSame('publication', $job['step']);
    }

    private function createJob(): void
    {
        $vttPath = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vttPath, "WEBVTT\n\n");
        $this->manager->create([
            'vimeo_id' => '123',
            'video_title' => 'Test',
            'sign_language' => 'lse',
            'edition' => 'valencia-2020',
            'subtitle_language' => 'es',
            'step' => 'tagging',
        ], new UploadedFile($vttPath, 'draft.vtt'));
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
