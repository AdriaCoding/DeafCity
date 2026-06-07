<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\VimeoClient;
use Studio\VimeoNotFoundException;
use Studio\VimeoPushSync;

class VimeoPushSyncTest extends TestCase
{
    private string $baseDir;
    private string $captionsDir;
    private string $catalogFile;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/vimeo-push-sync-' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $this->captionsDir = $this->baseDir . '/captions';
        mkdir($this->captionsDir, 0777, true);
        $this->catalogFile = $this->baseDir . '/catalog.json';
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_pushes_title_tags_and_captions_using_vimeo_code(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Server Title',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => ['humor'],
                'captions' => [
                    ['lang' => 'arq', 'label' => 'Algerian Darija', 'file' => '111.arq.vtt'],
                ],
            ],
        ]]));
        file_put_contents($this->captionsDir . '/111.arq.vtt', "WEBVTT\n\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->once())->method('updateTitle')->with('111', 'Server Title');
        $vimeo->expects($this->once())->method('setTags')->with('111', ['humor']);
        $vimeo->expects($this->once())->method('uploadAndActivateTextTrack')
            ->with('111', $this->captionsDir . '/111.arq.vtt', 'ar', 'Algerian Darija');
        $vimeo->expects($this->never())->method('getThumbnailUrl');

        $result = $this->makeSync($vimeo)->syncVideo([
            'vimeo_id' => '111',
            'title' => 'Server Title',
            'tags' => ['humor'],
            'thumbnail_url' => 'https://example.com/t.jpg',
            'captions' => [
                ['lang' => 'arq', 'label' => 'Algerian Darija', 'file' => '111.arq.vtt'],
            ],
        ]);

        $this->assertTrue($result['ok']);
    }

    public function test_does_not_overwrite_catalog_captions_from_vimeo(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Server Title',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [
                    ['lang' => 'arq', 'label' => 'Algerian Darija', 'file' => '111.arq.vtt'],
                ],
            ],
        ]]));
        file_put_contents($this->captionsDir . '/111.arq.vtt', "WEBVTT\n\nserver content\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->method('updateTitle');
        $vimeo->method('setTags');
        $vimeo->expects($this->never())->method('getTextTracksDetailed');

        $this->makeSync($vimeo)->syncVideo([
            'vimeo_id' => '111',
            'title' => 'Server Title',
            'tags' => [],
            'captions' => [
                ['lang' => 'arq', 'label' => 'Algerian Darija', 'file' => '111.arq.vtt'],
            ],
        ]);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('arq', $catalog['videos'][0]['captions'][0]['lang']);
        $this->assertStringContainsString('server content', file_get_contents($this->captionsDir . '/111.arq.vtt'));
    }

    public function test_pulls_thumbnail_only_when_missing(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_222',
                'vimeo_id' => '222',
                'title' => 'Title',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
            ],
        ]]));

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->method('updateTitle');
        $vimeo->method('setTags');
        $vimeo->expects($this->once())->method('getThumbnailUrl')->with('222')
            ->willReturn('https://example.com/backfill.jpg');

        $result = $this->makeSync($vimeo)->syncVideo([
            'vimeo_id' => '222',
            'title' => 'Title',
            'tags' => [],
            'captions' => [],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['thumbnailBackfilled']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('https://example.com/backfill.jpg', $catalog['videos'][0]['thumbnail_url']);
    }

    public function test_skips_thumbnail_pull_when_already_present(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_333',
                'vimeo_id' => '333',
                'title' => 'Title',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
                'thumbnail_url' => 'https://example.com/existing.jpg',
            ],
        ]]));

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->method('updateTitle');
        $vimeo->method('setTags');
        $vimeo->expects($this->never())->method('getThumbnailUrl');

        $this->makeSync($vimeo)->syncVideo([
            'vimeo_id' => '333',
            'title' => 'Title',
            'tags' => [],
            'thumbnail_url' => 'https://example.com/existing.jpg',
            'captions' => [],
        ]);
    }

    public function test_skips_video_not_found_on_vimeo(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->once())->method('updateTitle')
            ->willThrowException(new VimeoNotFoundException('not found'));
        $vimeo->expects($this->never())->method('setTags');

        $result = $this->makeSync($vimeo)->syncVideo([
            'vimeo_id' => '999',
            'title' => 'Title',
            'tags' => [],
            'captions' => [],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['skipped']);
    }

    private function makeSync(VimeoClient $vimeo): VimeoPushSync
    {
        return new VimeoPushSync(
            $vimeo,
            $this->config,
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
