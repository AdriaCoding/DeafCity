<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\VttParser;

class VttParserTest extends TestCase
{
    private VttParser $parser;

    protected function setUp(): void
    {
        $this->parser = new VttParser();
    }

    public function test_parses_single_cue_into_cue_array(): void
    {
        $path = $this->writeTempVtt(
            "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHello world\n"
        );

        $result = $this->parser->parse($path);

        $this->assertCount(1, $result['cues']);
        $this->assertSame(1.0, $result['cues'][0]['start']);
        $this->assertSame(4.0, $result['cues'][0]['end']);
        $this->assertSame('Hello world', $result['cues'][0]['text']);
        $this->assertSame('', $result['cues'][0]['opaque']);
    }

    public function test_write_then_parse_roundtrip_is_idempotent(): void
    {
        $original = "WEBVTT\n\n00:00:01.000 --> 00:00:04.000 align:left\nLine one\nLine two\n\n00:00:05.000 --> 00:00:08.500\nNext cue\n";
        $path = $this->writeTempVtt($original);

        $parsed = $this->parser->parse($path);
        $written = $this->parser->write($parsed);
        $reparsed = $this->parser->parseString($written);

        $this->assertSame($parsed['cues'][0]['start'], $reparsed['cues'][0]['start']);
        $this->assertSame($parsed['cues'][0]['end'], $reparsed['cues'][0]['end']);
        $this->assertSame($parsed['cues'][0]['text'], $reparsed['cues'][0]['text']);
        $this->assertSame($parsed['cues'][0]['opaque'], $reparsed['cues'][0]['opaque']);
        $this->assertSame($parsed['cues'][1]['start'], $reparsed['cues'][1]['start']);
    }

    public function test_write_produces_valid_webvtt_with_header(): void
    {
        $parsed = [
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'cues' => [
                ['start' => 1.0, 'end' => 4.0, 'text' => 'Hello', 'opaque' => ''],
            ],
        ];

        $output = $this->parser->write($parsed);

        $this->assertStringStartsWith('WEBVTT', $output);
        $this->assertStringContainsString('00:00:01.000 --> 00:00:04.000', $output);
        $this->assertStringContainsString('Hello', $output);
    }

    public function test_roundtrips_opaque_cue_attributes(): void
    {
        $path = $this->writeTempVtt(
            "WEBVTT\n\n00:00:01.000 --> 00:00:04.000 align:left position:10%\nHello\n"
        );

        $result = $this->parser->parse($path);

        $this->assertSame('align:left position:10%', $result['cues'][0]['opaque']);
    }

    public function test_parses_optional_numeric_cue_id(): void
    {
        $path = $this->writeTempVtt(
            "WEBVTT\n\n1\n00:00:01.978 --> 00:00:04.491\nHello\n"
        );

        $result = $this->parser->parse($path);

        $this->assertSame('1', $result['cues'][0]['id']);
        $written = $this->parser->write($result);
        $this->assertStringContainsString("1\n00:00:01.978 --> 00:00:04.491", $written);
    }

    public function test_preserves_multiline_cue_text(): void
    {
        $path = $this->writeTempVtt(
            "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nLine one\nLine two\n"
        );

        $result = $this->parser->parse($path);

        $this->assertSame("Line one\nLine two", $result['cues'][0]['text']);
    }

    public function test_canonicalize_splits_adjacent_inline_cues(): void
    {
        $loose = "WEBVTT\n"
            . "00:00:01.000 --> 00:00:02.000 First cue\n"
            . "00:00:02.000 --> 00:00:03.000 Second cue\n";

        $canonical = $this->parser->canonicalize($loose);
        $parsed = $this->parser->parseString($canonical);

        $this->assertCount(2, $parsed['cues']);
        $this->assertSame('First cue', $parsed['cues'][0]['text']);
        $this->assertSame('Second cue', $parsed['cues'][1]['text']);
        $this->assertStringContainsString("\n\n", $canonical);
    }

    private function writeTempVtt(string $contents): string
    {
        $dir = sys_get_temp_dir() . '/studio-vtt-parser-' . uniqid();
        mkdir($dir);
        $path = $dir . '/draft.vtt';
        file_put_contents($path, $contents);
        return $path;
    }
}
