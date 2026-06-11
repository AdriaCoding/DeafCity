<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\VideoVisibilityHandler;

class VideoVisibilityHandlerTest extends TestCase
{
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->catalogFile = tempnam(sys_get_temp_dir(), 'video-visibility-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->catalogFile)) {
            unlink($this->catalogFile);
        }
    }

    private function writeCatalog(array $data): void
    {
        file_put_contents($this->catalogFile, json_encode($data));
    }

    public function test_marks_video_invisible_in_catalog(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
            ],
        ]]);

        $result = $this->makeHandler()->handle('111', true);

        $this->assertTrue($result['ok']);
        $entry = json_decode(file_get_contents($this->catalogFile), true)['videos'][0];
        $this->assertTrue($entry['invisible']);
    }

    public function test_returns_error_for_empty_vimeo_id(): void
    {
        $result = $this->makeHandler()->handle('', true);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    public function test_returns_error_when_video_not_in_catalog(): void
    {
        $this->writeCatalog(['videos' => []]);

        $result = $this->makeHandler()->handle('999', true);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['error']);
    }

    private function makeHandler(): VideoVisibilityHandler
    {
        return new VideoVisibilityHandler(new CatalogEditor($this->catalogFile));
    }
}
