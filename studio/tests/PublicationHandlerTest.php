<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\JobManager;
use Studio\PublicationHandler;
use Studio\StudioConfig;
use Studio\UploadedFile;
use Studio\VimeoClient;

class PublicationHandlerTest extends TestCase
{
    private string $baseDir;
    private string $jobsDir;
    private string $captionsDir;
    private string $catalogFile;
    private JobManager $manager;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/pub-test-' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $this->jobsDir = $this->baseDir . '/jobs';
        mkdir($this->jobsDir, 0777, true);
        $this->captionsDir = $this->baseDir . '/captions';
        mkdir($this->captionsDir, 0777, true);
        $this->catalogFile = $this->baseDir . '/catalog.json';

        $this->manager = new JobManager($this->jobsDir);
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_full_success_copies_captions_writes_catalog_deletes_job(): void
    {
        $this->createJob();
        file_put_contents($this->manager->draftVttPath(), "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");
        file_put_contents($this->manager->draftVttPathForLang('en'), "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHello\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->exactly(2))->method('uploadAndActivateTextTrack');

        $handler = $this->makeHandler($vimeo);
        $result = $handler->handle();

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['vimeoWarnings']);
        $this->assertFileExists($this->captionsDir . '/111222333.es.vtt');
        $this->assertFileExists($this->captionsDir . '/111222333.en.vtt');
        $this->assertFalse($this->manager->exists());

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertCount(1, $catalog['videos']);
        $entry = $catalog['videos'][0];
        $this->assertSame('lse_111222333', $entry['id']);
        $this->assertSame('111222333', $entry['vimeo_id']);
        $this->assertSame('lse', $entry['sign_language']);
        $this->assertSame('2020-valencia', $entry['edition']);
        $this->assertSame(['humor'], $entry['tags']);
        $this->assertCount(2, $entry['captions']);
    }

    public function test_vimeo_upload_failure_still_writes_catalog_and_deletes_job(): void
    {
        $this->createJob();
        file_put_contents($this->manager->draftVttPath(), "WEBVTT\n\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->method('uploadAndActivateTextTrack')->willThrowException(new \RuntimeException('upload failed'));

        $handler = $this->makeHandler($vimeo);
        $result = $handler->handle();

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['vimeoWarnings']);
        $this->assertFalse($this->manager->exists());
        $this->assertFileExists($this->catalogFile);
    }

    public function test_overwrites_existing_catalog_entry_with_same_vimeo_id(): void
    {
        file_put_contents($this->catalogFile, json_encode([
            'videos' => [
                ['id' => 'lse_111222333', 'vimeo_id' => '111222333', 'title' => 'Old', 'tags' => [], 'captions' => []],
                ['id' => 'lsm_999888777', 'vimeo_id' => '999888777', 'title' => 'Other', 'tags' => [], 'captions' => []],
            ],
        ]));
        $this->createJob();
        file_put_contents($this->manager->draftVttPath(), "WEBVTT\n\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);

        $handler = $this->makeHandler($vimeo);
        $result = $handler->handle();

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertCount(2, $catalog['videos']);
        $ids = array_column($catalog['videos'], 'vimeo_id');
        $this->assertContains('111222333', $ids);
        $this->assertContains('999888777', $ids);
        $updated = array_values(array_filter($catalog['videos'], fn($v) => $v['vimeo_id'] === '111222333'))[0];
        $this->assertSame('Test Video', $updated['title']);
    }

    public function test_skips_missing_translation_files(): void
    {
        $this->createJob();
        file_put_contents($this->manager->draftVttPath(), "WEBVTT\n\n");
        // no draft_en.vtt

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->once())->method('uploadAndActivateTextTrack');

        $handler = $this->makeHandler($vimeo);
        $handler->handle();

        $this->assertFileExists($this->captionsDir . '/111222333.es.vtt');
        $this->assertFileDoesNotExist($this->captionsDir . '/111222333.en.vtt');
    }

    private function createJob(): void
    {
        $vttPath = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vttPath, "WEBVTT\n\n");
        $this->manager->create([
            'vimeo_id' => '111222333',
            'video_title' => 'Test Video',
            'sign_language' => 'lse',
            'edition' => '2020-valencia',
            'subtitle_language' => 'es',
            'tags' => ['humor'],
            'step' => 'publication',
        ], new UploadedFile($vttPath, 'draft.vtt'));
    }

    private function makeHandler(VimeoClient $vimeo): PublicationHandler
    {
        return new PublicationHandler(
            $vimeo,
            $this->manager,
            $this->config,
            $this->captionsDir,
            $this->catalogFile,
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
