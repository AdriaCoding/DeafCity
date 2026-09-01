<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\CatalogSheetSync;
use Studio\GoogleSheetsClient;
use Studio\SheetCatalogParser;
use Studio\VimeoClient;
use Studio\VimeoNotFoundException;
use Studio\VimeoRateLimitedException;
use Studio\VimeoTransientException;
use Studio\VimeoUnauthorizedException;

class CatalogSheetSyncTest extends TestCase
{
    private string $catalogFile;

    protected function setUp(): void
    {
        $this->catalogFile = tempnam(sys_get_temp_dir(), 'catalog-sheet-');
        file_put_contents($this->catalogFile, json_encode(['videos' => []], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        if (is_file($this->catalogFile)) {
            unlink($this->catalogFile);
        }
    }

    public function test_upsert_preserves_captions_and_invisible(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo(
            '111',
            'Old Title',
            'lse',
            '2020-valencia',
            'https://example.com/old.jpg',
            ['OLD'],
            'acudits',
            'Aurora',
            'https://example.com/old-embed',
        );
        $editor->upsertCaptions('111', [
            ['lang' => 'es', 'label' => 'Espanyol', 'file' => 'captions/111.es.vtt'],
        ]);
        $editor->setVideoInvisible('111', true);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#APPLAUSE', 'JOKES', 'DEAF+HEARING'],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->method('fetchVideoForCatalogSync')
            ->with('111', false, false)
            ->willReturn([
                'title' => 'Vimeo Title 111',
                'thumbnail_url' => null,
                'embed_url' => null,
            ]);

        $result = $this->makeSync($sheets, $vimeo, $editor)->run();

        $this->assertSame(0, $result->added);
        $this->assertSame(1, $result->updated);
        $video = $editor->findVideoByVimeoId('111');
        $this->assertNotNull($video);
        $this->assertSame('Vimeo Title 111', $video['title']);
        $this->assertSame(['APPLAUSE', 'DEAF&HEARING'], $video['tags']);
        $this->assertSame('acudits', $video['typology']);
        $this->assertTrue($video['invisible']);
        $this->assertSame(
            [['lang' => 'es', 'label' => 'Espanyol', 'file' => 'captions/111.es.vtt']],
            $video['captions'],
        );
        $this->assertSame('https://example.com/old.jpg', $video['thumbnail_url']);
        $this->assertSame('https://example.com/old-embed', $video['embed_url']);
    }

    public function test_empty_sheet_fields_clear_tags_and_typology(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo(
            '111',
            'Old',
            'lse',
            '2020-valencia',
            null,
            ['OLD', 'TAGS'],
            'acudits',
            'Aurora',
        );

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '', '', ''],
        ]);

        $vimeo = $this->vimeoReturningTitle('From Vimeo');

        $this->makeSync($sheets, $vimeo, $editor)->run();

