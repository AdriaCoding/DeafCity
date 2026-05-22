<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\WebVttValidator;

class WebVttValidatorTest extends TestCase
{
    private WebVttValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WebVttValidator();
    }

    public function test_accepts_valid_webvtt_file(): void
    {
        $path = $this->writeTempFile("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHello\n", 'draft.vtt');

        $this->validator->validate($path, 'draft.vtt');

        $this->addToAssertionCount(1);
    }

    public function test_accepts_webvtt_header_with_metadata(): void
    {
        $path = $this->writeTempFile("WEBVTT - This file has metadata\n\n00:00:00.000 --> 00:00:01.000\nHello\n", 'draft.vtt');

        $this->validator->validate($path, 'draft.vtt');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_non_vtt_extension(): void
    {
        $path = $this->writeTempFile("WEBVTT\n", 'draft.txt');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WebVTT');
        $this->validator->validate($path, 'draft.txt');
    }

    public function test_rejects_file_without_webvtt_header(): void
    {
        $path = $this->writeTempFile("NOT WEBVTT\n", 'draft.vtt');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WEBVTT');
        $this->validator->validate($path, 'draft.vtt');
    }

    private function writeTempFile(string $contents, string $name): string
    {
        $dir = sys_get_temp_dir() . '/studio-vtt-' . uniqid();
        mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }
}
