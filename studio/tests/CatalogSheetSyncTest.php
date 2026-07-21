<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\CatalogSheetSync;
use Studio\GoogleSheetsClient;
use Studio\SheetCatalogParser;
use Studio\VimeoClient;
use Studio\VimeoNotFoundException;

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
        $vimeo->method('getVideo')->with('111')->willReturn('Vimeo Title 111');
        $vimeo->expects($this->never())->method('getThumbnailUrl');
        $vimeo->expects($this->never())->method('getPlayerEmbedUrl');

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

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('From Vimeo');

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
        $vimeo->method('getVideo')->willReturnCallback(static function (string $id): string {
            if ($id === '404') {
                throw new VimeoNotFoundException('not found');
            }
            return "Title $id";
        });
        $vimeo->method('getThumbnailUrl')->willReturn(null);
        $vimeo->method('getPlayerEmbedUrl')->willReturn(null);

        $result = $this->makeSync($sheets, $vimeo, $editor)->run();

        $this->assertSame(1, $result->added);
        $this->assertGreaterThanOrEqual(2, $result->skipped);
        $this->assertNull($editor->findVideoByVimeoId('999'));
        $this->assertNull($editor->findVideoByVimeoId('404'));
        $this->assertNotNull($editor->findVideoByVimeoId('111'));
        $this->assertNotEmpty($result->warnings);
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

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getVideo')->willReturn('Title');
        $vimeo->method('getThumbnailUrl')->willReturn(null);
        $vimeo->method('getPlayerEmbedUrl')->willReturn(null);

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
        $vimeo->method('getVideo')->willReturnCallback(static fn(string $id): string => "Title $id");
        $vimeo->method('getThumbnailUrl')->willReturn('https://example.com/t.jpg');
        $vimeo->method('getPlayerEmbedUrl')->willReturn('https://player.vimeo.com/video/x');

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
        $vimeo->method('getVideo')->willReturn('Title 111');
        $vimeo->method('getThumbnailUrl')->willReturn('https://example.com/t.jpg');
        $vimeo->method('getPlayerEmbedUrl')->willReturn('https://player.vimeo.com/video/111');

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
        $vimeo->expects($this->never())->method('getVideo');

        $result = $this->makeSync($sheets, $vimeo, $editor)->run(['replace' => true]);

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('auth', strtolower($result->error));
        $this->assertSame($before, file_get_contents($this->catalogFile));
    }

    private function makeSync(
        GoogleSheetsClient $sheets,
        VimeoClient $vimeo,
        CatalogEditor $editor,
    ): CatalogSheetSync {
        return new CatalogSheetSync($sheets, $vimeo, $editor, new SheetCatalogParser());
    }
}
