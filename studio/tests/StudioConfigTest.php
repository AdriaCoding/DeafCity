<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigTest extends TestCase
{
    public function test_sign_languages_are_sorted_alphabetically_by_second_word(): void
    {
        $configPath = sys_get_temp_dir() . '/studio-config-sort-' . uniqid() . '.json';
        file_put_contents($configPath, json_encode([
            'sign_languages' => [
                // Full-label A–Z would be LIBRAS then LSA; second-word A–Z is Algerian then Brazilian.
                ['id' => 'libras', 'label' => 'LIBRAS Brazilian Sign Language'],
                ['id' => 'lsa', 'label' => 'LSA Algerian Sign Language'],
            ],
            'subtitle_languages' => [
                ['id' => 'es', 'label' => 'Spanish'],
                ['id' => 'en', 'label' => 'English'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        try {
            $config = new StudioConfig($configPath);

            $this->assertSame(['lsa', 'libras'], array_column($config->getSignLanguages(), 'id'));
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
                ['id' => 'ar', 'label' => 'Arabic'],
                ['id' => 'ca', 'label' => 'Catalan'],
                ['id' => 'en', 'label' => 'English'],
                ['id' => 'es', 'label' => 'Spanish'],
            ],
            $config->getSubtitleLanguages()
        );
        $this->assertSame(
            [['id' => 'acudits', 'label' => 'ACUDITS']],
            $config->getTypologies()
        );
    }
}
