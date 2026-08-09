<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\ContentLocalizationSync;
use Studio\InputLanguageAddHandler;
use Studio\LocalizationStore;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;

class InputLanguageAddHandlerTest extends TestCase
{
    private string $configPath;
    private string $localizationsPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-input-lang-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);
        $this->localizationsPath = sys_get_temp_dir() . '/ui-localizations-input-lang-add-' . uniqid() . '.json';
        file_put_contents($this->localizationsPath, '{}');
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
        @unlink($this->configPath . '.lock');
        @unlink($this->localizationsPath);
    }

    public function test_adds_dialect_with_valid_base_language(): void
    {
        $result = $this->makeHandler()->handle('es-mx', 'Espanyol (Mèxic)', 'es');

        $this->assertTrue($result['ok']);
        $this->assertSame('es-mx', $result['id']);
        $this->assertSame('Espanyol (Mèxic)', $result['label']);
        $this->assertSame('es', $result['baseLanguage']);

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter(
            $reloaded->getInputLanguages(),
            fn($e) => $e['id'] === 'es-mx',
        ))[0];
        $this->assertSame(['id' => 'es-mx', 'label' => 'Espanyol (Mèxic)', 'base_language' => 'es'], $entry);
    }

    public function test_lowercases_id_before_saving(): void
    {
        $result = $this->makeHandler()->handle('ES-MX', 'Espanyol (Mèxic)', 'es');

        $this->assertTrue($result['ok']);
        $this->assertSame('es-mx', $result['id']);
    }

    public function test_rejects_unknown_base_language(): void
    {
        $result = $this->makeHandler()->handle('es-mx', 'Espanyol (Mèxic)', 'not-a-real-language');

        $this->assertFalse($result['ok']);
    }

    public function test_rejects_missing_fields(): void
    {
        $this->assertFalse($this->makeHandler()->handle('', 'Espanyol (Mèxic)', 'es')['ok']);
        $this->assertFalse($this->makeHandler()->handle('es-mx', '', 'es')['ok']);
        $this->assertFalse($this->makeHandler()->handle('es-mx', 'Espanyol (Mèxic)', '')['ok']);
    }

    public function test_rejects_id_colliding_with_existing_subtitle_language(): void
    {
        // 'es' already exists as a base Subtitle language in the fixture.
        $result = $this->makeHandler()->handle('es', 'Espanyol duplicat', 'es');

        $this->assertFalse($result['ok']);
    }

    public function test_rejects_duplicate_input_language_id(): void
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->handle('es-mx', 'Espanyol (Mèxic)', 'es')['ok']);
        $again = $handler->handle('es-mx', 'Un altre nom', 'es');

        $this->assertFalse($again['ok']);
    }

    public function test_rejects_invalid_id_format(): void
    {
        $result = $this->makeHandler()->handle('ES MX!', 'Espanyol (Mèxic)', 'es');

        $this->assertFalse($result['ok']);
    }

    private function makeHandler(): InputLanguageAddHandler
    {
        return new InputLanguageAddHandler(
            new StudioConfigMutation(
                new StudioConfig($this->configPath),
                new CatalogEditor($this->configPath . '.catalog-unused'),
                new ContentLocalizationSync(new LocalizationStore($this->localizationsPath)),
            ),
        );
    }
}
