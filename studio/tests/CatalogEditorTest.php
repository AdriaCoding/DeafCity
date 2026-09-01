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
        @unlink($this->catalogFile . '.lock');
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

    public function test_updates_sign_language_and_edition_and_regenerates_id(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Old',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->updateVideo('111', 'New', [], null, 'libras', '2023-sao-paulo');

        $entry = $this->readCatalog()['videos'][0];

        $this->assertSame('libras', $entry['sign_language']);
        $this->assertSame('2023-sao-paulo', $entry['edition']);
        $this->assertSame('libras_111', $entry['id']);
    }

    public function test_leaves_sign_language_and_edition_when_omitted(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Old',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->updateVideo('111', 'New', [], null, '', '');

        $entry = $this->readCatalog()['videos'][0];

        $this->assertSame('lse', $entry['sign_language']);
        $this->assertSame('2020-valencia', $entry['edition']);
        $this->assertSame('lse_111', $entry['id']);
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

    public function test_addVideo_stores_participant_when_provided(): void
    {
        $this->writeCatalog(['videos' => []]);

        (new CatalogEditor($this->catalogFile))->addVideo(
            vimeoId: '999',
            title: '2026_ROMA_Serena_3_HD',
            signLanguage: 'lis',
            edition: '2026-rome',
            participant: 'Serena',
        );

        $entry = $this->readCatalog()['videos'][0];
        $this->assertSame('Serena', $entry['participant']);
    }

    public function test_upsertFromSheet_preserves_existing_participant_when_incoming_empty(): void
    {
        $this->writeCatalog(['videos' => []]);
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo(
            vimeoId: '111',
            title: 'Old',
            signLanguage: 'lse',
            edition: '2020-valencia',
            participant: 'Aurora',
        );

        $editor->upsertFromSheet(
            vimeoId: '111',
            title: 'Updated',
            signLanguage: 'lse',
            edition: '2020-valencia',
            tags: ['APPLAUSE'],
            typology: 'acudits',
            participant: null,
        );

        $entry = $editor->findVideoByVimeoId('111');
        $this->assertSame('Aurora', $entry['participant']);
    }

    public function test_upsertFromSheet_updates_participant_when_incoming_provided(): void
    {
        $this->writeCatalog(['videos' => []]);
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo(
            vimeoId: '111',
            title: 'Old',
            signLanguage: 'lse',
            edition: '2020-valencia',
            participant: 'Aurora',
        );

        $editor->upsertFromSheet(
            vimeoId: '111',
            title: 'Updated',
            signLanguage: 'lse',
            edition: '2020-valencia',
            tags: [],
            typology: null,
            participant: 'Dani',
        );

        $entry = $editor->findVideoByVimeoId('111');
        $this->assertSame('Dani', $entry['participant']);
    }

    public function test_addVideo_throws_when_vimeo_id_already_in_catalog(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'lse_111', 'vimeo_id' => '111', 'title' => 'T', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []],
        ]]);

        $this->expectException(\RuntimeException::class);
        (new CatalogEditor($this->catalogFile))->addVideo('111', 'Other', 'lse', 'x');
    }

    public function test_concurrent_addVideo_for_same_vimeo_id_does_not_duplicate(): void
    {
        $this->writeCatalog(['videos' => []]);

        // Child A simulates a slow concurrent writer: it grabs the exclusive
        // lock (via the dedicated .lock file — see CatalogEditor::
        // withLockedCatalog(), which never locks catalogFilePath itself
        // because that path gets replaced by rename() on every commit),
        // holds it, and only then commits the '999' entry via the same
        // write-temp-then-rename protocol. This opens exactly the window the
        // race lived in — a window where an unlocked pre-check
        // (findVideoByVimeoId) run by another process still sees an empty
        // catalog.
        $writerPid = pcntl_fork();
        if ($writerPid === 0) {
            $lockFp = fopen($this->catalogFile . '.lock', 'c');
            flock($lockFp, LOCK_EX);
            usleep(300_000);
            $raw = file_get_contents($this->catalogFile);
            $catalog = json_decode($raw ?: '', true) ?: ['videos' => []];
            $catalog['videos'][] = ['id' => 'lse_999', 'vimeo_id' => '999', 'title' => 'Writer', 'sign_language' => 'lse', 'edition' => 'x', 'tags' => [], 'captions' => []];
            $tmp = $this->catalogFile . '.tmp-writer-' . getmypid();
            file_put_contents($tmp, json_encode($catalog));
            rename($tmp, $this->catalogFile);
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            exit(0);
        }

        // Child B: waits just long enough for the writer to hold the lock
        // (but not to have written yet), then races addVideo() for the same
        // vimeo_id. Its unlocked pre-check will see the still-empty catalog;
        // it then blocks on the lock until the writer commits. The fix must
        // re-check for the duplicate *after* acquiring the lock.
        $racerPid = pcntl_fork();
        if ($racerPid === 0) {
            usleep(100_000);
            $editor = new CatalogEditor($this->catalogFile);
            try {
                $editor->addVideo('999', 'Racer', 'lse', 'x');
                exit(0);
            } catch (\RuntimeException) {
                exit(1);
            }
        }

        pcntl_waitpid($writerPid, $writerStatus);
        pcntl_waitpid($racerPid, $racerStatus);

        // The racer must lose: its post-lock re-check should reject the
        // duplicate the writer already committed.
        $this->assertSame(1, pcntl_wexitstatus($racerStatus));

        $catalog = $this->readCatalog();
        $entries = array_values(array_filter(
            $catalog['videos'],
            static fn(array $e): bool => ($e['vimeo_id'] ?? '') === '999',
        ));
        $this->assertCount(1, $entries);
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

    public function test_deleteCaption_removes_matching_lang_from_captions(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-a',
                'tags' => [],
                'master_caption_lang' => 'ca',
                'captions' => [
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                    ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
                ],
            ],
        ]]);

        (new CatalogEditor($this->catalogFile))->deleteCaption('111', 'es');

        $captions = $this->readCatalog()['videos'][0]['captions'];
        $this->assertCount(1, $captions);
        $this->assertSame('ca', $captions[0]['lang']);
    }

    public function test_deleteCaption_promotes_first_remaining_when_master_deleted(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-a',
                'tags' => [],
                'master_caption_lang' => 'ca',
                'captions' => [
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                    ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
                    ['lang' => 'en', 'label' => 'English', 'file' => '111.en.vtt'],
                ],
            ],
        ]]);

        (new CatalogEditor($this->catalogFile))->deleteCaption('111', 'ca');

        $entry = $this->readCatalog()['videos'][0];
        $this->assertSame('es', $entry['master_caption_lang']);
        $this->assertSame('es', $entry['captions'][0]['lang']);
    }

    public function test_deleteCaption_unsets_master_when_last_caption_removed(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-a',
                'tags' => [],
                'master_caption_lang' => 'ca',
                'captions' => [
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                ],
            ],
        ]]);

        (new CatalogEditor($this->catalogFile))->deleteCaption('111', 'ca');

        $entry = $this->readCatalog()['videos'][0];
        $this->assertSame([], $entry['captions']);
        $this->assertArrayNotHasKey('master_caption_lang', $entry);
    }

    public function test_deleteCaption_leaves_master_unchanged_when_non_master_deleted(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-a',
                'tags' => [],
                'master_caption_lang' => 'ca',
                'captions' => [
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                    ['lang' => 'es', 'label' => 'Spanish', 'file' => '111.es.vtt'],
                ],
            ],
        ]]);

        (new CatalogEditor($this->catalogFile))->deleteCaption('111', 'es');

        $entry = $this->readCatalog()['videos'][0];
        $this->assertSame('ca', $entry['master_caption_lang']);
    }

    public function test_deleteCaption_throws_when_lang_not_in_catalog(): void
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
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '111.ca.vtt'],
                ],
            ],
        ]]);

        $this->expectException(\InvalidArgumentException::class);
        (new CatalogEditor($this->catalogFile))->deleteCaption('111', 'es');
    }

    public function test_deleteCaption_throws_when_video_not_found(): void
    {
        $this->writeCatalog(['videos' => []]);

        $this->expectException(\RuntimeException::class);
        (new CatalogEditor($this->catalogFile))->deleteCaption('999', 'ca');
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

    public function test_setVideoInvisible_sets_invisible_flag(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => ['tag-a'],
                'captions' => [],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->setVideoInvisible('111', true);

        $entry = $this->readCatalog()['videos'][0];
        $this->assertTrue($entry['invisible']);
        $this->assertSame('T', $entry['title']);
        $this->assertSame('2020-valencia', $entry['edition']);
    }

    public function test_setVideoInvisible_clears_flag_when_restored(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'invisible' => true,
                'captions' => [],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->setVideoInvisible('111', false);

        $entry = $this->readCatalog()['videos'][0];
        $this->assertArrayNotHasKey('invisible', $entry);
    }

    public function test_setVideoInvisible_throws_when_video_not_found(): void
    {
        $this->writeCatalog(['videos' => []]);

        $this->expectException(\RuntimeException::class);
        (new CatalogEditor($this->catalogFile))->setVideoInvisible('999', true);
    }

    public function test_isVideoVisible_treats_missing_flag_as_visible(): void
    {
        $editor = new CatalogEditor($this->catalogFile);

        $this->assertTrue($editor->isVideoVisible(['vimeo_id' => '111']));
        $this->assertFalse($editor->isVideoVisible(['vimeo_id' => '111', 'invisible' => true]));
    }

    public function test_getReferencedEditionIds_includes_invisible_videos(): void
    {
        $this->writeCatalog(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'T',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'invisible' => true,
                'captions' => [],
            ],
        ]]);

        $editor = new CatalogEditor($this->catalogFile);

        $this->assertSame(['2020-valencia'], $editor->getReferencedEditionIds());
    }

    public function test_writes_commit_via_rename_never_leaving_a_truncated_file_mid_write(): void
    {
        $this->writeCatalog(['videos' => []]);
        $originalInode = fileinode($this->catalogFile);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('111', 'Title', 'lse', '2020-valencia');

        // A commit via write-temp-then-rename always replaces the inode —
        // an in-place ftruncate()+fwrite() would keep the original inode
        // and expose a torn (briefly empty) file to any concurrent
        // unlocked reader such as getAllVideos().
        clearstatcache(true, $this->catalogFile);
        $this->assertNotSame($originalInode, fileinode($this->catalogFile));

        // No leftover temp file after a successful commit.
        $leftovers = glob($this->catalogFile . '.tmp-*');
        $this->assertSame([], $leftovers);

        $catalog = $this->readCatalog();
        $this->assertSame('111', $catalog['videos'][0]['vimeo_id']);
    }

    public function test_write_preserves_existing_file_permission_bits(): void
    {
        $this->writeCatalog(['videos' => []]);
        chmod($this->catalogFile, 0664);

        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('111', 'Title', 'lse', '2020-valencia');

        clearstatcache(true, $this->catalogFile);
        $this->assertSame('0664', substr(sprintf('%o', fileperms($this->catalogFile)), -4));
    }
}
