<?php

namespace Studio\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Studio\CueChunker;

/**
 * Runs the shared golden fixture (tests/fixtures/cue_chunker_cases.json) through
 * the PHP CueChunker. The Python suite (test_cue_chunker_parity.py) runs the
 * SAME fixture through the Python chunker, so both implementations are pinned to
 * identical cue output. Timestamps are compared at millisecond resolution (3dp),
 * the WebVTT grid, to stay immune to cross-language float formatting.
 */
class CueChunkerParityTest extends TestCase
{
    /** @return array<string, array{0: array, 1: array, 2: array}> */
    public static function fixtureCases(): array
    {
        $json = file_get_contents(__DIR__ . '/fixtures/cue_chunker_cases.json');
        $cases = json_decode((string) $json, true);
        $out = [];
        foreach ($cases as $case) {
            $out[$case['name']] = [
                $case['words'],
                $case['expected'],
                $case['params'] ?? [],
            ];
        }
        return $out;
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $words
     * @param list<array{start: float, end: float, text: string}> $expected
     * @param array<string, mixed> $params
     */
    #[DataProvider('fixtureCases')]
    public function test_fixture_case(array $words, array $expected, array $params): void
    {
        $cues = (new CueChunker($params))->chunk($words);

        $this->assertCount(count($expected), $cues);
        foreach ($expected as $i => $want) {
            $this->assertSame($want['text'], $cues[$i]['text']);
            $this->assertSame(round((float) $want['start'], 3), round((float) $cues[$i]['start'], 3));
            $this->assertSame(round((float) $want['end'], 3), round((float) $cues[$i]['end'], 3));
        }
    }
}
