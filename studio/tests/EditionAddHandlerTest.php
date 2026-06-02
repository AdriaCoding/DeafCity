<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\EditionAddHandler;
use Studio\StudioConfig;

class EditionAddHandlerTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-edition-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_adds_edition_to_config_file(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new EditionAddHandler($config);

        $result = $handler->handle('Lisboa', '2027');

        $this->assertTrue($result['ok']);
        $this->assertSame('2027-lisboa', $result['id']);
        $this->assertSame('Lisboa 2027', $result['label']);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getEditions(), 'id');
        $this->assertContains('2027-lisboa', $ids);
    }

    public function test_rejects_duplicate_edition(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new EditionAddHandler($config);

        $this->assertTrue($handler->handle('Lisboa', '2027')['ok']);
        $again = $handler->handle('Lisboa', '2027');

        $this->assertFalse($again['ok']);
        $this->assertNotEmpty($again['errors']);
    }

    public function test_rejects_invalid_input(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new EditionAddHandler($config);

        $result = $handler->handle('', '20');

        $this->assertFalse($result['ok']);
    }
}
