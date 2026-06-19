<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogIntakeAddHandler;
use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\VimeoClient;

class CatalogIntakeAddHandlerTest extends TestCase
{
    private string $baseDir;
    private string $captionsDir;
    private string $catalogFile;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/catalog-intake-add-' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $this->captionsDir = $this->baseDir . '/captions';
        mkdir($this->captionsDir, 0777, true);
        $this->catalogFile = $this->baseDir . '/catalog.json';
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');
        file_put_contents($this->catalogFile, json_encode(['videos' => []]));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_stores_form_tags_over_vimeo_tags(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('Vimeo Title');
        $vimeo->method('getThumbnailUrl')->willReturn(null);
        $vimeo->expects($this->never())->method('getTagNames');

        $result = $this->makeHandler($vimeo)->handle(
            '111',
            'lse',
            '2020-valencia',
            'Custom Title',
            'acudits',
            ['custom', 'tag'],
            [],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(['custom', 'tag'], $result['video']['tags']);
    }

    public function test_saves_caption_locally_without_vimeo_sync(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('Vimeo Title');
        $vimeo->method('getThumbnailUrl')->willReturn(null);
        $vimeo->expects($this->never())->method('getTextTracks');
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');

        $result = $this->makeHandler($vimeo)->handle(
            '222',
            'lse',
            '2020-valencia',
            'With Captions',
            'acudits',
            [],
            [['lang' => 'es', 'tmpPath' => $vtt, 'originalName' => 'subtitles.vtt']],
        );

        $this->assertTrue($result['ok']);
        $this->assertFileExists($this->captionsDir . '/222.es.vtt');
        $this->assertCount(1, $result['video']['captions']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('222', $catalog['videos'][0]['vimeo_id']);
    }

    public function test_keeps_created_video_when_caption_upload_fails(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('Vimeo Title');
        $vimeo->method('getThumbnailUrl')->willReturn(null);

        $result = $this->makeHandler($vimeo)->handle(
            '333',
            'lse',
            '2020-valencia',
            'Caption Fail',
            'acudits',
            [],
            [['lang' => 'zz', 'tmpPath' => $vtt, 'originalName' => 'subtitles.vtt']],
        );

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['captionError'] ?? null);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertCount(1, $catalog['videos']);
        $this->assertSame('333', $catalog['videos'][0]['vimeo_id']);
        $this->assertSame([], $catalog['videos'][0]['captions']);
    }

    public function test_persists_chosen_master_caption_lang(): void
    {
        $es = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($es, "WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHola\n");
        $en = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($en, "WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHello\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('Vimeo Title');
        $vimeo->method('getThumbnailUrl')->willReturn(null);

        $result = $this->makeHandler($vimeo)->handle(
            '444',
            'lse',
            '2020-valencia',
            'Master Pick',
            'acudits',
            [],
            [
                ['lang' => 'es', 'tmpPath' => $es, 'originalName' => 'a.vtt'],
                ['lang' => 'en', 'tmpPath' => $en, 'originalName' => 'b.vtt'],
            ],
            'en',
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('en', $result['video']['master_caption_lang']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('en', $catalog['videos'][0]['master_caption_lang']);
    }

    private function makeHandler(VimeoClient $vimeo): CatalogIntakeAddHandler
    {
        return new CatalogIntakeAddHandler(
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
