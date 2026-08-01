<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\ContentLocalizationSync;
use Studio\LocalizationStore;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;
use Studio\TypologyAddHandler;

class TypologyAddHandlerTest extends TestCase
{
    private string $configPath;
    private string $localizationsPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-typology-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);

        $this->localizationsPath = sys_get_temp_dir() . '/ui-localizations-typology-add-' . uniqid() . '.json';
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

    private function makeMutation(): StudioConfigMutation
    {
        return new StudioConfigMutation(
            new StudioConfig($this->configPath),
            new CatalogEditor($this->configPath . '.catalog-unused'),
            new ContentLocalizationSync(new LocalizationStore($this->localizationsPath)),
        );
    }

    public function test_adds_typology_to_config_file(): void
    {
        $handler = new TypologyAddHandler($this->makeMutation());

        $result = $handler->handle('ANÈCDOTES');

        $this->assertTrue($result['ok']);
        $this->assertSame('anecdotes', $result['id']);
        $this->assertSame('ANÈCDOTES', $result['label']);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getTypologies(), 'id');
        $this->assertContains('anecdotes', $ids);

        $localizations = new LocalizationStore($this->localizationsPath);
        $entry = $localizations->all()['content.typology.anecdotes'];
        $this->assertSame(['en' => 'ANÈCDOTES'], $entry['translations']);
    }

    public function test_rejects_duplicate_typology_id(): void
    {
        $handler = new TypologyAddHandler($this->makeMutation());

        // Slugifies to 'acudits', which already exists in the fixture.
        $result = $handler->handle('Acudits');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_empty_label(): void
    {
        $handler = new TypologyAddHandler($this->makeMutation());

        $result = $handler->handle('   ');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }
}
