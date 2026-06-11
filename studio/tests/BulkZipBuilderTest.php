<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BulkZipBuilder;

class BulkZipBuilderTest extends TestCase
{
    public function test_builds_zip_with_en_vtt_names_and_content(): void
    {
        $builder = new BulkZipBuilder();
        $entries = [
            ['originalFilename' => 'talk_ca', 'vttPath' => $this->writeVtt('WEBVTT ca')],
            ['originalFilename' => 'session_es', 'vttPath' => $this->writeVtt('WEBVTT es')],
        ];

        $zipBinary = $builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'bulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('WEBVTT ca', $zip->getFromName('talk_ca_EN.vtt'));
        $this->assertSame('WEBVTT es', $zip->getFromName('session_es_EN.vtt'));
        $zip->close();
        unlink($tmp);
    }

    private function writeVtt(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($path, $content);
        return $path;
    }
}
