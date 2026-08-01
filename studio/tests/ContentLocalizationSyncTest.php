<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\ContentLocalizationSync;
use Studio\LocalizationStore;

class ContentLocalizationSyncTest extends TestCase
{
    private string $storePath;

    protected function setUp(): void
    {
        $this->storePath = sys_get_temp_dir() . '/ui-localizations-sync-' . uniqid() . '.json';
        file_put_contents($this->storePath, json_encode([
            'content.typology.acudits' => [
                'section' => 'content',
                'context' => 'Typology (acudits)',
                'translations' => ['en' => 'Jokes', 'ca' => 'Acudits'],
            ],
            'content.edition.2020-valencia' => [
                'section' => 'content',
                'context' => 'Edition city (2020-valencia)',
                'translations' => ['en' => 'Valencia'],
            ],
            'content.sign_language.lse.label' => [
                'section' => 'content',
                'context' => 'Sign language label (lse)',
                'translations' => ['en' => 'LSE'],
            ],
            'content.sign_language.lse.short_label' => [
                'section' => 'content',
                'context' => 'Sign language short label (lse)',
                'translations' => ['en' => 'LSE'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->storePath)) {
            unlink($this->storePath);
        }
    }

    private function sync(): ContentLocalizationSync
    {
        return new ContentLocalizationSync(new LocalizationStore($this->storePath));
    }

    public function test_sync_typology_creates_content_key(): void
    {
        $this->sync()->syncTypology('pensaments', 'PENSAMENTS');

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['content.typology.pensaments'];
        $this->assertSame('content', $entry['section']);
        $this->assertSame('Typology (pensaments)', $entry['context']);
        $this->assertSame(['en' => 'PENSAMENTS'], $entry['translations']);
    }

    public function test_sync_edition_creates_content_key(): void
    {
        $this->sync()->syncEdition('2099-test', 'Test City');

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['content.edition.2099-test'];
        $this->assertSame('Edition city (2099-test)', $entry['context']);
        $this->assertSame(['en' => 'Test City'], $entry['translations']);
    }

    public function test_sync_sign_language_creates_content_key(): void
    {
        $this->sync()->syncSignLanguage('gss', 'GSS Greek Sign Language');

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['content.sign_language.gss.label'];
        $this->assertSame('Sign language label (gss)', $entry['context']);
        $this->assertSame(['en' => 'GSS Greek Sign Language'], $entry['translations']);
    }

    public function test_sync_does_not_overwrite_existing_translations(): void
    {
        $this->sync()->syncTypology('acudits', 'ACUDITS I BROMES');

        $reloaded = new LocalizationStore($this->storePath);
        $entry = $reloaded->all()['content.typology.acudits'];
        $this->assertSame(['en' => 'Jokes', 'ca' => 'Acudits'], $entry['translations']);
    }

    public function test_remove_typology_deletes_content_key(): void
    {
        $this->sync()->removeTypology('acudits');

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertArrayNotHasKey('content.typology.acudits', $reloaded->all());
    }

    public function test_remove_edition_deletes_content_key(): void
    {
        $this->sync()->removeEdition('2020-valencia');

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertArrayNotHasKey('content.edition.2020-valencia', $reloaded->all());
    }

    public function test_remove_sign_language_deletes_label_and_short_label_keys(): void
    {
        $this->sync()->removeSignLanguage('lse');

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertArrayNotHasKey('content.sign_language.lse.label', $reloaded->all());
        $this->assertArrayNotHasKey('content.sign_language.lse.short_label', $reloaded->all());
    }

    public function test_remove_is_a_no_op_for_unknown_id(): void
    {
        $this->sync()->removeTypology('does-not-exist');

        $reloaded = new LocalizationStore($this->storePath);
        $this->assertArrayHasKey('content.typology.acudits', $reloaded->all());
    }
}
