<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\IntakeSourceDetector;

class IntakeSourceDetectorTest extends TestCase
{
    private IntakeSourceDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new IntakeSourceDetector();
    }

    public function test_detects_vtt_by_extension(): void
    {
        $path = $this->writeTemp('Cue text', 'subtitles.vtt');

        $this->assertSame('upload', $this->detector->detect($path, 'subtitles.vtt'));
    }

    public function test_detects_vtt_by_content_even_without_extension(): void
    {
        $path = $this->writeTemp("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHola\n", 'captions.txt');

        $this->assertSame('upload', $this->detector->detect($path, 'captions.txt'));
    }

    public function test_detects_mp3_as_audio(): void
    {
        $path = $this->writeTemp("\xFF\xFB\x90\x00audio", 'interpreter.mp3');

        $this->assertSame('generate', $this->detector->detect($path, 'interpreter.mp3'));
    }

    public function test_detects_wav_as_audio(): void
    {
        $path = $this->writeTemp("RIFF\x00\x00\x00\x00WAVEfmt ", 'interpreter.wav');

        $this->assertSame('generate', $this->detector->detect($path, 'interpreter.wav'));
    }

    public function test_rejects_unrecognized_file(): void
    {
        $path = $this->writeTemp('plain text document', 'notes.pdf');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WebVTT');
        $this->detector->detect($path, 'notes.pdf');
    }

    private function writeTemp(string $contents, string $name): string
    {
        $dir = sys_get_temp_dir() . '/studio-intake-' . uniqid();
        mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }
}
