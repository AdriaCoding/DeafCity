<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;

class StudioConfigMutationServiceTest extends TestCase
{
    public function test_removal_rejects_catalog_references_through_domain_service(): void
    {
        $configPath = tempnam(sys_get_temp_dir(), 'studio-config-service-');
        $catalogPath = tempnam(sys_get_temp_dir(), 'catalog-service-');
        copy(__DIR__ . '/fixtures/studio-config.json', $configPath);
        file_put_contents($catalogPath, json_encode(['videos' => [[
            'vimeo_id' => '111',
            'edition' => '2020-valencia',
            'captions' => [],
        ]]]));

        $service = new StudioConfigMutation(
            new StudioConfig($configPath),
            new CatalogEditor($catalogPath),
        );

        $this->expectException(\RuntimeException::class);
        $service->removeEdition('2020-valencia');
    }
}
