<?php

namespace Studio\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\ContentLocalizationSync;
use Studio\LocalizationStore;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;

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
        foreach ([...$this->storeFiles, $this->configPath, $this->catalogFile, $this->configPath . '.lock'] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    // ── config ↔ localization atomicity ───────────────────────────────────────

    /**
     * Adding a content item touches two files: studio-config.json and
     * data/ui-localizations.json. If the second write fails the item exists in
     * config with no translation key, so the Studio and the Website show a raw
     * slug where a label belongs — and nothing tells the Producer why.
     *
     */
    #[DataProvider('contentItemAdders')]
    public function test_failed_localization_sync_rolls_back_the_config_entry(
        string $addMethod,
        string $getMethod,
        string $id,
        string $label,
    ): void {
        $mutation = $this->makeMutation($this->throwingLocalizationSync());

        $before = $mutation->{$getMethod}();

        try {
            $mutation->{$addMethod}($id, $label);
            $this->fail('The failing localization sync should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('localization store is down', $e->getMessage());
        }

        $this->assertEquals($before, $mutation->{$getMethod}(), 'config must be unchanged');
        $this->assertEquals(
            $before,
            (new StudioConfig($this->configPath))->{$getMethod}(),
            'the rollback must reach disk, not just the in-memory config'
        );
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function contentItemAdders(): array
    {
        return [
            'typology' => ['addTypology', 'getTypologies', 'acudit-nou', 'Acudit nou'],
            'edition' => ['addEdition', 'getEditions', '2027-tunis', '2027 Tunis'],
            'sign language' => ['addSignLanguage', 'getSignLanguages', 'lst', 'LST Tunisian Sign Language'],
        ];
    }

    public function test_successful_add_still_persists_both_sides(): void
    {
        $storePath = $this->makeLocalizationStoreFile();
        $mutation = $this->makeMutation(new ContentLocalizationSync(new LocalizationStore($storePath)));

        $mutation->addTypology('acudit-nou', 'Acudit nou');

        $ids = array_column((new StudioConfig($this->configPath))->getTypologies(), 'id');
        $this->assertContains('acudit-nou', $ids);
    }

    private function makeMutation(ContentLocalizationSync $sync): StudioConfigMutation
    {
        return new StudioConfigMutation(
            new StudioConfig($this->configPath),
            new CatalogEditor($this->catalogFile),
            $sync,
        );
    }

    private function throwingLocalizationSync(): ContentLocalizationSync
    {
        $store = new class ($this->makeLocalizationStoreFile()) extends LocalizationStore {
            public function createKeyIfMissing(string $key, string $section, string $context, string $enText): bool
            {
                throw new \RuntimeException('localization store is down');
            }
        };

        return new ContentLocalizationSync($store);
    }

    private function makeLocalizationStoreFile(): string
    {
        $path = sys_get_temp_dir() . '/ui-localizations-' . uniqid() . '.json';
        file_put_contents($path, json_encode(new \stdClass()));
        $this->storeFiles[] = $path;

        return $path;
    }

    /** @var list<string> */
    private array $storeFiles = [];

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

    // ── removeTypology ────────────────────────────────────────────────────────

    public function test_remove_typology_deletes_entry_when_unreferenced(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addTypology('endevinalles', 'ENDEVINALLES');

        $catalogEditor = new CatalogEditor($this->catalogFile);
        $config->removeTypology('endevinalles', $catalogEditor);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getTypologies(), 'id');
        $this->assertNotContains('endevinalles', $ids);
    }

    public function test_remove_typology_throws_when_referenced_by_catalog(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => '2020-valencia', 'typology' => 'acudits', 'tags' => [], 'captions' => []],
        ]]));

        $config = new StudioConfig($this->configPath);
        $catalogEditor = new CatalogEditor($this->catalogFile);

        $this->expectException(\RuntimeException::class);
        $config->removeTypology('acudits', $catalogEditor);
    }

    // ── updateTypologyLabel ───────────────────────────────────────────────────

    public function test_update_typology_label_changes_label_not_id(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->updateTypologyLabel('acudits', 'ACUDITS I BROMES');

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter($reloaded->getTypologies(), fn($e) => $e['id'] === 'acudits'))[0];

        $this->assertSame('acudits', $entry['id']);
        $this->assertSame('ACUDITS I BROMES', $entry['label']);
    }

    // ── removeSubtitleLanguage ────────────────────────────────────────────────

    public function test_remove_subtitle_language_deletes_entry_when_unreferenced(): void
    {
        $config = new StudioConfig($this->configPath);
        $config->addSubtitleLanguage('de', 'German');

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
