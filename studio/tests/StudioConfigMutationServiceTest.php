<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\ContentLocalizationSync;
use Studio\LocalizationStore;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;

class StudioConfigMutationServiceTest extends TestCase
{
    public function test_removal_rejects_catalog_references_through_domain_service(): void
    {
        $configPath = tempnam(sys_get_temp_dir(), 'studio-config-service-');
        $catalogPath = tempnam(sys_get_temp_dir(), 'catalog-service-');
        $localizationsPath = tempnam(sys_get_temp_dir(), 'ui-localizations-service-');
        copy(__DIR__ . '/fixtures/studio-config.json', $configPath);
        file_put_contents($catalogPath, json_encode(['videos' => [[
            'vimeo_id' => '111',
            'edition' => '2020-valencia',
            'captions' => [],
        ]]]));
        file_put_contents($localizationsPath, '{}');

        $service = new StudioConfigMutation(
            new StudioConfig($configPath),
            new CatalogEditor($catalogPath),
            new ContentLocalizationSync(new LocalizationStore($localizationsPath)),
        );

        $this->expectException(\RuntimeException::class);
        $service->removeEdition('2020-valencia');
    }

    public function test_remove_typology_also_deletes_its_locale_row(): void
    {
        $configPath = tempnam(sys_get_temp_dir(), 'studio-config-service-');
        $catalogPath = tempnam(sys_get_temp_dir(), 'catalog-service-');
        $localizationsPath = tempnam(sys_get_temp_dir(), 'ui-localizations-service-');
        copy(__DIR__ . '/fixtures/studio-config.json', $configPath);
        file_put_contents($catalogPath, json_encode(['videos' => []]));
        file_put_contents($localizationsPath, json_encode([
            'content.typology.acudits' => [
                'section' => 'content',
                'context' => 'Typology (acudits)',
                'translations' => ['en' => 'Jokes'],
            ],
        ]));

        $localizationStore = new LocalizationStore($localizationsPath);
        $service = new StudioConfigMutation(
            new StudioConfig($configPath),
            new CatalogEditor($catalogPath),
            new ContentLocalizationSync($localizationStore),
        );

        $service->removeTypology('acudits');

        $reloaded = new LocalizationStore($localizationsPath);
        $this->assertArrayNotHasKey('content.typology.acudits', $reloaded->all());
    }
}
