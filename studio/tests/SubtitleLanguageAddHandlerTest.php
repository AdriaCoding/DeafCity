<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\Iso639LanguageRegistry;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;
use Studio\SubtitleLanguageAddHandler;
use Studio\VimeoLocaleRegistry;

class SubtitleLanguageAddHandlerTest extends TestCase
{
    private string $configPath;
    private Iso639LanguageRegistry $isoRegistry;
    private VimeoLocaleRegistry $vimeoRegistry;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-subtitle-lang-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config-vimeo.json', $this->configPath);
        $this->isoRegistry = new Iso639LanguageRegistry(__DIR__ . '/../js/iso-639-3.json');
        $this->vimeoRegistry = new VimeoLocaleRegistry(__DIR__ . '/../js/vimeo-texttrack-locales.json');
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
        @unlink($this->configPath . '.lock');
    }

    public function test_adds_language_in_iso_vimeo_intersection(): void
    {
        // 'de' is both an ISO language and an accepted Vimeo locale.
        $result = $this->makeHandler()->handle('de', 'German');

        $this->assertTrue($result['ok']);
        $this->assertSame('de', $result['id']);
        $this->assertSame('German', $result['label']);
        $this->assertArrayNotHasKey('vimeo_code', $result);

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter(
            $reloaded->getSubtitleLanguages(),
            fn($e) => $e['id'] === 'de',
        ))[0];
        $this->assertSame(['id' => 'de', 'label' => 'German'], $entry);

        $raw = json_decode((string) file_get_contents($this->configPath), true);
        $rawEntry = array_values(array_filter(
            $raw['subtitle_languages'] ?? [],
            fn($e) => ($e['id'] ?? '') === 'de',
        ))[0];
        $this->assertArrayNotHasKey('vimeo_code', $rawEntry);
    }

    public function test_rejects_duplicate_subtitle_language_id(): void
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->handle('de', 'German')['ok']);
        $again = $handler->handle('de', 'Deutsch');

        $this->assertFalse($again['ok']);
    }

    public function test_rejects_unknown_iso_code(): void
    {
        $result = $this->makeHandler()->handle('zzz', 'Fake');

        $this->assertFalse($result['ok']);
    }

    public function test_rejects_iso_language_not_accepted_by_vimeo(): void
    {
        // 'arq' (Algerian Darija) is a valid ISO language but not a Vimeo locale,
        // so it falls outside the selectable intersection.
        $result = $this->makeHandler()->handle('arq', 'Algerian Darija');

        $this->assertFalse($result['ok']);
    }

    private function makeHandler(): SubtitleLanguageAddHandler
    {
        return new SubtitleLanguageAddHandler(
            new StudioConfigMutation(
                new StudioConfig($this->configPath),
                new CatalogEditor($this->configPath . '.catalog-unused'),
            ),
            $this->isoRegistry,
            $this->vimeoRegistry,
        );
    }
}
