<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SheetCatalogParser;

class SheetCatalogParserTest extends TestCase
{
    public function test_forward_fills_city_and_participant(): void
    {
        $rows = [
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '1211616576', '1', '#APPLAUSE', 'JOKES', 'DEAF+HEARING'],
            ['002', '', '', '1211616574', '2', '#SHOPING', 'ANECDOTES', 'DEAF+HEARING'],
            ['003', '', 'Dani', '1211615982', '1', '#HEARING AID', 'ANECDOTES', ''],
        ];

        $result = (new SheetCatalogParser())->parse($rows);

        $this->assertCount(3, $result['rows']);
        $this->assertSame('2020-valencia', $result['rows'][0]->editionId);
        $this->assertSame('Aurora', $result['rows'][0]->participant);
        $this->assertSame('2020-valencia', $result['rows'][1]->editionId);
        $this->assertSame('Aurora', $result['rows'][1]->participant);
        $this->assertSame('2020-valencia', $result['rows'][2]->editionId);
        $this->assertSame('Dani', $result['rows'][2]->participant);
    }

    public function test_maps_tags_typology_and_edition_including_tunis(): void
    {
        $rows = [
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#APPLAUSE', 'JOKES', 'DEAF+HEARING'],
            ['002', '2026 Tunis', 'Aya', '222', '1', '#HELLO', 'RIDDLES', ''],
            ['003', '2023 São Paulo', 'Ana', '333', '1', '', 'MISUNDERSTANDINGS', 'yes'],
        ];

        $result = (new SheetCatalogParser())->parse($rows);

        $this->assertSame(['APPLAUSE', 'DEAF&HEARING'], $result['rows'][0]->tags);
        $this->assertSame('acudits', $result['rows'][0]->typologyId);
        $this->assertSame('lse', $result['rows'][0]->signLanguage);

        $this->assertSame('2026-tunis', $result['rows'][1]->editionId);
        $this->assertSame('tunisian-sl', $result['rows'][1]->signLanguage);
        $this->assertSame(['HELLO'], $result['rows'][1]->tags);
        $this->assertSame('endevinalles', $result['rows'][1]->typologyId);

        $this->assertSame('2023-sao-paulo', $result['rows'][2]->editionId);
        $this->assertSame('libras', $result['rows'][2]->signLanguage);
        $this->assertSame(['DEAF&HEARING'], $result['rows'][2]->tags);
        $this->assertSame('malentesos', $result['rows'][2]->typologyId);
    }

    public function test_stops_before_stats_exports_junk_rows(): void
    {
        $rows = [
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'JOKES', ''],
            ['', 'STATS', 'CLIPS', '', '#REF!', '', '', ''],
            ['002', '2021 Mexico', 'Beto', '222', '1', '#B', 'MEMORIES', ''],
        ];

        $result = (new SheetCatalogParser())->parse($rows);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('111', $result['rows'][0]->vimeoId);
    }

    public function test_skips_duplicate_vimeo_ids_with_warning(): void
    {
        $rows = [
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '999', '1', '#A', 'JOKES', ''],
            ['002', '', '', '999', '2', '#B', 'ANECDOTES', ''],
            ['003', '', 'Dani', '111', '1', '#C', 'JOKES', ''],
        ];

        $result = (new SheetCatalogParser())->parse($rows);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('111', $result['rows'][0]->vimeoId);
        $this->assertSame(['999'], $result['duplicateVimeoIds']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('999', $result['warnings'][0]);
    }

    public function test_skips_rows_without_numeric_vimeo_id(): void
    {
        $rows = [
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '', '1', '#A', 'JOKES', ''],
            ['002', '', '', 'not-an-id', '2', '#B', 'JOKES', ''],
            ['003', '', 'Dani', '111', '1', '#C', 'JOKES', ''],
        ];

        $result = (new SheetCatalogParser())->parse($rows);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('111', $result['rows'][0]->vimeoId);
        $this->assertSame([], $result['duplicateVimeoIds']);
    }

    public function test_marks_unknown_typology_and_skips_unknown_city(): void
    {
        $rows = [
            ['', 'Ciutats', 'Participants', 'Vimeo', '', 'TÍTOLS', 'ANECDOTES', 'DEAF+HEARING'],
            ['001', '2020 València', 'Aurora', '111', '1', '#A', 'NOTATYPE', ''],
            ['002', '2099 Atlantis', 'X', '222', '1', '#B', 'JOKES', ''],
        ];

        $result = (new SheetCatalogParser())->parse($rows);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('111', $result['rows'][0]->vimeoId);
        $this->assertNull($result['rows'][0]->typologyId);
        $this->assertTrue($result['rows'][0]->unknownTypology);
        $this->assertSame('NOTATYPE', $result['rows'][0]->rawTypology);
        $this->assertTrue(
            (bool) array_filter($result['warnings'], static fn(string $w): bool => str_contains($w, 'Atlantis') || str_contains($w, '222'))
        );
    }
}
