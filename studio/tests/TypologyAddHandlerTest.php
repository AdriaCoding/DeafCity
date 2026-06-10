<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;
use Studio\TypologyAddHandler;

class TypologyAddHandlerTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-typology-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_adds_typology_to_config_file(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new TypologyAddHandler($config);

        $result = $handler->handle('ANÈCDOTES');

        $this->assertTrue($result['ok']);
        $this->assertSame('anecdotes', $result['id']);
        $this->assertSame('ANÈCDOTES', $result['label']);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getTypologies(), 'id');
        $this->assertContains('anecdotes', $ids);
    }

    public function test_rejects_duplicate_typology_id(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new TypologyAddHandler($config);

        // Slugifies to 'acudits', which already exists in the fixture.
        $result = $handler->handle('Acudits');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_empty_label(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new TypologyAddHandler($config);

        $result = $handler->handle('   ');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }
}
