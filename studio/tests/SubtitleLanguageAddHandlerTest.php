<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;
use Studio\SubtitleLanguageAddHandler;

class SubtitleLanguageAddHandlerTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-subtitle-lang-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_adds_subtitle_language_to_config_file(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new SubtitleLanguageAddHandler($config);

        $result = $handler->handle('de', 'German');

        $this->assertTrue($result['ok']);
        $this->assertSame('de', $result['id']);
        $this->assertSame('German', $result['label']);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getSubtitleLanguages(), 'id');
        $this->assertContains('de', $ids);
    }

    public function test_rejects_duplicate_subtitle_language(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new SubtitleLanguageAddHandler($config);

        $this->assertTrue($handler->handle('de', 'German')['ok']);
        $again = $handler->handle('de', 'Deutsch');

        $this->assertFalse($again['ok']);
    }
}
