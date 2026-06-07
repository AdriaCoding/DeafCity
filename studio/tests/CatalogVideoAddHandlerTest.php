<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\CatalogVideoAddHandler;
use Studio\VimeoClient;
use Studio\VimeoNotFoundException;

class CatalogVideoAddHandlerTest extends TestCase
{
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->catalogFile = tempnam(sys_get_temp_dir(), 'catalog-add-');
        file_put_contents($this->catalogFile, json_encode(['videos' => []]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->catalogFile)) {
            unlink($this->catalogFile);
        }
    }

    public function test_adds_video_using_vimeo_metadata(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->once())->method('getVideo')->with('111')->willReturn('Vimeo Title');
        $vimeo->expects($this->once())->method('getThumbnailUrl')->with('111')->willReturn('https://example.com/t.jpg');

        $result = $this->makeHandler($vimeo)->handle('111', 'lse', '2024-madrid', 'Custom Title');

        $this->assertTrue($result['ok']);
        $this->assertSame('111', $result['video']['vimeo_id']);
        $this->assertSame('Custom Title', $result['video']['title']);
        $this->assertSame('https://example.com/t.jpg', $result['video']['thumbnail_url']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('Custom Title', $catalog['videos'][0]['title']);
    }

    public function test_uses_vimeo_title_when_custom_title_empty(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('From Vimeo');
        $vimeo->method('getThumbnailUrl')->willReturn(null);

        $result = $this->makeHandler($vimeo)->handle('222', 'lse', '2024-madrid', '');

        $this->assertTrue($result['ok']);
        $this->assertSame('From Vimeo', $result['video']['title']);
    }

    public function test_returns_error_when_vimeo_not_found(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willThrowException(new VimeoNotFoundException('not found'));

        $result = $this->makeHandler($vimeo)->handle('999', 'lse', '2024-madrid', 'Title');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    public function test_seeds_tags_from_vimeo_on_add(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('Vimeo Title');
        $vimeo->method('getThumbnailUrl')->willReturn(null);
        $vimeo->expects($this->once())->method('getTagNames')->with('333')->willReturn(['humor', 'deaf']);

        $result = $this->makeHandler($vimeo)->handle('333', 'lse', '2024-madrid', 'Title');

        $this->assertTrue($result['ok']);
        $this->assertSame(['humor', 'deaf'], $result['video']['tags']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame(['humor', 'deaf'], $catalog['videos'][0]['tags']);
    }

    public function test_returns_error_when_video_already_in_catalog(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
        ]]));

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('getVideo');

        $result = $this->makeHandler($vimeo)->handle('111', 'lse', '2024-madrid', 'Title');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('ja és al catàleg', $result['error']);
    }

    private function makeHandler(VimeoClient $vimeo): CatalogVideoAddHandler
    {
        return new CatalogVideoAddHandler($vimeo, new CatalogEditor($this->catalogFile));
    }
}
