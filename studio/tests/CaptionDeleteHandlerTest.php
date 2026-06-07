<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionDeleteHandler;
use Studio\CatalogEditor;

class CaptionDeleteHandlerTest extends TestCase
{
    private string $baseDir;
    private string $captionsDir;
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/caption-delete-' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $this->captionsDir = $this->baseDir . '/captions';
        mkdir($this->captionsDir, 0777, true);
        $this->catalogFile = $this->baseDir . '/catalog.json';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_deletes_physical_file_from_captions_dir(): void
    {
        $this->writeCatalog([
            'master_caption_lang' => 'es',
            'captions' => [
                ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
            ],
        ]);
        file_put_contents($this->captionsDir . '/111.es.vtt', "WEBVTT\n\n");

        $result = $this->makeHandler()->handle('111', 'es');

        $this->assertTrue($result['ok']);
        $this->assertFileDoesNotExist($this->captionsDir . '/111.es.vtt');
    }

    public function test_succeeds_when_physical_file_already_missing(): void
    {
        $this->writeCatalog([
            'master_caption_lang' => 'es',
            'captions' => [
                ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
            ],
        ]);

        $result = $this->makeHandler()->handle('111', 'es');

        $this->assertTrue($result['ok']);
        $this->assertSame([], (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111')['captions']);
    }

    public function test_returns_newMaster_when_master_was_promoted(): void
    {
        $this->writeCatalog([
            'master_caption_lang' => 'ca',
            'captions' => [
                ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
            ],
        ]);
        file_put_contents($this->captionsDir . '/111.ca.vtt', "WEBVTT\n\n");
        file_put_contents($this->captionsDir . '/111.es.vtt', "WEBVTT\n\n");

        $result = $this->makeHandler()->handle('111', 'ca');

        $this->assertTrue($result['ok']);
        $this->assertSame('es', $result['newMaster']);
    }

    public function test_returns_ok_without_newMaster_when_master_unchanged(): void
    {
        $this->writeCatalog([
            'master_caption_lang' => 'ca',
            'captions' => [
                ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
            ],
        ]);
        file_put_contents($this->captionsDir . '/111.es.vtt', "WEBVTT\n\n");

        $result = $this->makeHandler()->handle('111', 'es');

        $this->assertTrue($result['ok']);
        $this->assertArrayNotHasKey('newMaster', $result);
    }

    private function writeCatalog(array $videoFields): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            array_merge([
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Test',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
            ], $videoFields),
        ]]));
    }

    private function makeHandler(): CaptionDeleteHandler
    {
        return new CaptionDeleteHandler(
            new CatalogEditor($this->catalogFile),
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
