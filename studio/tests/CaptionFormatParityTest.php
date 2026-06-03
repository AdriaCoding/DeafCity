<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SrtToVttConverter;
use Studio\VttParser;

/**
 * Production WebVTT (data/captions/) and SubRip intake files share the same cue model:
 * optional index, timestamp line, text. SRT uses commas in timestamps; VTT uses dots.
 */
class CaptionFormatParityTest extends TestCase
{
    private VttParser $vttParser;

    protected function setUp(): void
    {
        $this->vttParser = new VttParser();
    }

    public function test_production_vtt_and_converted_srt_share_cue_shape(): void
    {
        $production = $this->vttParser->parse(
            dirname(__DIR__, 2) . '/data/captions/501686486.es.vtt'
        );
        $converted = $this->vttParser->parseString(
            (new SrtToVttConverter())->convert(__DIR__ . '/ALGER_FR_Hamida_1.srt')
        );

        $this->assertGreaterThan(0, count($production['cues']));
        $this->assertGreaterThan(0, count($converted['cues']));

        foreach ([$production, $converted] as $parsed) {
            $cue = $parsed['cues'][0];
            $this->assertArrayHasKey('start', $cue);
            $this->assertArrayHasKey('end', $cue);
            $this->assertArrayHasKey('text', $cue);
            $this->assertArrayHasKey('id', $cue);
            $this->assertIsFloat($cue['start']);
            $this->assertIsFloat($cue['end']);
            $this->assertGreaterThan($cue['start'], $cue['end']);
            $this->assertNotSame('', trim($cue['text']));
            $this->assertMatchesRegularExpression('/^\d+$/', $cue['id']);
        }

        $this->assertSame('1', $production['cues'][0]['id']);
        $this->assertSame('1', $converted['cues'][0]['id']);
    }

    public function test_production_vtt_roundtrips_cue_ids(): void
    {
        $path = dirname(__DIR__, 2) . '/data/captions/501686486.es.vtt';
        $parsed = $this->vttParser->parse($path);
        $written = $this->vttParser->write($parsed);
        $reparsed = $this->vttParser->parseString($written);

        $this->assertSame($parsed['cues'][0]['id'], $reparsed['cues'][0]['id']);
        $this->assertStringContainsString("1\n00:00:01.978 --> 00:00:04.491", $written);
    }
}
