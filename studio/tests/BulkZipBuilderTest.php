<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BulkZipBuilder;

class BulkZipBuilderTest extends TestCase
{
    private BulkZipBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new BulkZipBuilder();
    }

    public function test_non_english_source_produces_only_en_srt(): void
    {
        $entries = [
            [
                'originalFilename' => 'BCN_Raquel_3_CA',
                'language'         => 'ca',
                'srcSrtPath'       => $this->writeSrt("1\n00:00:01,000 --> 00:00:03,000\nHola\n"),
                'enSrtPath'        => $this->writeSrt("1\n00:00:01,000 --> 00:00:03,000\nHello\n"),
            ],
        ];

        $zipBinary = $this->builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'bulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(1, $zip->numFiles);
        $this->assertFalse($zip->getFromName('BCN_Raquel_3_CA.srt'));
        $this->assertStringContainsString('Hello', (string) $zip->getFromName('BCN_Raquel_3_EN.srt'));
        $zip->close();
        unlink($tmp);
    }

    public function test_italian_source_uses_expected_en_name(): void
    {
        $entries = [
            [
                'originalFilename' => 'Roma_Serena_3_IT',
                'language'         => 'it',
                'srcSrtPath'       => $this->writeSrt("1\n00:00:01,000 --> 00:00:03,000\nCiao\n"),
                'enSrtPath'        => $this->writeSrt("1\n00:00:01,000 --> 00:00:03,000\nHello\n"),
            ],
        ];

        $zipBinary = $this->builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'bulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(1, $zip->numFiles);
        $this->assertFalse($zip->getFromName('Roma_Serena_3_IT.srt'));
        $this->assertNotFalse($zip->getFromName('Roma_Serena_3_EN.srt'));
        $zip->close();
        unlink($tmp);
    }

    public function test_english_source_produces_only_en_srt(): void
    {
        $srt = $this->writeSrt("1\n00:00:01,000 --> 00:00:03,000\nHello\n");
        $entries = [
            [
                'originalFilename' => 'talk_en',
                'language'         => 'en',
                'srcSrtPath'       => $srt,
                'enSrtPath'        => $srt,
            ],
        ];

        $zipBinary = $this->builder->build($entries);

        $tmp = tempnam(sys_get_temp_dir(), 'bulkzip');
        file_put_contents($tmp, $zipBinary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $this->assertSame(1, $zip->numFiles);
        $this->assertNotFalse($zip->getFromName('talk_EN.srt'));
        $zip->close();
        unlink($tmp);
    }

    private function writeSrt(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($path, $content);
        return $path;
    }
}
