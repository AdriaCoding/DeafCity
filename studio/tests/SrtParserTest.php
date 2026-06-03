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

    private function writeTemp(string $contents): string
    {
        $path = sys_get_temp_dir() . '/studio-srt-' . uniqid() . '.srt';
        file_put_contents($path, $contents);
        return $path;
    }
}
