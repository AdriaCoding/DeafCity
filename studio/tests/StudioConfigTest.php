<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigTest extends TestCase
{
    public function test_language_lists_are_sorted_alphabetically_by_label(): void
    {
        $configPath = sys_get_temp_dir() . '/studio-config-sort-' . uniqid() . '.json';
        file_put_contents($configPath, json_encode([
            'sign_languages' => [
                ['id' => 'z', 'label' => 'Zulu Sign Language'],
                ['id' => 'a', 'label' => 'Alpha Sign Language'],
            ],
            'subtitle_languages' => [
                ['id' => 'es', 'label' => 'Spanish', 'vimeo_code' => 'es'],
                ['id' => 'en', 'label' => 'English', 'vimeo_code' => 'en'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        try {
            $config = new StudioConfig($configPath);

            $this->assertSame(['a', 'z'], array_column($config->getSignLanguages(), 'id'));
            $this->assertSame(['en', 'es'], array_column($config->getSubtitleLanguages(), 'id'));
        } finally {
            if (is_file($configPath)) {
                unlink($configPath);
            }
        }
    }

    public function test_exposes_curated_lists_from_config_file(): void
    {
        $config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');

        $this->assertSame(
            [['id' => 'lse', 'label' => 'LSE Spanish Sign Language']],
            $config->getSignLanguages()
        );
        $this->assertSame(
            [['id' => '2020-valencia', 'label' => '2020 Valencia']],
            $config->getEditions()
        );
        $this->assertSame(
            [
                ['id' => 'arq', 'label' => 'Algerian Darija', 'vimeo_code' => 'ar', 'translation_target' => false],
                ['id' => 'ca', 'label' => 'Catalan', 'vimeo_code' => 'ca', 'translation_target' => true],
                ['id' => 'en', 'label' => 'English', 'vimeo_code' => 'en', 'translation_target' => true],
                ['id' => 'es', 'label' => 'Spanish', 'vimeo_code' => 'es', 'translation_target' => true],
            ],
            $config->getSubtitleLanguages()
        );
        $this->assertSame(
            [['id' => 'acudits', 'label' => 'ACUDITS']],
            $config->getTypologies()
        );
    }
}
