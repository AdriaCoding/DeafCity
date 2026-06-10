<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\VideoEditHandler;
use Studio\VimeoClient;

class VideoEditHandlerTest extends TestCase
{
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->catalogFile = tempnam(sys_get_temp_dir(), 'video-edit-');
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Old Title',
                'sign_language' => 'lse',
                'edition' => '2020-x',
                'tags' => ['old'],
                'captions' => [],
            ],
        ]]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->catalogFile)) {
            unlink($this->catalogFile);
        }
    }

    private function makeHandler(VimeoClient $vimeo): VideoEditHandler
    {
        return new VideoEditHandler($vimeo, new CatalogEditor($this->catalogFile));
    }

    public function test_happy_path_writes_vimeo_and_catalog(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->once())->method('updateTitle')->with('111', 'New Title');
        $vimeo->expects($this->once())->method('setTags')->with('111', ['a', 'b']);

        $result = $this->makeHandler($vimeo)->handle('111', 'New Title', ['a', 'b']);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['vimeoWarning']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('New Title', $catalog['videos'][0]['title']);
        $this->assertSame(['a', 'b'], $catalog['videos'][0]['tags']);
    }

    public function test_vimeo_failure_still_saves_catalog_and_returns_warning(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('updateTitle')->willThrowException(new \RuntimeException('Vimeo error'));

        $result = $this->makeHandler($vimeo)->handle('111', 'New Title', ['a']);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['vimeoWarning']);
        $this->assertStringContainsString('Vimeo error', $result['vimeoWarning']);

        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('New Title', $catalog['videos'][0]['title']);
    }

    public function test_set_tags_failure_also_captured_in_warning(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('updateTitle'); // succeeds
        $vimeo->method('setTags')->willThrowException(new \RuntimeException('Tags error'));

        $result = $this->makeHandler($vimeo)->handle('111', 'New Title', ['a']);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['vimeoWarning']);
    }

    public function test_typology_is_written_to_catalog_entry(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);

        $result = $this->makeHandler($vimeo)->handle('111', 'New Title', ['a'], 'acudits');

        $this->assertTrue($result['ok']);
        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertSame('acudits', $catalog['videos'][0]['typology']);
    }

    public function test_null_typology_clears_field_on_catalog_entry(): void
    {
        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $catalog['videos'][0]['typology'] = 'acudits';
        file_put_contents($this->catalogFile, json_encode($catalog));

        $vimeo = $this->createMock(VimeoClient::class);

        $result = $this->makeHandler($vimeo)->handle('111', 'New Title', ['a'], null);

        $this->assertTrue($result['ok']);
        $catalog = json_decode(file_get_contents($this->catalogFile), true);
        $this->assertArrayNotHasKey('typology', $catalog['videos'][0]);
    }

    public function test_catalog_failure_returns_ok_false(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);

        // Write invalid JSON to break the catalog
        file_put_contents($this->catalogFile, 'not-json');

        $result = $this->makeHandler($vimeo)->handle('111', 'New Title', []);

        $this->assertFalse($result['ok']);
    }
}
