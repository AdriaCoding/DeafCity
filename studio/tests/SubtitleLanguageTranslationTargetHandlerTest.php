<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;
use Studio\SubtitleLanguageTranslationTargetHandler;

class SubtitleLanguageTranslationTargetHandlerTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-target-handler-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config-legacy-subtitle-languages.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_enabling_translation_target_persists_flag(): void
    {
        $handler = new SubtitleLanguageTranslationTargetHandler(new StudioConfig($this->configPath));

        $result = $handler->handle('arq', true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['translation_target']);

        $reloaded = new StudioConfig($this->configPath);
        $this->assertTrue($reloaded->isTranslationTarget('arq'));
    }

    public function test_unknown_language_returns_error(): void
    {
        $handler = new SubtitleLanguageTranslationTargetHandler(new StudioConfig($this->configPath));

        $result = $handler->handle('zz', true);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_empty_id_returns_error(): void
    {
        $handler = new SubtitleLanguageTranslationTargetHandler(new StudioConfig($this->configPath));

        $result = $handler->handle('', true);

        $this->assertFalse($result['ok']);
    }
}
