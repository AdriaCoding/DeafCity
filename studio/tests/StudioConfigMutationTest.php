<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\StudioConfig;

class StudioConfigMutationTest extends TestCase
{
    private string $configPath;
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-mutation-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);

        $this->catalogFile = tempnam(sys_get_temp_dir(), 'catalog-mutation-');
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

    // ── updateEditionLabel ────────────────────────────────────────────────────

    public function test_update_edition_label_changes_label_not_id(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->updateEditionLabel('2020-valencia', 'València 2020');

        $reloaded = new StudioConfig($this->configPath);
        $editions = $reloaded->getEditions();
        $entry = array_values(array_filter($editions, fn($e) => $e['id'] === '2020-valencia'))[0];

        $this->assertSame('2020-valencia', $entry['id']);
        $this->assertSame('València 2020', $entry['label']);
    }

    public function test_update_edition_label_throws_when_id_not_found(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->expectException(\RuntimeException::class);
        $config->updateEditionLabel('9999-nonexistent', 'Whatever');
    }

    // ── updateSignLanguageLabel ───────────────────────────────────────────────

    public function test_update_sign_language_label_changes_label_not_id(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->updateSignLanguageLabel('lse', 'LSE Llengua de Signes Espanyola');

        $reloaded = new StudioConfig($this->configPath);
        $langs = $reloaded->getSignLanguages();
        $entry = array_values(array_filter($langs, fn($e) => $e['id'] === 'lse'))[0];

        $this->assertSame('lse', $entry['id']);
        $this->assertSame('LSE Llengua de Signes Espanyola', $entry['label']);
    }

    public function test_update_sign_language_label_throws_when_id_not_found(): void
    {
        $config = new StudioConfig($this->configPath);

        $this->expectException(\RuntimeException::class);
        $config->updateSignLanguageLabel('nonexistent', 'Whatever');
    }

    // ── removeEdition ─────────────────────────────────────────────────────────

    public function test_remove_edition_deletes_entry_when_unreferenced(): void
    {
        // Add an extra edition to remove
        $config = new StudioConfig($this->configPath);
        $config->addEdition('2099-test', 'Test 2099');

        $catalogEditor = new CatalogEditor($this->catalogFile);
        $config->removeEdition('2099-test', $catalogEditor);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getEditions(), 'id');
        $this->assertNotContains('2099-test', $ids);
    }

    public function test_remove_edition_throws_when_referenced_by_catalog(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => '2020-valencia', 'tags' => [], 'captions' => []],
        ]]));

        $config = new StudioConfig($this->configPath);
        $catalogEditor = new CatalogEditor($this->catalogFile);

        $this->expectException(\RuntimeException::class);
        $config->removeEdition('2020-valencia', $catalogEditor);
    }

    // ── removeSignLanguage ────────────────────────────────────────────────────

    public function test_remove_sign_language_deletes_entry_when_unreferenced(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSignLanguage('gss', 'GSS Greek Sign Language');

        $catalogEditor = new CatalogEditor($this->catalogFile);
        $config->removeSignLanguage('gss', $catalogEditor);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getSignLanguages(), 'id');
        $this->assertNotContains('gss', $ids);
    }

    public function test_remove_sign_language_throws_when_referenced_by_catalog(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => '2020-valencia', 'tags' => [], 'captions' => []],
        ]]));

        $config = new StudioConfig($this->configPath);
        $catalogEditor = new CatalogEditor($this->catalogFile);

        $this->expectException(\RuntimeException::class);
        $config->removeSignLanguage('lse', $catalogEditor);
    }

    // ── removeSubtitleLanguage ────────────────────────────────────────────────

    public function test_remove_subtitle_language_deletes_entry_when_unreferenced(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('de', 'German', 'de');

        $catalogEditor = new CatalogEditor($this->catalogFile);
        $config->removeSubtitleLanguage('de', $catalogEditor);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getSubtitleLanguages(), 'id');
        $this->assertNotContains('de', $ids);
    }

    public function test_remove_subtitle_language_throws_when_referenced_by_catalog_captions(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']],
            ],
        ]]));

        $config = new StudioConfig($this->configPath);
        $catalogEditor = new CatalogEditor($this->catalogFile);

        $this->expectException(\RuntimeException::class);
        $config->removeSubtitleLanguage('es', $catalogEditor);
    }
}
