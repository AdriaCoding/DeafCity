<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;

class StudioConfigInputLanguageTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-input-lang-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
        @unlink($this->configPath . '.lock');
    }

    public function test_add_input_language_persists_id_label_and_base_language(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter(
            $reloaded->getInputLanguages(),
            fn($e) => $e['id'] === 'es-mx',
        ))[0];

        $this->assertSame(['id' => 'es-mx', 'label' => 'Espanyol (Mèxic)', 'base_language' => 'es'], $entry);
    }

    public function test_add_input_language_rejects_unknown_base_language(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->expectException(\InvalidArgumentException::class);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'not-a-real-language');
    }

    public function test_add_input_language_rejects_invalid_id(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->expectException(\InvalidArgumentException::class);
        $config->addInputLanguage('ES MX', 'Espanyol (Mèxic)', 'es');
    }

    public function test_remove_input_language_deletes_entry(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');
        $config->removeInputLanguage('es-mx');

        $reloaded = new StudioConfig($this->configPath);
        $this->assertSame([], $reloaded->getInputLanguages());
    }

    public function test_get_base_language_for_subtitle_language_returns_itself(): void
    {
        $config = new StudioConfig($this->configPath);
        $this->assertSame('es', $config->getBaseLanguageFor('es'));
    }

    public function test_get_base_language_for_dialect_returns_base(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        $this->assertSame('es', $config->getBaseLanguageFor('es-mx'));
    }

    public function test_get_base_language_for_unknown_id_returns_itself(): void
    {
        $config = new StudioConfig($this->configPath);
        $this->assertSame('xx', $config->getBaseLanguageFor('xx'));
    }

    public function test_language_label_for_prefers_input_language_over_subtitle_language(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        $this->assertSame('Espanyol (Mèxic)', $config->languageLabelFor('es-mx'));
        $this->assertSame('Spanish', $config->languageLabelFor('es'));
    }

    public function test_language_label_for_unknown_id_returns_raw_id(): void
    {
        $config = new StudioConfig($this->configPath);
        $this->assertSame('xx', $config->languageLabelFor('xx'));
    }

    public function test_is_valid_input_language_accepts_base_and_dialect(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        $this->assertTrue($config->isValidInputLanguage('es'));
        $this->assertTrue($config->isValidInputLanguage('es-mx'));
        $this->assertFalse($config->isValidInputLanguage('xx'));
    }

    public function test_combined_input_language_options_includes_base_and_dialects(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        $ids = array_column($config->getCombinedInputLanguageOptions(), 'id');
        sort($ids);

        $this->assertSame(['ar', 'ca', 'en', 'es', 'es-mx'], $ids);
    }

    public function test_combined_input_language_options_carry_base_language(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addInputLanguage('es-mx', 'Espanyol (Mèxic)', 'es');

        $options = $config->getCombinedInputLanguageOptions();
        $byId = [];
        foreach ($options as $option) {
            $byId[$option['id']] = $option;
        }

        $this->assertSame('es', $byId['es']['base_language']);
        $this->assertSame('es', $byId['es-mx']['base_language']);
    }
}
