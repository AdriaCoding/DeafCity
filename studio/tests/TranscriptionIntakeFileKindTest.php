<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\TranscriptionIntakeFileKind;

class TranscriptionIntakeFileKindTest extends TestCase
{
    public function test_recognises_vtt_as_subtitle(): void
    {
        $this->assertSame('subtitle', TranscriptionIntakeFileKind::fromFilename('draft.vtt'));
    }

    public function test_recognises_srt_as_subtitle(): void
    {
        $this->assertSame('subtitle', TranscriptionIntakeFileKind::fromFilename('draft.srt'));
    }

    public function test_recognises_mp3_as_audio(): void
    {
        $this->assertSame('audio', TranscriptionIntakeFileKind::fromFilename('talk.mp3'));
    }

    public function test_returns_null_for_unknown_extension(): void
    {
        $this->assertNull(TranscriptionIntakeFileKind::fromFilename('notes.txt'));
    }
}
