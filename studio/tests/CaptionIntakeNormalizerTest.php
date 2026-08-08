<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionIntakeNormalizer;
use Studio\VttParser;

class CaptionIntakeNormalizerTest extends TestCase
{
    private CaptionIntakeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CaptionIntakeNormalizer();
    }

    public function test_passes_webvtt_through_unchanged(): void
    {
        $content = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHola\n";
        $path = $this->writeTemp($content, 'vtt');

        $this->assertSame($content, $this->normalizer->normalize($path, 'subtitles.vtt'));
    }

    public function test_converts_subrip_upload(): void
    {
        $path = $this->writeTemp("1\n00:00:01,000 --> 00:00:04,000\nHola\n", 'srt');

        $output = $this->normalizer->normalize($path, 'subtitles.srt');

        $this->assertStringStartsWith("WEBVTT\n", $output);
        $parsed = (new VttParser())->parseString($output);
        $this->assertCount(1, $parsed['cues']);
        $this->assertSame('Hola', $parsed['cues'][0]['text']);
        $this->assertSame(1.0, $parsed['cues'][0]['start']);
    }

    /**
     * Detection is content-based, so an .srt that is really WebVTT (and the
     * reverse) is handled by what is inside the file, not by its name.
     */
    public function test_detects_by_content_not_extension(): void
    {
        $vttNamedSrt = $this->writeTemp("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n", 'srt');

        $this->assertStringStartsWith("WEBVTT\n", $this->normalizer->normalize($vttNamedSrt, 'x.vtt'));
    }

    public function test_rejects_webvtt_without_header(): void
    {
        $path = $this->writeTemp("NOT A HEADER\n\n00:00:01.000 --> 00:00:02.000\nHi\n", 'vtt');

        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer->normalize($path, 'subtitles.vtt');
    }

    public function test_rejects_non_caption_extension(): void
    {
        $path = $this->writeTemp("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n", 'txt');

        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer->normalize($path, 'subtitles.txt');
    }

    public function test_rejects_malformed_subrip(): void
    {
        $path = $this->writeTemp("1\n00:00:01,000 --> 00:00:04,000\n", 'srt');

        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer->normalize($path, 'subtitles.srt');
    }

    public function test_rejects_empty_subrip(): void
    {
        $path = $this->writeTemp("1\n", 'srt');

        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer->normalize($path, 'subtitles.srt');
    }

    public function test_converts_the_production_subrip_fixture(): void
    {
        $output = $this->normalizer->normalize(
            __DIR__ . '/ALGER_FR_Hamida_1.srt',
            'ALGER_FR_Hamida_1.srt'
        );

        $parsed = (new VttParser())->parseString($output);
        $this->assertCount(51, $parsed['cues']);
        $this->assertSame('Un papa avec son fils.', $parsed['cues'][0]['text']);
    }

    private function writeTemp(string $contents, string $ext): string
    {
        $path = sys_get_temp_dir() . '/studio-normalizer-' . uniqid() . '.' . $ext;
        file_put_contents($path, $contents);
        return $path;
    }
}
