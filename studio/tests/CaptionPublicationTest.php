<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionPublication;
use Studio\CatalogEditor;
use Studio\VimeoClient;

class CaptionPublicationTest extends TestCase
{
    public function test_persists_catalog_before_attempting_vimeo_mirror(): void
    {
        $dir = sys_get_temp_dir() . '/caption-publication-' . uniqid();
        mkdir($dir . '/captions', 0777, true);
        $catalogPath = $dir . '/catalog.json';
        $catalogData = [
            'videos' => [[
                'vimeo_id' => '111',
                'captions' => [],
            ]],
        ];
        file_put_contents($catalogPath, json_encode($catalogData));
        file_put_contents($dir . '/captions/111.es.vtt', "WEBVTT\n\n");

        $catalog = new CatalogEditor($catalogPath);
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willThrowException(new \RuntimeException('offline'));
        $vimeo->method('uploadAndActivateTextTrack')->willThrowException(new \RuntimeException('offline'));

        $result = (new CaptionPublication($catalog, $vimeo, $dir . '/captions'))
            ->publish('111', [[
                'lang' => 'es',
                'label' => 'Spanish',
                'file' => '111.es.vtt',
            ]]);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['vimeoWarnings']);
        $this->assertSame('es', $catalog->findVideoByVimeoId('111')['captions'][0]['lang']);

        $this->removeDir($dir);
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
