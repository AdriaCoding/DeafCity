<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionIntakeNormalizer;
use Studio\SrtParser;

class CaptionIntakeNormalizerTest extends TestCase
{
    private CaptionIntakeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CaptionIntakeNormalizer();
    }

    public function test_passes_subrip_through_unchanged(): void
    {
        $content = "1\n00:00:01,000 --> 00:00:04,000\nHola\n";
        $path = $this->writeTemp($content, 'srt');

        $this->assertSame($content, $this->normalizer->normalize($path, 'subtitles.srt'));
    }

    /** Content, not extension, decides the branch — a SubRip .txt is still accepted. */
    public function test_accepts_subrip_content_under_any_extension(): void
    {
        $content = "1\n00:00:01,000 --> 00:00:04,000\nHola\n";
        $path = $this->writeTemp($content, 'txt');

        $this->assertSame($content, $this->normalizer->normalize($path, 'captions.txt'));
    }

    public function test_converts_webvtt_upload(): void
    {
        $path = $this->writeTemp("WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHola\n", 'vtt');

        $output = $this->normalizer->normalize($path, 'subtitles.vtt');

        $this->assertStringNotContainsString('WEBVTT', $output);
        $this->assertStringStartsWith("1\n00:00:01,000 --> 00:00:04,000\n", $output);
        $parsed = (new SrtParser())->parseString($output);
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

        $output = $this->normalizer->normalize($vttNamedSrt, 'x.vtt');

        $this->assertStringNotContainsString('WEBVTT', $output);
        $this->assertStringStartsWith("1\n", $output);
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

    public function test_passes_the_production_subrip_fixture_through(): void
    {
        $output = $this->normalizer->normalize(
            __DIR__ . '/ALGER_FR_Hamida_1.srt',
            'ALGER_FR_Hamida_1.srt'
        );

        $this->assertSame(file_get_contents(__DIR__ . '/ALGER_FR_Hamida_1.srt'), $output);
    }

    public function test_converts_the_production_webvtt_fixture(): void
    {
        $output = $this->normalizer->normalize(
            __DIR__ . '/fixtures/production_sample.vtt',
            'production_sample.vtt'
        );

        $parsed = (new SrtParser())->parseString($output);
        $this->assertCount(19, $parsed['cues']);
        $this->assertSame('Un grupo de personas', $parsed['cues'][0]['text']);
    }

    /** Both upload formats must land on the same cues once stored. */
    public function test_both_targets_agree_on_cues_for_either_upload_format(): void
    {
        $srt = $this->writeTemp("1\n00:00:01,000 --> 00:00:04,000\nHola\n", 'srt');
        $vtt = $this->writeTemp("WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\nHola\n", 'vtt');

        $fromSrt = (new SrtParser())->parseString($this->normalizer->normalize($srt, 'a.srt'));
        $fromVtt = (new SrtParser())->parseString($this->normalizer->normalize($vtt, 'b.vtt'));

        $this->assertSame($fromSrt['cues'], $fromVtt['cues']);
    }

    private function writeTemp(string $contents, string $ext): string
    {
        $path = sys_get_temp_dir() . '/studio-normalizer-' . uniqid() . '.' . $ext;
        file_put_contents($path, $contents);
        return $path;
    }
}
