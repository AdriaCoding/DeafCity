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
            [['id' => 'valencia-2020', 'label' => 'Valencia 2020']],
            $config->getEditions()
        );
        $this->assertSame(
            [
                ['id' => 'es', 'label' => 'Spanish'],
                ['id' => 'en', 'label' => 'English'],
            ],
            $config->getSubtitleLanguages()
        );
    }
}
