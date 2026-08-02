<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\DataDirZipBuilder;

class DataDirZipBuilderTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/data-zip-' . uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->rootDir);
    }

    public function test_zip_contains_a_top_level_file(): void
    {
        file_put_contents($this->rootDir . '/catalog.json', '{"videos":[]}');

        $binary = (new DataDirZipBuilder())->build($this->rootDir);

        $zipPath = tempnam(sys_get_temp_dir(), 'readzip');
        file_put_contents($zipPath, $binary);
        $zip = new \ZipArchive();
        $zip->open($zipPath);

        $this->assertSame('{"videos":[]}', $zip->getFromName('catalog.json'));

        $zip->close();
        unlink($zipPath);
    }

    public function test_zip_preserves_nested_directory_structure(): void
    {
        mkdir($this->rootDir . '/captions', 0777, true);
        file_put_contents($this->rootDir . '/captions/111_EN.vtt', "WEBVTT\n\nHola\n");

        $binary = (new DataDirZipBuilder())->build($this->rootDir);

        $zipPath = tempnam(sys_get_temp_dir(), 'readzip');
        file_put_contents($zipPath, $binary);
        $zip = new \ZipArchive();
        $zip->open($zipPath);

        $this->assertSame("WEBVTT\n\nHola\n", $zip->getFromName('captions/111_EN.vtt'));

        $zip->close();
        unlink($zipPath);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
