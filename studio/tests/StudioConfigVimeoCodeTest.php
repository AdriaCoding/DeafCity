<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\StudioConfig;

class StudioConfigVimeoCodeTest extends TestCase
{
    private string $configPath;
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-vimeo-code-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config-vimeo.json', $this->configPath);

        $this->catalogFile = tempnam(sys_get_temp_dir(), 'catalog-vimeo-code-');
        file_put_contents($this->catalogFile, json_encode(['videos' => []]));
    }

    protected function tearDown(): void
    {
        foreach ([$this->configPath, $this->catalogFile] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    public function test_add_subtitle_language_persists_vimeo_code(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('de', 'German', 'de');

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter(
            $reloaded->getSubtitleLanguages(),
            fn($e) => $e['id'] === 'de',
        ))[0];

        $this->assertSame('de', $entry['vimeo_code']);
    }

    public function test_vimeo_code_for_returns_configured_code(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('arq', 'Algerian Darija', 'ar');

        $this->assertSame('ar', $config->vimeoCodeFor('arq'));
    }

    public function test_vimeo_code_for_falls_back_to_id_when_missing(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->assertSame('es', $config->vimeoCodeFor('es'));
    }

    public function test_get_used_vimeo_codes_lists_assigned_codes(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('arq', 'Algerian Darija', 'ar');
        $config->addSubtitleLanguage('aeb', 'Tunisian Arabic', 'mt');

        $used = $config->getUsedVimeoCodes();

        $this->assertContains('ar', $used);
        $this->assertContains('mt', $used);
        $this->assertContains('es', $used);
    }

    public function test_get_used_vimeo_codes_excludes_given_id(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('arq', 'Algerian Darija', 'ar');

        $used = $config->getUsedVimeoCodes('arq');

        $this->assertNotContains('ar', $used);
    }

    public function test_rejects_duplicate_vimeo_code_on_add(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('arq', 'Algerian Darija', 'ar');

        $this->expectException(\RuntimeException::class);
        $config->addSubtitleLanguage('aeb', 'Tunisian Arabic', 'ar');
    }

    public function test_blocks_vimeo_code_update_when_referenced_by_catalog(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('arq', 'Algerian Darija', 'ar');

        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'arq', 'label' => 'Algerian Darija', 'file' => '111.arq.vtt']],
            ],
        ]]));

        $catalogEditor = new CatalogEditor($this->catalogFile);

        $this->expectException(\RuntimeException::class);
        $config->updateSubtitleLanguageVimeoCode('arq', 'mt', $catalogEditor);
    }

    public function test_allows_vimeo_code_update_when_unreferenced(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('arq', 'Algerian Darija', 'ar');

        $catalogEditor = new CatalogEditor($this->catalogFile);
        $config->updateSubtitleLanguageVimeoCode('arq', 'mt', $catalogEditor);

        $this->assertSame('mt', $config->vimeoCodeFor('arq'));
    }
}
