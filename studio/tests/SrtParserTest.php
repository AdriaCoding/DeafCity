<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SrtParser;

class SrtParserTest extends TestCase
{
    private SrtParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SrtParser();
    }

    public function test_parses_single_cue(): void
    {
        $path = $this->writeTemp(
            "1\n00:00:01,000 --> 00:00:04,000\nHello world\n"
        );

        $result = $this->parser->parse($path);

        $this->assertCount(1, $result['cues']);
        $this->assertSame(1.0, $result['cues'][0]['start']);
        $this->assertSame(4.0, $result['cues'][0]['end']);
        $this->assertSame('Hello world', $result['cues'][0]['text']);
        $this->assertSame('', $result['cues'][0]['opaque']);
        $this->assertSame('1', $result['cues'][0]['id']);
    }

    public function test_parses_multi_line_cue_text(): void
    {
        $path = $this->writeTemp(
            "1\n00:00:01,000 --> 00:00:04,000\nLine one\nLine two\n\n2\n00:00:05,000 --> 00:00:08,500\nNext\n"
        );

        $result = $this->parser->parse($path);

        $this->assertCount(2, $result['cues']);
        $this->assertSame("Line one\nLine two", $result['cues'][0]['text']);
    }

    public function test_parses_file_with_utf8_bom(): void
    {
        $path = $this->writeTemp(
            "\xEF\xBB\xBF1\n00:00:01,000 --> 00:00:04,000\nHola\n"
        );

        $result = $this->parser->parse($path);

        $this->assertSame('Hola', $result['cues'][0]['text']);
    }

    public function test_passes_through_inline_markup(): void
    {
        $path = $this->writeTemp(
            "1\n00:00:01,000 --> 00:00:04,000\n<i>Italic</i> {\\an8}\n"
        );

        $result = $this->parser->parse($path);

        $this->assertSame('<i>Italic</i> {\an8}', $result['cues'][0]['text']);
    }

    public function test_parses_timestamp_without_hours(): void
    {
        $path = $this->writeTemp(
            "1\n00:01,000 --> 00:04,000\nShort\n"
        );

        $result = $this->parser->parse($path);

        $this->assertSame(1.0, $result['cues'][0]['start']);
        $this->assertSame(4.0, $result['cues'][0]['end']);
    }

    public function test_rejects_empty_file(): void
    {
        $path = $this->writeTemp('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('buit');
        $this->parser->parse($path);
    }

    public function test_rejects_invalid_utf8(): void
    {
        $path = $this->writeTemp("\xFF\xFE1\n00:00:01,000 --> 00:00:04,000\nHola\n");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UTF-8');
        $this->parser->parse($path);
    }

    public function test_rejects_bad_cue_index(): void
    {
        $path = $this->writeTemp(
            "abc\n00:00:01,000 --> 00:00:04,000\nHola\n"
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('índex');
        $this->parser->parse($path);
    }

    public function test_rejects_bad_timestamp(): void
    {
        $path = $this->writeTemp(
            "1\n00:00:01.000 --> 00:00:04.000\nHola\n"
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('marca de temps');
        $this->parser->parse($path);
    }

    public function test_rejects_cue_without_text(): void
    {
        $path = $this->writeTemp(
            "1\n00:00:01,000 --> 00:00:04,000\n"
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('text');
        $this->parser->parse($path);
    }

    public function test_write_emits_indexed_comma_timestamped_blocks(): void
    {
        $output = $this->parser->write([
            ['start' => 1.0, 'end' => 4.0, 'text' => 'Hello world', 'opaque' => '', 'id' => ''],
            ['start' => 5.25, 'end' => 8.5, 'text' => "Line one\nLine two", 'opaque' => '', 'id' => ''],
        ]);

        $this->assertSame(
            "1\n00:00:01,000 --> 00:00:04,000\nHello world\n\n"
            . "2\n00:00:05,250 --> 00:00:08,500\nLine one\nLine two\n",
            $output
        );
    }

    public function test_write_renumbers_indices_sequentially(): void
    {
        $output = $this->parser->write([
            ['start' => 0.0, 'end' => 1.0, 'text' => 'a', 'opaque' => '', 'id' => '7'],
            ['start' => 1.0, 'end' => 2.0, 'text' => 'b', 'opaque' => '', 'id' => '99'],
        ]);

        $this->assertStringStartsWith("1\n", $output);
        $this->assertStringContainsString("\n\n2\n", $output);
        $this->assertStringNotContainsString('99', $output);
    }

    public function test_write_formats_hours(): void
    {
        $output = $this->parser->write([
            ['start' => 3661.5, 'end' => 3662.0, 'text' => 'x', 'opaque' => '', 'id' => ''],
        ]);

        $this->assertStringContainsString('01:01:01,500 --> 01:01:02,000', $output);
    }

    /**
     * A float landing a hair under a second boundary must not round up into a
     * 4-digit millisecond field — that would emit a timestamp parse() rejects.
     */
    public function test_write_does_not_overflow_milliseconds(): void
    {
        $output = $this->parser->write([
            ['start' => 4.9999, 'end' => 59.99999, 'text' => 'edge', 'opaque' => '', 'id' => ''],
        ]);

        $this->assertStringContainsString('00:00:05,000 --> 00:01:00,000', $output);
        $this->assertCount(1, $this->parser->parseString($output)['cues']);
    }

    public function test_write_output_is_reparseable(): void
    {
        $cues = [
            ['start' => 1.978, 'end' => 4.491, 'text' => 'Un grupo de personas', 'opaque' => '', 'id' => ''],
            ['start' => 4.767, 'end' => 8.414, 'text' => 'deciden ir a pasear.', 'opaque' => '', 'id' => ''],
        ];

        $reparsed = $this->parser->parseString($this->parser->write($cues))['cues'];

        $this->assertCount(2, $reparsed);
        foreach ($cues as $i => $cue) {
            $this->assertEqualsWithDelta($cue['start'], $reparsed[$i]['start'], 0.0005);
            $this->assertEqualsWithDelta($cue['end'], $reparsed[$i]['end'], 0.0005);
            $this->assertSame($cue['text'], $reparsed[$i]['text']);
        }
    }

    private function writeTemp(string $contents): string
    {
        $path = sys_get_temp_dir() . '/studio-srt-' . uniqid() . '.srt';
        file_put_contents($path, $contents);
        return $path;
    }
}
