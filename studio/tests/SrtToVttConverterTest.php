<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SrtToVttConverter;
use Studio\VttParser;

class SrtToVttConverterTest extends TestCase
{
    public function test_convert_produces_valid_webvtt(): void
    {
        $path = sys_get_temp_dir() . '/studio-srt-conv-' . uniqid() . '.srt';
        file_put_contents(
            $path,
            "1\n00:00:01,000 --> 00:00:04,000\nHello world\n"
        );

        $output = (new SrtToVttConverter())->convert($path);

        $this->assertStringStartsWith("WEBVTT\n", $output);
        $parsed = (new VttParser())->parseString($output);
        $this->assertCount(1, $parsed['cues']);
        $this->assertSame('Hello world', $parsed['cues'][0]['text']);
        $this->assertSame(1.0, $parsed['cues'][0]['start']);
        $this->assertSame(4.0, $parsed['cues'][0]['end']);
        $this->assertSame('1', $parsed['cues'][0]['id']);
    }

    public function test_convert_alger_fr_hamida_fixture(): void
    {
        $path = __DIR__ . '/ALGER_FR_Hamida_1.srt';

        $output = (new SrtToVttConverter())->convert($path);

        $parsed = (new VttParser())->parseString($output);
        $this->assertCount(51, $parsed['cues']);
        $this->assertSame('Un papa avec son fils.', $parsed['cues'][0]['text']);
        $this->assertSame(3.66, $parsed['cues'][0]['start']);
        $this->assertSame(5.74, $parsed['cues'][0]['end']);
        $this->assertStringContainsString(
            "1\n00:00:03.660 --> 00:00:05.740\nUn papa avec son fils.",
            $output
        );
    }
}
