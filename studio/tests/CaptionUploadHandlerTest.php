<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionUploadHandler;
use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\VimeoClient;

class CaptionUploadHandlerTest extends TestCase
{
    private string $baseDir;
    private string $captionsDir;
    private string $catalogFile;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/caption-upload-' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $this->captionsDir = $this->baseDir . '/captions';
        mkdir($this->captionsDir, 0777, true);
        $this->catalogFile = $this->baseDir . '/catalog.json';
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');

        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Test',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
            ],
        ]]));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_saves_vtt_file_and_updates_catalog(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->once())->method('uploadAndActivateTextTrack')
            ->with('111', $this->captionsDir . '/111.es.vtt', 'es', 'Spanish');

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['vimeoWarnings']);
        $this->assertFileExists($this->captionsDir . '/111.es.vtt');

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111');
        $this->assertCount(1, $entry['captions']);
        $this->assertSame('es', $entry['captions'][0]['lang']);
        $this->assertSame('111.es.vtt', $entry['captions'][0]['file']);

        $this->assertCount(1, $result['captions']);
        $this->assertSame('es', $result['captions'][0]['lang']);
        $this->assertSame('111.es.vtt', $result['captions'][0]['file']);
    }

    public function test_rejects_invalid_language(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n");

        $result = $this->makeHandler($this->createMock(VimeoClient::class))->handle('111', [[
            'lang' => 'zz',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('llengua', $result['error'] ?? '');
    }

    public function test_replaces_existing_caption_for_same_language(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Test',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']],
            ],
        ]]));
        file_put_contents($this->captionsDir . '/111.es.vtt', "WEBVTT\n\nold\n");

        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nNou\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $vtt,
            'originalName' => 'new.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Nou', file_get_contents($this->captionsDir . '/111.es.vtt'));

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111');
        $this->assertCount(1, $entry['captions']);
    }

    public function test_vimeo_failure_still_saves_file_and_catalog(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->method('uploadAndActivateTextTrack')->willThrowException(new \RuntimeException('upload failed'));

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'en',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['vimeoWarnings']);
        $this->assertFileExists($this->captionsDir . '/111.en.vtt');
    }

    public function test_upload_uses_resolved_vimeo_code_for_dialect(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nSalut\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->once())->method('uploadAndActivateTextTrack')
            ->with('111', $this->captionsDir . '/111.arq.vtt', 'ar', 'Algerian Darija');

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'arq',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
    }

    public function test_empty_uploads_returns_ok_without_changes(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');

        $result = $this->makeHandler($vimeo)->handle('111', []);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['vimeoWarnings']);
    }

    private function makeHandler(VimeoClient $vimeo): CaptionUploadHandler
    {
        return new CaptionUploadHandler(
            $vimeo,
            new CatalogEditor($this->catalogFile),
            $this->config,
            $this->captionsDir,
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
