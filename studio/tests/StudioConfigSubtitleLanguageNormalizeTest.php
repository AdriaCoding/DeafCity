<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigSubtitleLanguageNormalizeTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-subtitle-normalize-' . uniqid() . '.json';
        file_put_contents($this->configPath, json_encode([
            'subtitle_languages' => [
                ['id' => 'en', 'label' => 'English', 'vimeo_code' => 'en', 'translation_target' => false],
                ['id' => 'es', 'label' => 'Spanish', 'vimeo_code' => 'es', 'translation_target' => true],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_get_subtitle_languages_strips_legacy_translation_target_and_vimeo_code_fields(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->assertSame(
            [
                ['id' => 'en', 'label' => 'English'],
                ['id' => 'es', 'label' => 'Spanish'],
            ],
            $config->getSubtitleLanguages()
        );
    }
}
