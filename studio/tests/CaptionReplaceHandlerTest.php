<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionReplaceHandler;
use Studio\CaptionUploadHandler;
use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\VimeoClient;

class CaptionReplaceHandlerTest extends TestCase
{
    private string $baseDir;
    private string $captionsDir;
    private string $catalogFile;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/caption-replace-' . uniqid();
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
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']],
            ],
        ]]));
        file_put_contents($this->captionsDir . '/111.es.vtt', "WEBVTT\n\nold\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_rejects_when_lang_not_in_video_captions(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');

        $result = $this->makeHandler($vimeo)->handle('111', 'en', [
            'lang' => 'en',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('en', $result['error'] ?? '');
    }

    private function makeHandler(VimeoClient $vimeo): CaptionReplaceHandler
    {
        return new CaptionReplaceHandler(
            new CatalogEditor($this->catalogFile),
            new CaptionUploadHandler(
                $vimeo,
                new CatalogEditor($this->catalogFile),
                $this->config,
                $this->captionsDir,
            ),
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
