<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\AudioPreprocessor;

class AudioPreprocessorTest extends TestCase
{
    private string $jobDir;

    protected function setUp(): void
    {
        $this->jobDir = sys_get_temp_dir() . '/ap_test_' . uniqid();
        mkdir($this->jobDir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->jobDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        if (is_dir($this->jobDir)) {
            rmdir($this->jobDir);
        }
    }

    public function test_builds_ffmpeg_command_for_16khz_mono_flac_in_job_dir(): void
    {
        $captured = null;
        $exec = function (string $cmd, array &$out, int &$code) use (&$captured) {
            $captured = $cmd;
            $code = 0;
        };

        $pre = new AudioPreprocessor($exec);
        $flac = $pre->toGroqUpload($this->jobDir . '/interpreter_audio.wav', $this->jobDir);

        $this->assertNotNull($captured);
        $this->assertStringContainsString('ffmpeg', $captured);
        $this->assertStringContainsString('-ar 16000', $captured);
        $this->assertStringContainsString('-ac 1', $captured);
        $this->assertStringContainsString(escapeshellarg($this->jobDir . '/interpreter_audio.wav'), $captured);
        // Output is a .flac inside the Job folder.
        $this->assertSame($this->jobDir, dirname($flac));
        $this->assertSame('flac', pathinfo($flac, PATHINFO_EXTENSION));
        $this->assertStringContainsString(escapeshellarg($flac), $captured);
    }

    public function test_nonzero_exit_raises(): void
    {
        $exec = function (string $cmd, array &$out, int &$code) {
            $out = ['ffmpeg: invalid data'];
            $code = 1;
        };

        $pre = new AudioPreprocessor($exec);

        $this->expectException(\RuntimeException::class);
        $pre->toGroqUpload($this->jobDir . '/bad.wav', $this->jobDir);
    }
}
