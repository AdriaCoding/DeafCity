<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigAddSubtitleLanguageTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-add-subtitle-lang-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config-vimeo.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
        @unlink($this->configPath . '.lock');
    }

    public function test_add_subtitle_language_persists_id_and_label(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('de', 'German');

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter(
            $reloaded->getSubtitleLanguages(),
            fn($e) => $e['id'] === 'de',
        ))[0];

        $this->assertSame(['id' => 'de', 'label' => 'German'], $entry);
    }

    public function test_add_subtitle_language_does_not_serialize_vimeo_code(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('de', 'German');

        $raw = json_decode((string) file_get_contents($this->configPath), true);
        foreach ($raw['subtitle_languages'] as $entry) {
            $this->assertArrayNotHasKey('vimeo_code', $entry);
        }
    }
}
