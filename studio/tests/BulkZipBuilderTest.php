<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BulkZipBuilder;

class BulkZipBuilderTest extends TestCase
{
    public function test_non_english_source_produces_source_and_en_srt(): void
    {
        $builder = new BulkZipBuilder();
        $entries = [
            [
                'originalFilename' => 'talk_ca',
                'language'         => 'ca',
                'srcVttPath'       => $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHola\n"),
                'enVttPath'        => $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHello\n"),
            ],
        ];

        $zipBinary = $builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'bulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(2, $zip->numFiles);
        $this->assertStringContainsString('Hola', (string) $zip->getFromName('talk_ca_CA.srt'));
        $this->assertStringContainsString('Hello', (string) $zip->getFromName('talk_ca_EN.srt'));
        $zip->close();
        unlink($tmp);
    }

    public function test_english_source_produces_only_en_srt(): void
    {
        $builder = new BulkZipBuilder();
        $vtt = $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHello\n");
        $entries = [
            [
                'originalFilename' => 'talk_en',
                'language'         => 'en',
                'srcVttPath'       => $vtt,
                'enVttPath'        => $vtt,
            ],
        ];

        $zipBinary = $builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'bulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(1, $zip->numFiles);
        $this->assertNotFalse($zip->getFromName('talk_en_EN.srt'));
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
