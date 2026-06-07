<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigTranslationTargetTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-translation-target-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config-legacy-subtitle-languages.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_legacy_entries_without_field_use_migration_defaults(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->assertTrue($config->isTranslationTarget('es'));
        $this->assertTrue($config->isTranslationTarget('en'));
        $this->assertFalse($config->isTranslationTarget('arq'));
        $this->assertFalse($config->isTranslationTarget('aeb'));
    }

    public function test_get_translation_target_languages_returns_only_flagged_entries(): void
    {
        $config = new StudioConfig($this->configPath);

        $ids = array_column($config->getTranslationTargetLanguages(), 'id');

        $this->assertEqualsCanonicalizing(['es', 'en'], $ids);
    }

    public function test_set_translation_target_persists_to_config_file(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->setSubtitleLanguageTranslationTarget('arq', true);

        $reloaded = new StudioConfig($this->configPath);
        $this->assertTrue($reloaded->isTranslationTarget('arq'));
    }

    public function test_add_subtitle_language_defaults_translation_target_to_false(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('de', 'German', 'de');

        $reloaded = new StudioConfig($this->configPath);
        $this->assertFalse($reloaded->isTranslationTarget('de'));
    }

    public function test_set_translation_target_throws_for_unknown_id(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->expectException(\RuntimeException::class);
        $config->setSubtitleLanguageTranslationTarget('zz', true);
    }
}