        $video = $editor->findVideoByVimeoId('111');
        $this->assertSame([], $video['tags']);
        $this->assertArrayNotHasKey('typology', $video);
    }

    public function test_skips_duplicate_and_vimeo_404_without_writing(): void
    {
        $editor = new CatalogEditor($this->catalogFile);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '999', '1', '#A', 'JOKES', ''],
            ['002', '', '', '999', '2', '#B', 'ANECDOTES', ''],
            ['003', '', 'Dani', '404', '1', '#C', 'JOKES', ''],
            ['004', '', '', '111', '1', '#D', 'JOKES', ''],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->method('fetchVideoForCatalogSync')->willReturnCallback(
            static function (string $id): array {
                if ($id === '404') {
                    throw new VimeoNotFoundException('not found error_code=5000');
                }
                return [
                    'title' => "Title $id",
                    'thumbnail_url' => null,
                    'embed_url' => null,
                ];
            },
        );

        $result = $this->makeSync($sheets, $vimeo, $editor)->run();

        $this->assertSame(1, $result->added);
        $this->assertGreaterThanOrEqual(2, $result->skipped);
        $this->assertNull($editor->findVideoByVimeoId('999'));
        $this->assertNull($editor->findVideoByVimeoId('404'));
        $this->assertNotNull($editor->findVideoByVimeoId('111'));
        $this->assertNotEmpty($result->warnings);
        $this->assertTrue(
            (bool) array_filter($result->warnings, static fn(string $w): bool => str_contains(strtolower($w), 'not found')),
        );
    }

    public function test_skips_unknown_city_and_clears_unknown_typology_with_warning(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('111', 'Old', 'lse', '2020-valencia', null, [], 'acudits', 'Aurora');

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'WEIRDTYPE', ''],
            ['002', '2099 Atlantis', 'X', '222', '1', '#B', 'JOKES', ''],
        ]);

        $vimeo = $this->vimeoReturningTitle('Title');

        $result = $this->makeSync($sheets, $vimeo, $editor)->run();

        $this->assertSame(1, $result->updated);
        $video = $editor->findVideoByVimeoId('111');
        $this->assertArrayNotHasKey('typology', $video);
        $this->assertNull($editor->findVideoByVimeoId('222'));
        $this->assertTrue(
            (bool) array_filter($result->warnings, static fn(string $w): bool => str_contains($w, 'WEIRDTYPE') || str_contains($w, 'typology'))
        );
        $this->assertTrue(
            (bool) array_filter($result->warnings, static fn(string $w): bool => str_contains($w, 'Atlantis') || str_contains($w, '222'))
        );
    }

    public function test_replace_removes_only_ids_absent_from_sheet(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('111', 'Keep', 'lse', '2020-valencia', null, [], null, 'Aurora');
        $editor->addVideo('222', 'Drop', 'lse', '2020-valencia', null, [], null, 'Aurora');
        $editor->upsertCaptions('222', [
            ['lang' => 'es', 'label' => 'Espanyol', 'file' => 'captions/222.es.vtt'],
        ]);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'JOKES', ''],
            ['002', '', '', '333', '2', '#B', 'JOKES', ''],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->method('fetchVideoForCatalogSync')->willReturnCallback(
            static function (string $id, bool $needThumb, bool $needEmbed): array {
                return [
                    'title' => "Title $id",
                    'thumbnail_url' => $needThumb ? 'https://example.com/t.jpg' : null,
                    'embed_url' => $needEmbed ? 'https://player.vimeo.com/video/x' : null,
                ];
            },
        );

        $result = $this->makeSync($sheets, $vimeo, $editor)->run(['replace' => true]);

        $this->assertSame(1, $result->removed);
        $this->assertSame(1, $result->added);
        $this->assertSame(1, $result->updated);
        $this->assertNotNull($editor->findVideoByVimeoId('111'));
        $this->assertNull($editor->findVideoByVimeoId('222'));
        $this->assertNotNull($editor->findVideoByVimeoId('333'));
        $this->assertSame('https://example.com/t.jpg', $editor->findVideoByVimeoId('333')['thumbnail_url']);
    }

    public function test_second_run_is_idempotent(): void
    {
        $editor = new CatalogEditor($this->catalogFile);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#APPLAUSE', 'JOKES', 'DEAF+HEARING'],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->method('fetchVideoForCatalogSync')->willReturnCallback(
            static function (string $id, bool $needThumb, bool $needEmbed): array {
                return [
                    'title' => 'Title 111',
                    'thumbnail_url' => $needThumb ? 'https://example.com/t.jpg' : null,
                    'embed_url' => $needEmbed ? 'https://player.vimeo.com/video/111' : null,
                ];
            },
        );

        $sync = $this->makeSync($sheets, $vimeo, $editor);
        $first = $sync->run();
        $before = file_get_contents($this->catalogFile);
        $second = $sync->run();
        $after = file_get_contents($this->catalogFile);

        $this->assertSame(1, $first->added);
        $this->assertSame(0, $first->updated);
        $this->assertSame(0, $second->added);
        $this->assertSame(1, $second->updated);
        $this->assertSame($before, $after);
    }

    public function test_sheets_auth_failure_aborts_without_catalog_changes(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('111', 'Keep', 'lse', '2020-valencia');
        $before = file_get_contents($this->catalogFile);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willThrowException(new \RuntimeException('Google Sheets auth failed'));

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('assertAuthenticated');
        $vimeo->expects($this->never())->method('fetchVideoForCatalogSync');

        $result = $this->makeSync($sheets, $vimeo, $editor)->run(['replace' => true]);

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('auth', strtolower($result->error));
        $this->assertSame($before, file_get_contents($this->catalogFile));
    }

    public function test_rate_limited_then_success_upserts_video(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $slept = [];
        $calls = 0;

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'JOKES', ''],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->expects($this->exactly(2))
            ->method('fetchVideoForCatalogSync')
            ->with('111', true, true)
            ->willReturnCallback(function () use (&$calls): array {
                $calls++;
                if ($calls === 1) {
                    throw new VimeoRateLimitedException('rate', time() + 5);
                }
                return [
                    'title' => 'After wait',
                    'thumbnail_url' => 'https://example.com/t.jpg',
                    'embed_url' => 'https://player.vimeo.com/video/111',
                ];
            });

        $result = $this->makeSync($sheets, $vimeo, $editor, static function (int $seconds) use (&$slept): void {
            $slept[] = $seconds;
        })->run();

        $this->assertSame(1, $result->added);
        $this->assertNull($result->error);
        $this->assertNotEmpty($slept);
        $this->assertGreaterThanOrEqual(5, $slept[0]);
        $video = $editor->findVideoByVimeoId('111');
        $this->assertSame('After wait', $video['title']);
        $this->assertFalse(
            (bool) array_filter($result->warnings, static fn(string $w): bool => str_contains(strtolower($w), 'not found')),
        );
    }

    public function test_unauthorized_aborts_before_catalog_writes(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('222', 'Keep', 'lse', '2020-valencia');
        $before = file_get_contents($this->catalogFile);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'JOKES', ''],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated')
            ->willThrowException(new VimeoUnauthorizedException('bad token error_code=8000'));
        $vimeo->expects($this->never())->method('fetchVideoForCatalogSync');

        $result = $this->makeSync($sheets, $vimeo, $editor)->run(['replace' => true]);

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('8000', $result->error);
        $this->assertSame($before, file_get_contents($this->catalogFile));
        $this->assertNotNull($editor->findVideoByVimeoId('222'));
    }

    public function test_transient_exhausted_skips_with_non_not_found_warning(): void
    {
        $editor = new CatalogEditor($this->catalogFile);

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'JOKES', ''],
            ['002', '', 'Dani', '222', '1', '#B', 'JOKES', ''],
        ]);

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->method('fetchVideoForCatalogSync')->willReturnCallback(
            static function (string $id): array {
                if ($id === '111') {
                    throw new VimeoTransientException('HTTP 503');
                }
                return [
                    'title' => 'Ok 222',
                    'thumbnail_url' => null,
                    'embed_url' => null,
                ];
            },
        );

        $result = $this->makeSync($sheets, $vimeo, $editor, static function (): void {})->run();

        $this->assertNull($editor->findVideoByVimeoId('111'));
        $this->assertNotNull($editor->findVideoByVimeoId('222'));
        $this->assertSame(1, $result->added);
        $this->assertGreaterThanOrEqual(1, $result->skipped);
        $this->assertTrue(
            (bool) array_filter(
                $result->warnings,
                static fn(string $w): bool => str_contains($w, '111') && str_contains(strtolower($w), 'transient'),
            ),
        );
        $this->assertFalse(
            (bool) array_filter(
                $result->warnings,
                static fn(string $w): bool => str_contains($w, '111') && str_contains(strtolower($w), 'not found'),
            ),
        );
    }

    public function test_mid_run_throw_rolls_back_all_catalog_changes_from_this_run(): void
    {
        $editor = new CatalogEditor($this->catalogFile);
        $editor->addVideo('999', 'Pre-existing', 'lse', '2020-valencia');
        $before = file_get_contents($this->catalogFile);

        // A CatalogEditor that behaves normally except its second
        // upsertFromSheet() call throws, simulating an unexpected failure
        // (e.g. a disk error) partway through a run that already
        // successfully applied row 1.
        $throwingEditor = new class ($this->catalogFile) extends CatalogEditor {
            private int $calls = 0;

            public function upsertFromSheet(
                string $vimeoId,
                string $title,
                string $signLanguage,
                string $edition,
                array $tags,
                ?string $typology,
                ?string $participant,
                ?string $thumbnailUrl = null,
                ?string $embedUrl = null,
            ): string {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new \RuntimeException('Simulated mid-run failure');
                }
                return parent::upsertFromSheet(
                    $vimeoId,
                    $title,
                    $signLanguage,
                    $edition,
                    $tags,
                    $typology,
                    $participant,
                    $thumbnailUrl,
                    $embedUrl,
                );
            }
        };

        $sheets = $this->createMock(GoogleSheetsClient::class);
        $sheets->method('fetchRows')->willReturn([
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'JOKES', ''],
            ['002', '', 'Dani', '222', '1', '#B', 'JOKES', ''],
        ]);

        $vimeo = $this->vimeoReturningTitle('Some Title');

        $sync = $this->makeSync($sheets, $vimeo, $throwingEditor);

        try {
            $sync->run();
            $this->fail('Expected the mid-run failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated mid-run failure', $e->getMessage());
        }

        // Both the row-1 upsert (111) applied before the throw and the
        // pre-existing video (999) must be exactly as they were before
        // this run started.
        $this->assertSame($before, file_get_contents($this->catalogFile));
        $this->assertNull($editor->findVideoByVimeoId('111'));
        $this->assertNotNull($editor->findVideoByVimeoId('999'));
    }

    private function vimeoReturningTitle(string $title): VimeoClient
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('assertAuthenticated');
        $vimeo->method('fetchVideoForCatalogSync')->willReturn([
            'title' => $title,
            'thumbnail_url' => null,
            'embed_url' => null,
        ]);
        return $vimeo;
    }

    private function makeSync(
        GoogleSheetsClient $sheets,
        VimeoClient $vimeo,
        CatalogEditor $editor,
        ?callable $sleeper = null,
    ): CatalogSheetSync {
        return new CatalogSheetSync(
            $sheets,
            $vimeo,
            $editor,
            new SheetCatalogParser([
                ['id' => 'acudits', 'label' => 'JOKES'],
                ['id' => 'anecdotes', 'label' => 'ANECDOTES'],
                ['id' => 'malentesos', 'label' => 'MISUNDERSTANDINGS'],
                ['id' => 'endevinalles', 'label' => 'RIDDLES'],
                ['id' => 'memories', 'label' => 'MEMORIES'],
                ['id' => 'pensaments', 'label' => 'THOUGHTS'],
            ]),
            $sleeper ?? static function (int $seconds): void {},
        );
    }
}
