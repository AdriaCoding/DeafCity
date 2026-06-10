<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigTest extends TestCase
{
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
                ['id' => 'es', 'label' => 'Spanish', 'vimeo_code' => 'es', 'translation_target' => true],
                ['id' => 'en', 'label' => 'English', 'vimeo_code' => 'en', 'translation_target' => true],
                ['id' => 'ca', 'label' => 'Catalan', 'vimeo_code' => 'ca', 'translation_target' => true],
                ['id' => 'arq', 'label' => 'Algerian Darija', 'vimeo_code' => 'ar', 'translation_target' => false],
            ],
            $config->getSubtitleLanguages()
        );
        $this->assertSame(
            [['id' => 'acudits', 'label' => 'ACUDITS']],
            $config->getTypologies()
        );
    }
}
