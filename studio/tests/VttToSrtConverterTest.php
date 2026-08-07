<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SrtParser;
use Studio\VttParser;
use Studio\VttToSrtConverter;

class VttToSrtConverterTest extends TestCase
{
    public function test_convert_produces_valid_subrip(): void
    {
        $path = sys_get_temp_dir() . '/studio-vtt-conv-' . uniqid() . '.vtt';
        file_put_contents(
            $path,
            "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHello world\n"
        );

        $output = (new VttToSrtConverter())->convert($path);

        $this->assertStringStartsWith("1\n00:00:01,000 --> 00:00:04,000\n", $output);
        $this->assertStringNotContainsString('WEBVTT', $output);

        $parsed = (new SrtParser())->parseString($output);
        $this->assertCount(1, $parsed['cues']);
        $this->assertSame('Hello world', $parsed['cues'][0]['text']);
        $this->assertSame(1.0, $parsed['cues'][0]['start']);
        $this->assertSame(4.0, $parsed['cues'][0]['end']);
    }

    public function test_convert_drops_the_webvtt_header_and_renumbers(): void
    {
        $path = sys_get_temp_dir() . '/studio-vtt-conv-' . uniqid() . '.vtt';
        file_put_contents(
            $path,
            "WEBVTT - with metadata\n\n00:00:01.000 --> 00:00:02.000\nOne\n\n00:00:02.000 --> 00:00:03.000\nTwo\n"
        );

        $output = (new VttToSrtConverter())->convert($path);

        $this->assertStringStartsWith("1\n", $output);
        $this->assertStringContainsString("\n\n2\n", $output);
        $this->assertStringNotContainsString('metadata', $output);
    }

    /**
     * The real 27-cue production sample: every cue must survive the conversion
     * with its timing and text intact.
     */
    public function test_convert_preserves_every_cue_of_the_production_sample(): void
    {
        $path = __DIR__ . '/fixtures/production_sample.vtt';

        $source = (new VttParser())->parse($path)['cues'];
        $converted = (new SrtParser())->parseString((new VttToSrtConverter())->convert($path))['cues'];

        $this->assertGreaterThan(0, count($source));
        $this->assertCount(count($source), $converted);

        foreach ($source as $i => $cue) {
            $this->assertEqualsWithDelta($cue['start'], $converted[$i]['start'], 0.0005, "cue $i start");
            $this->assertEqualsWithDelta($cue['end'], $converted[$i]['end'], 0.0005, "cue $i end");
            $this->assertSame($cue['text'], $converted[$i]['text'], "cue $i text");
            $this->assertSame((string) ($i + 1), $converted[$i]['id'], "cue $i index");
        }
    }

    public function test_write_cues_accepts_a_bare_cue_list(): void
    {
        $output = (new VttToSrtConverter())->writeCues([
            ['start' => 0.5, 'end' => 1.25, 'text' => 'Bare', 'opaque' => '', 'id' => ''],
        ]);

        $this->assertSame("1\n00:00:00,500 --> 00:00:01,250\nBare\n", $output);
    }
}
