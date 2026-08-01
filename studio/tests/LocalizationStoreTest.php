<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\LocalizationStore;

class LocalizationStoreTest extends TestCase
{
    private string $storePath;

    protected function setUp(): void
    {
        $this->storePath = sys_get_temp_dir() . '/ui-localizations-' . uniqid() . '.json';
        file_put_contents($this->storePath, json_encode([
            'player.filter.all_cities' => [
                'section' => 'player',
                'context' => 'City filter clear option',
                'translations' => ['en' => 'All cities', 'es' => 'Todas las ciudades'],
            ],
            'player.filter.all_typologies' => [
                'section' => 'player',
                'context' => 'Typology filter clear option',
                'translations' => ['en' => 'All typologies'],
            ],
            'about.title' => [
                'section' => 'about',
                'context' => 'About page title',
                'translations' => ['en' => 'About'],
            ],
            'content.typology.acudits' => [
                'section' => 'content',
                'context' => 'Typology label',
                'translations' => ['en' => 'Jokes'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->storePath)) {
            unlink($this->storePath);
        }
    }

    public function test_list_grouped_by_section(): void
    {
        $store = new LocalizationStore($this->storePath);
        $grouped = $store->listBySection();

        $this->assertArrayHasKey('player', $grouped);
        $this->assertSame('player.filter.all_cities', $grouped['player'][0]['key']);
        $this->assertArrayHasKey('about', $grouped);
        $this->assertArrayHasKey('content', $grouped);
    }

    public function test_set_cell_persists(): void
    {
        $store = new LocalizationStore($this->storePath);
        $store->setCell('player.filter.all_typologies', 'fr', 'Toutes les typologies');

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertSame('Toutes les typologies', $reloaded->getCell('player.filter.all_typologies', 'fr'));
    }

    public function test_completeness_ignores_content_labels(): void
    {
        $store = new LocalizationStore($this->storePath);

        $this->assertTrue($store->computeCompleteness(['en'])['en']);
        $this->assertFalse($store->computeCompleteness(['es'])['es']);
    }

    public function test_fill_empty_cells_only_and_seeded_flag(): void
    {
        $store = new LocalizationStore($this->storePath);
        $store->setCell('player.filter.all_cities', 'fr', 'Toutes les villes');
        $filled = $store->fillEmptyCells('fr', [
            'player.filter.all_typologies' => 'Toutes les typologies',
            'player.filter.all_cities' => 'Should not overwrite',
        ]);

        $this->assertSame(1, $filled);

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertSame('Todas las ciudades', $reloaded->getCell('player.filter.all_cities', 'es'));
        $this->assertSame('Toutes les typologies', $reloaded->getCell('player.filter.all_typologies', 'fr'));

        $all = $reloaded->all();
        $this->assertTrue($all['player.filter.all_typologies']['seeded']['fr'] ?? false);
    }

    public function test_set_cell_clears_seeded_flag(): void
    {
        $store = new LocalizationStore($this->storePath);
        $store->fillEmptyCells('it', ['player.filter.all_typologies' => 'Tutte le tipologie']);
        $store->setCell('player.filter.all_typologies', 'it', 'Tutte le tipologie (edited)');

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['player.filter.all_typologies'];
        $this->assertArrayNotHasKey('seeded', $entry);
        $this->assertSame('Tutte le tipologie (edited)', $reloaded->getCell('player.filter.all_typologies', 'it'));
    }

    public function test_create_key_if_missing_creates_new_key(): void
    {
        $store = new LocalizationStore($this->storePath);
        $created = $store->createKeyIfMissing('content.typology.pensaments', 'content', 'Typology (pensaments)', 'PENSAMENTS');

        $this->assertTrue($created);

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['content.typology.pensaments'];
        $this->assertSame('content', $entry['section']);
        $this->assertSame('Typology (pensaments)', $entry['context']);
        $this->assertSame(['en' => 'PENSAMENTS'], $entry['translations']);
    }

    public function test_create_key_if_missing_does_not_overwrite_existing_key(): void
    {
        $store = new LocalizationStore($this->storePath);
        $created = $store->createKeyIfMissing('content.typology.acudits', 'content', 'Should not be used', 'Should not be used');

        $this->assertFalse($created);

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['content.typology.acudits'];
        $this->assertSame('Typology label', $entry['context']);
        $this->assertSame(['en' => 'Jokes'], $entry['translations']);
    }

    public function test_remove_key_deletes_existing_key(): void
    {
        $store = new LocalizationStore($this->storePath);
        $removed = $store->removeKey('content.typology.acudits');

        $this->assertTrue($removed);

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertArrayNotHasKey('content.typology.acudits', $reloaded->all());
    }

    public function test_remove_key_is_a_no_op_for_missing_key(): void
    {
        $store = new LocalizationStore($this->storePath);
        $removed = $store->removeKey('content.typology.does-not-exist');

        $this->assertFalse($removed);

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertArrayHasKey('content.typology.acudits', $reloaded->all());
    }
}
