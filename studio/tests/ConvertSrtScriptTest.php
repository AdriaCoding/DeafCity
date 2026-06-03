<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;

class ConvertSrtScriptTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/studio-convert-srt-' . uniqid();
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);
    }

    public function test_convert_srt_script_writes_draft_vtt_and_done_status(): void
    {
        $srtPath = $this->workDir . '/intake.srt';
        $vttPath = $this->workDir . '/draft.vtt';
        $statusPath = $this->workDir . '/conversion.json';
        copy(__DIR__ . '/ALGER_FR_Hamida_1.srt', $srtPath);

        $cmd = sprintf(
            'php8.4 %s --srt_path %s --vtt_output %s --status_file %s',
            escapeshellarg(__DIR__ . '/../scripts/convert_srt.php'),
            escapeshellarg($srtPath),
            escapeshellarg($vttPath),
            escapeshellarg($statusPath),
        );
        exec($cmd, $output, $code);

        $this->assertSame(0, $code);
        $this->assertFileExists($vttPath);
        $this->assertStringStartsWith("WEBVTT\n", (string) file_get_contents($vttPath));
        $status = json_decode((string) file_get_contents($statusPath), true);
        $this->assertSame('done', $status['status'] ?? '');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
