<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\ShortenBulkZipBuilder;

class ShortenBulkZipBuilderTest extends TestCase
{
    private ShortenBulkZipBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ShortenBulkZipBuilder();
    }

    public function test_produces_one_srt_per_entry_in_source_language(): void
    {
        $entries = [
            [
                'originalFilename' => 'BCN_Raquel_3_CA',
                'language'         => 'ca',
                'srcVttPath'       => $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHola escurçada\n"),
            ],
        ];

        $zipBinary = $this->builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'shortenbulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringContainsString('Hola escurçada', (string) $zip->getFromName('BCN_Raquel_3_CA.srt'));
        $zip->close();
        unlink($tmp);
    }

    public function test_english_source_keeps_en_suffix(): void
    {
        $entries = [
            [
                'originalFilename' => 'talk_en',
                'language'         => 'en',
                'srcVttPath'       => $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHello\n"),
            ],
        ];

        $zipBinary = $this->builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'shortenbulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(1, $zip->numFiles);
        $this->assertNotFalse($zip->getFromName('talk_EN.srt'));
        $zip->close();
        unlink($tmp);
    }

    public function test_multiple_entries_produce_multiple_files(): void
    {
        $entries = [
            [
                'originalFilename' => 'talk_ca',
                'language'         => 'ca',
                'srcVttPath'       => $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHola\n"),
            ],
            [
                'originalFilename' => 'xerrada_es',
                'language'         => 'es',
                'srcVttPath'       => $this->writeVtt("WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHola\n"),
            ],
        ];

        $zipBinary = $this->builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'shortenbulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(2, $zip->numFiles);
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
