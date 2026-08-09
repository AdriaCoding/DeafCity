<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionReader;
use Studio\CaptionWriter;

class CaptionWriterTest extends TestCase
{
    private CaptionReader $reader;
    private CaptionWriter $writer;

    protected function setUp(): void
    {
        $this->reader = new CaptionReader();
        $this->writer = new CaptionWriter();
    }

    public function test_writes_webvtt_when_format_is_vtt(): void
    {
        $output = $this->writer->write([
            'format' => 'vtt',
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'cues' => [['start' => 1.0, 'end' => 2.0, 'text' => 'Hi', 'opaque' => '', 'id' => '']],
        ]);

        $this->assertStringStartsWith("WEBVTT\n", $output);
        $this->assertStringContainsString('00:00:01.000 --> 00:00:02.000', $output);
    }

    public function test_writes_subrip_when_format_is_srt(): void
    {
        $output = $this->writer->write([
            'format' => 'srt',
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'cues' => [['start' => 1.0, 'end' => 2.0, 'text' => 'Hi', 'opaque' => '', 'id' => '']],
        ]);

        $this->assertStringNotContainsString('WEBVTT', $output);
        $this->assertSame("1\n00:00:01,000 --> 00:00:02,000\nHi\n", $output);
    }

    public function test_defaults_to_webvtt_when_format_is_absent(): void
    {
        $output = $this->writer->write([
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'cues' => [['start' => 0.0, 'end' => 1.0, 'text' => 'Hi', 'opaque' => '', 'id' => '']],
        ]);

        $this->assertStringStartsWith("WEBVTT\n", $output);
    }

    /**
     * The property the caption editors depend on: reading a file, swapping its
     * cues, and writing it back must not change the file's format. Without it,
     * saving an .srt caption would rewrite it as WebVTT.
     */
    public function test_round_trip_preserves_subrip(): void
    {
        $original = "1\n00:00:01,000 --> 00:00:02,000\nOne\n\n2\n00:00:03,000 --> 00:00:04,500\nTwo\n";

        $parsed = $this->reader->readString($original);
        $rewritten = $this->writer->write($parsed);

        $this->assertSame($original, $rewritten);
    }

    public function test_round_trip_preserves_webvtt(): void
    {
        $original = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:02.000\nOne\n";

        $parsed = $this->reader->readString($original);
        $rewritten = $this->writer->write($parsed);

        $this->assertSame($original, $rewritten);
    }

    public function test_round_trip_preserves_format_after_editing_cues(): void
    {
        foreach (['srt', 'vtt'] as $format) {
            $original = $format === 'srt'
                ? "1\n00:00:01,000 --> 00:00:02,000\nBefore\n"
                : "WEBVTT\n\n1\n00:00:01.000 --> 00:00:02.000\nBefore\n";

            $parsed = $this->reader->readString($original);
            $parsed['cues'][0]['text'] = 'After';
            $rewritten = $this->writer->write($parsed);

            $this->assertSame($format, $this->reader->readString($rewritten)['format'], $format);
            $this->assertSame('After', $this->reader->readString($rewritten)['cues'][0]['text'], $format);
        }
    }
}
