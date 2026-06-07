<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\Iso639LanguageRegistry;
use Studio\StudioConfig;
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
    }

    public function test_adds_subtitle_language_with_vimeo_code_to_config_file(): void
    {
        $handler = $this->makeHandler();

        $result = $handler->handle('de', 'German', 'de');

        $this->assertTrue($result['ok']);
        $this->assertSame('de', $result['id']);
        $this->assertSame('German', $result['label']);
        $this->assertSame('de', $result['vimeo_code']);

        $reloaded = new StudioConfig($this->configPath);
        $entry = array_values(array_filter(
            $reloaded->getSubtitleLanguages(),
            fn($e) => $e['id'] === 'de',
        ))[0];
        $this->assertSame('de', $entry['vimeo_code']);
        $this->assertFalse($entry['translation_target']);
    }

    public function test_rejects_duplicate_subtitle_language_id(): void
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->handle('de', 'German', 'de')['ok']);
        $again = $handler->handle('de', 'Deutsch', 'de');

        $this->assertFalse($again['ok']);
    }

    public function test_rejects_unknown_iso_code(): void
    {
        $result = $this->makeHandler()->handle('zzz', 'Fake', 'es');

        $this->assertFalse($result['ok']);
    }

    public function test_rejects_unknown_vimeo_code(): void
    {
        $result = $this->makeHandler()->handle('de', 'German', 'zzz');

        $this->assertFalse($result['ok']);
    }

    public function test_rejects_duplicate_vimeo_code(): void
    {
        $handler = $this->makeHandler();

        $this->assertTrue($handler->handle('arq', 'Algerian Darija', 'ar')['ok']);
        $again = $handler->handle('aeb', 'Tunisian Arabic', 'ar');

        $this->assertFalse($again['ok']);
    }

    private function makeHandler(): SubtitleLanguageAddHandler
    {
        return new SubtitleLanguageAddHandler(
            new StudioConfig($this->configPath),
            $this->isoRegistry,
            $this->vimeoRegistry,
        );
    }
}
