<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;

class CatalogEditorTest extends TestCase
{
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->catalogFile = tempnam(sys_get_temp_dir(), 'catalog-editor-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->catalogFile)) {
            unlink($this->catalogFile);
        }
    }

    private function writeCatalog(array $data): void
    {
        file_put_contents($this->catalogFile, json_encode($data));
    }

    private function readCatalog(): array
    {
        return json_decode(file_get_contents($this->catalogFile), true);
    }

    public function test_updates_title_and_tags_of_matching_video(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Old Title',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => ['old-tag'],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->updateVideo('111', 'New Title', ['tag-a', 'tag-b']);

        $catalog = $this->readCatalog();
        $entry = $catalog['videos'][0];

        $this->assertSame('New Title', $entry['title']);
        $this->assertSame(['tag-a', 'tag-b'], $entry['tags']);
    }

    public function test_leaves_other_fields_untouched(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Old',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->updateVideo('111', 'New', ['x']);

        $entry = $this->readCatalog()['videos'][0];

        $this->assertSame('lse_111', $entry['id']);
        $this->assertSame('111', $entry['vimeo_id']);
        $this->assertSame('lse', $entry['sign_language']);
        $this->assertSame('2020-valencia', $entry['edition']);
        $this->assertSame([['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']], $entry['captions']);
    }

    public function test_throws_when_video_id_not_found(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);

        $this->expectException(\RuntimeException::class);
        $editor->updateVideo('999', 'Title', []);
    }

    public function test_concurrent_writes_do_not_corrupt(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
            ['id' => 'lse_222', 'vimeo_id' => '222', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
        ]]);

        $pids = [];
        foreach (['111' => 'First', '222' => 'Second'] as $id => $title) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $editor = new CatalogEditor($this->catalogFile);
                $editor->updateVideo($id, $title, []);
                exit(0);
            }
            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $catalog = $this->readCatalog();
        $this->assertCount(2, $catalog['videos']);
        $titles = array_column($catalog['videos'], 'title');
        $this->assertContains('First', $titles);
        $this->assertContains('Second', $titles);
    }

    public function test_findVideoByVimeoId_returns_matching_entry(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'My Video',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => ['humor'],
                'captions' => [],
            ],
        ]]);

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111');

        $this->assertNotNull($entry);
        $this->assertSame('My Video', $entry['title']);
        $this->assertSame('111', $entry['vimeo_id']);
    }

    public function test_findVideoByVimeoId_returns_null_when_missing(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
        ]]);

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('999');

        $this->assertNull($entry);
    }

    public function test_addVideo_appends_entry_to_catalog(): void
    {
        $this->writeCatalog(['videos' => []]);

        $entry = (new CatalogEditor($this->catalogFile))->addVideo(
            vimeoId: '999',
            title: 'New Video',
            signLanguage: 'lse',
            edition: '2024-madrid',
            thumbnailUrl: 'https://example.com/thumb.jpg',
        );

        $catalog = $this->readCatalog();
        $this->assertCount(1, $catalog['videos']);
        $this->assertSame('lse_999', $entry['id']);
        $this->assertSame('999', $catalog['videos'][0]['vimeo_id']);
        $this->assertSame('New Video', $catalog['videos'][0]['title']);
        $this->assertSame('lse', $catalog['videos'][0]['sign_language']);
        $this->assertSame('2024-madrid', $catalog['videos'][0]['edition']);
        $this->assertSame([], $catalog['videos'][0]['tags']);
        $this->assertSame([], $catalog['videos'][0]['captions']);
        $this->assertSame('https://example.com/thumb.jpg', $catalog['videos'][0]['thumbnail_url']);
    }

    public function test_addVideo_throws_when_vimeo_id_already_in_catalog(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
        ]]);

        $this->expectException(\RuntimeException::class);
        (new CatalogEditor($this->catalogFile))->addVideo('111', 'Other', 'lse', 'x');
    }

    public function test_findVideoByVimeoId_returns_null_for_empty_catalog(): void
    {
        $this->writeCatalog(['videos' => []]);

        $this->assertNull((new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111'));
    }

    public function test_upsertCaptions_merges_by_language(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt']],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->upsertCaptions('111', [
            ['lang' => 'en', 'label' => 'English', 'file' => '111.en.vtt'],
            ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.new.vtt'],
        ]);

        $captions = $this->readCatalog()['videos'][0]['captions'];
        $this->assertCount(2, $captions);
        $byLang = [];
        foreach ($captions as $c) {
            $byLang[$c['lang']] = $c['file'];
        }
        $this->assertSame('111.en.vtt', $byLang['en']);
        $this->assertSame('111.es.new.vtt', $byLang['es']);
    }

    public function test_returns_referenced_vimeo_ids(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => '2020-a', 'tags' => [], 'captions' => []],
            ['id' => 'lse_222', 'vimeo_id' => '222', 'title' => 'T', 'sign_language' => 'lse', 'edition' => '2021-b', 'tags' => [], 'captions' => []],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);

        $this->assertContains('2020-a', $editor->getReferencedEditionIds());
        $this->assertContains('2021-b', $editor->getReferencedEditionIds());
        $this->assertContains('lse', $editor->getReferencedSignLanguageIds());
    }

    public function test_returns_referenced_subtitle_language_ids_from_captions(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-a',
                'tags' => [],
                'captions' => [
                    ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
                    ['lang' => 'en', 'label' => 'English', 'file' => '111.en.vtt'],
                ],
            ],
            [
                'id' => 'lse_222',
                'vimeo_id' => '222',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2021-b',
                'tags' => [],
                'captions' => [
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '222.ca.vtt'],
                ],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);

        $this->assertEqualsCanonicalizing(['es', 'en', 'ca'], $editor->getReferencedSubtitleLanguageIds());
    }
}
