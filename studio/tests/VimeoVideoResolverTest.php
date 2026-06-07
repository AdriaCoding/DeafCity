<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\InvalidVimeoIdException;
use Studio\VimeoClient;
use Studio\VimeoIdParser;
use Studio\VimeoNotFoundException;
use Studio\VimeoVideoResolver;

class VimeoVideoResolverTest extends TestCase
{
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->catalogFile = tempnam(sys_get_temp_dir(), 'vimeo-resolve-');
        file_put_contents($this->catalogFile, json_encode(['videos' => []]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->catalogFile)) {
            unlink($this->catalogFile);
        }
    }

    public function test_resolves_url_to_vimeo_id_title_and_thumbnail(): void
    {
        $parser = $this->createMock(VimeoIdParser::class);
        $parser->method('parse')->with('https://vimeo.com/111')->willReturn('111');

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->once())->method('getVideo')->with('111')->willReturn('My Title');
        $vimeo->expects($this->once())->method('getThumbnailUrl')->with('111')->willReturn('https://example.com/t.jpg');

        $result = $this->makeResolver($parser, $vimeo)->resolve('https://vimeo.com/111');

        $this->assertTrue($result['ok']);
        $this->assertSame('111', $result['vimeo_id']);
        $this->assertSame('My Title', $result['title']);
        $this->assertSame('https://example.com/t.jpg', $result['thumbnail_url']);
    }

    private function makeResolver(VimeoIdParser $parser, VimeoClient $vimeo): VimeoVideoResolver
    {
        return new VimeoVideoResolver($parser, $vimeo, new CatalogEditor($this->catalogFile));
    }
}
