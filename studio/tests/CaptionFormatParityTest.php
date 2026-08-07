<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SrtParser;
use Studio\SrtToVttConverter;
use Studio\VttParser;
use Studio\VttToSrtConverter;

/**
 * Production-shaped WebVTT and SubRip intake files share the same cue model:
 * optional index, timestamp line, text. SRT uses commas in timestamps; VTT uses dots.
 *
 * This is the regression net for the VTT→SRT migration. Both conversion
 * directions must stay lossless at the cue level for the whole of it — if this
 * file goes red, captions are being corrupted somewhere.
 */
class CaptionFormatParityTest extends TestCase
{
    private VttParser $vttParser;
    private SrtParser $srtParser;

    protected function setUp(): void
    {
        $this->vttParser = new VttParser();
        $this->srtParser = new SrtParser();
    }

    public function test_production_vtt_and_converted_srt_share_cue_shape(): void
    {
        $production = $this->vttParser->parse(
            __DIR__ . '/fixtures/production_sample.vtt'
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

    /**
     * The new primary direction: VTT→SRT must preserve every cue exactly.
     */
    public function test_vtt_to_srt_is_lossless_at_the_cue_level(): void
    {
        $path = __DIR__ . '/fixtures/production_sample.vtt';

        $source = $this->vttParser->parse($path)['cues'];
        $converted = $this->srtParser->parseString(
            (new VttToSrtConverter())->convert($path)
        )['cues'];

        $this->assertGreaterThan(0, count($source));
        $this->assertSameCues($source, $converted);
    }

    /**
     * And the intake direction, still needed for VTT uploads.
     */
    public function test_srt_to_vtt_is_lossless_at_the_cue_level(): void
    {
        $path = __DIR__ . '/ALGER_FR_Hamida_1.srt';

        $source = $this->srtParser->parse($path)['cues'];
        $converted = $this->vttParser->parseString(
            (new SrtToVttConverter())->convert($path)
        )['cues'];

        $this->assertGreaterThan(0, count($source));
        $this->assertSameCues($source, $converted);
    }

    /**
     * A caption crossing the boundary in both directions — the shape of an SRT
     * upload that later gets re-served as SRT — must come back unchanged.
     */
    public function test_srt_survives_a_full_round_trip_through_vtt(): void
    {
        $path = __DIR__ . '/ALGER_FR_Hamida_1.srt';

        $original = $this->srtParser->parse($path)['cues'];
        $vtt = (new SrtToVttConverter())->convert($path);
        $backToSrt = (new VttToSrtConverter())->writeCues(
            $this->vttParser->parseString($vtt)['cues']
        );

        $this->assertSameCues($original, $this->srtParser->parseString($backToSrt)['cues']);
    }

    public function test_converted_srt_has_subrip_document_shape(): void
    {
        $output = (new VttToSrtConverter())->convert(__DIR__ . '/fixtures/production_sample.vtt');

        $this->assertStringNotContainsString('WEBVTT', $output);
        $this->assertStringStartsWith("1\n00:00:01,978 --> 00:00:04,491", $output);

        $blocks = preg_split('/\n\n+/', trim($output));
        foreach ($blocks as $i => $block) {
            $lines = explode("\n", $block);
            $this->assertSame((string) ($i + 1), $lines[0], "block $i index");
            $this->assertMatchesRegularExpression(
                '/^\d{2}:\d{2}:\d{2},\d{3} --> \d{2}:\d{2}:\d{2},\d{3}$/',
                $lines[1],
                "block $i timing"
            );
        }
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $expected
     * @param list<array{start: float, end: float, text: string}> $actual
     */
    private function assertSameCues(array $expected, array $actual): void
    {
        $this->assertCount(count($expected), $actual, 'cue count');

        foreach ($expected as $i => $cue) {
            $this->assertEqualsWithDelta($cue['start'], $actual[$i]['start'], 0.0005, "cue $i start");
            $this->assertEqualsWithDelta($cue['end'], $actual[$i]['end'], 0.0005, "cue $i end");
            $this->assertSame($cue['text'], $actual[$i]['text'], "cue $i text");
        }
    }

    public function test_production_vtt_roundtrips_cue_ids(): void
    {
        $path = __DIR__ . '/fixtures/production_sample.vtt';
        $parsed = $this->vttParser->parse($path);
        $written = $this->vttParser->write($parsed);
        $reparsed = $this->vttParser->parseString($written);

        $this->assertSame($parsed['cues'][0]['id'], $reparsed['cues'][0]['id']);
        $this->assertStringContainsString("1\n00:00:01.978 --> 00:00:04.491", $written);
    }
}
