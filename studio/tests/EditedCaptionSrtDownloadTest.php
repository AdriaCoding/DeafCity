<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\EditedCaptionSrtDownload;

class EditedCaptionSrtDownloadTest extends TestCase
{
    public function test_builds_srt_from_the_posted_cues(): void
    {
        $result = (new EditedCaptionSrtDownload())->build('1211711234', 'ca', [
            ['start' => 0.0, 'end' => 2.199, 'text' => 'LINIA NO DESADA'],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('1211711234_CA.srt', $result['filename']);
        $this->assertStringContainsString('LINIA NO DESADA', $result['body']);
        $this->assertStringContainsString('00:00:00,000 --> 00:00:02,199', $result['body']);
    }

    public function test_rejects_cues_that_cannot_be_serialised(): void
    {
        $result = (new EditedCaptionSrtDownload())->build('1211711234', 'ca', [
            ['start' => 0.0, 'text' => 'falta end'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('Cos de la sol·licitud no vàlid.', $result['error']);
    }
}
