<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionReader;

class CaptionReaderTest extends TestCase
{
    private CaptionReader $reader;

    protected function setUp(): void
    {
        $this->reader = new CaptionReader();
    }

    public function test_reads_webvtt(): void
    {
        $parsed = $this->reader->readString(
            "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHello\n"
        );

        $this->assertCount(1, $parsed['cues']);
        $this->assertSame('Hello', $parsed['cues'][0]['text']);
        $this->assertSame(1.0, $parsed['cues'][0]['start']);
        $this->assertSame('WEBVTT', $parsed['header']);
    }

    public function test_reads_subrip(): void
    {
        $parsed = $this->reader->readString(
            "1\n00:00:01,000 --> 00:00:04,000\nHello\n"
        );

        $this->assertCount(1, $parsed['cues']);
        $this->assertSame('Hello', $parsed['cues'][0]['text']);
        $this->assertSame(1.0, $parsed['cues'][0]['start']);
        $this->assertSame(4.0, $parsed['cues'][0]['end']);
    }

    public function test_subrip_yields_a_writable_vtt_structure(): void
    {
        $parsed = $this->reader->readString("1\n00:00:01,000 --> 00:00:04,000\nHello\n");

        $this->assertSame('WEBVTT', $parsed['header']);
        $this->assertSame([], $parsed['opaque_blocks']);
    }

    public function test_reads_subrip_with_bom(): void
    {
        $parsed = $this->reader->readString(
            "\xEF\xBB\xBF1\n00:00:01,000 --> 00:00:04,000\nHola\n"
        );

        $this->assertCount(1, $parsed['cues']);
        $this->assertSame('Hola', $parsed['cues'][0]['text']);
    }

    public function test_reads_both_production_fixtures(): void
    {
        $vtt = $this->reader->read(__DIR__ . '/fixtures/production_sample.vtt');
        $srt = $this->reader->read(__DIR__ . '/ALGER_FR_Hamida_1.srt');

        $this->assertCount(19, $vtt['cues']);
        $this->assertCount(51, $srt['cues']);
        $this->assertSame('Un papa avec son fils.', $srt['cues'][0]['text']);
        $this->assertSame('Un grupo de personas', $vtt['cues'][0]['text']);
    }

    /**
     * A WEBVTT header wins even when the body would otherwise look SubRip-ish.
     */
    public function test_webvtt_header_takes_precedence(): void
    {
        $parsed = $this->reader->readString(
            "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHello\n"
        );

        $this->assertSame('WEBVTT', $parsed['header']);
        $this->assertCount(1, $parsed['cues']);
    }

    public function test_unrecognised_content_falls_back_to_the_vtt_parser(): void
    {
        $parsed = $this->reader->readString("something else entirely\n");

        $this->assertSame([], $parsed['cues']);
    }

    public function test_read_throws_for_missing_file(): void
    {
        $this->expectException(\RuntimeException::class);
        @$this->reader->read('/nonexistent/caption.srt');
    }
}
