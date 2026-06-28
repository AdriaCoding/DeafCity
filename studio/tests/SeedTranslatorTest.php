<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\LocalizationStore;
use Studio\SeedTranslator;

class SeedTranslatorTest extends TestCase
{
    private string $storePath;

    protected function setUp(): void
    {
        $this->storePath = sys_get_temp_dir() . '/ui-localizations-seed-' . uniqid() . '.json';
        file_put_contents($this->storePath, json_encode([
            'player.filter.all_cities' => [
                'section' => 'player',
                'translations' => ['en' => 'All cities', 'es' => 'Todas las ciudades'],
            ],
            'player.filter.all_typologies' => [
                'section' => 'player',
                'translations' => ['en' => 'All typologies'],
            ],
            'about.title' => [
                'section' => 'about',
                'translations' => ['en' => 'About'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->storePath)) {
            unlink($this->storePath);
        }
    }

    public function test_fills_only_empty_cells_and_marks_seeded(): void
    {
        $store = new LocalizationStore($this->storePath);
        $translator = new class {
            public function translate(array $texts, string $srcLang, string $tgtLang): array
            {
                return array_map(static fn(string $t): string => "FR:$t", $texts);
            }
        };

        $seed = new SeedTranslator($store, $translator);
        $result = $seed->seed('fr');

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['filled']);

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertSame('Todas las ciudades', $reloaded->getCell('player.filter.all_cities', 'es'));
        $this->assertSame('FR:All typologies', $reloaded->getCell('player.filter.all_typologies', 'fr'));
        $this->assertTrue($reloaded->all()['player.filter.all_typologies']['seeded']['fr'] ?? false);
    }

    public function test_translation_error_leaves_prior_state_intact(): void
    {
        $store = new LocalizationStore($this->storePath);
        $translator = new class {
            public function translate(array $texts, string $srcLang, string $tgtLang): array
            {
                throw new \RuntimeException('API down');
            }
        };

        $seed = new SeedTranslator($store, $translator);
        $result = $seed->seed('ca');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['filled']);

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertSame('', $reloaded->getCell('player.filter.all_typologies', 'ca'));
    }
}
