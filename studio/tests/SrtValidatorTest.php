<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SrtValidator;

class SrtValidatorTest extends TestCase
{
    private SrtValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SrtValidator();
    }

    public function test_accepts_valid_subrip_file(): void
    {
        $path = $this->writeTempFile("1\n00:00:01,000 --> 00:00:04,000\nHello\n", 'draft.srt');

        $this->validator->validate($path, 'draft.srt');

        $this->addToAssertionCount(1);
    }

    public function test_accepts_production_fixture(): void
    {
        $this->validator->validate(__DIR__ . '/ALGER_FR_Hamida_1.srt', 'ALGER_FR_Hamida_1.srt');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_non_srt_extension(): void
    {
        $path = $this->writeTempFile("1\n00:00:01,000 --> 00:00:04,000\nHello\n", 'draft.txt');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SubRip');
        $this->validator->validate($path, 'draft.txt');
    }

    public function test_rejects_webvtt_content_named_srt(): void
    {
        $path = $this->writeTempFile("WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHello\n", 'draft.srt');

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate($path, 'draft.srt');
    }

    public function test_rejects_dot_decimal_timestamps(): void
    {
        $path = $this->writeTempFile("1\n00:00:01.000 --> 00:00:04.000\nHello\n", 'draft.srt');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('marca de temps');
        $this->validator->validate($path, 'draft.srt');
    }

    public function test_rejects_missing_cue_index(): void
    {
        $path = $this->writeTempFile("00:00:01,000 --> 00:00:04,000\nHello\n", 'draft.srt');

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate($path, 'draft.srt');
    }

    public function test_rejects_empty_file(): void
    {
        $path = $this->writeTempFile('', 'draft.srt');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('buit');
        $this->validator->validate($path, 'draft.srt');
    }

    private function writeTempFile(string $contents, string $name): string
    {
        $dir = sys_get_temp_dir() . '/studio-srt-validator-' . uniqid();
        mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }
}
