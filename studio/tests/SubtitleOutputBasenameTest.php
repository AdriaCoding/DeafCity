<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioConfig;
use Studio\SubtitleOutputBasename;

class SubtitleOutputBasenameTest extends TestCase
{
    private SubtitleOutputBasename $basename;

    protected function setUp(): void
    {
        $this->basename = new SubtitleOutputBasename(
            new StudioConfig(__DIR__ . '/fixtures/studio-config.json'),
        );
    }

    public function test_strips_source_language_suffix_from_audio_stem(): void
    {
        $this->assertSame('BCN_Raquel_3', $this->basename->baseStem('BCN_Raquel_3_CA', 'ca'));
        $this->assertSame('Roma_Serena_3', $this->basename->baseStem('Roma_Serena_3_IT', 'it'));
    }

    public function test_srt_filename_replaces_audio_suffix_with_subtitle_language(): void
    {
        $this->assertSame('BCN_Raquel_3_CA.srt', $this->basename->srtFilename('BCN_Raquel_3_CA', 'ca', 'ca'));
        $this->assertSame('BCN_Raquel_3_EN.srt', $this->basename->srtFilename('BCN_Raquel_3_CA', 'ca', 'en'));
        $this->assertSame('Roma_Serena_3_IT.srt', $this->basename->srtFilename('Roma_Serena_3_IT', 'it', 'it'));
        $this->assertSame('Roma_Serena_3_EN.srt', $this->basename->srtFilename('Roma_Serena_3_IT', 'it', 'en'));
    }

    public function test_lowercase_filename_suffix_is_stripped(): void
    {
        $this->assertSame('talk', $this->basename->baseStem('talk_ca', 'ca'));
        $this->assertSame('talk_CA.srt', $this->basename->srtFilename('talk_ca', 'ca', 'ca'));
        $this->assertSame('talk_EN.srt', $this->basename->srtFilename('talk_ca', 'ca', 'en'));
    }

    public function test_vimeo_code_suffix_is_stripped_for_arq(): void
    {
        $this->assertSame('session', $this->basename->baseStem('session_ar', 'arq'));
        $this->assertSame('session_AR.srt', $this->basename->srtFilename('session_ar', 'arq', 'arq'));
    }

    public function test_transcription_download_uses_source_language_when_lang_not_specified(): void
    {
        $this->assertSame(
            'BCN_Raquel_3_CA.vtt',
            $this->basename->transcriptionDownloadFilename('BCN_Raquel_3_CA', 'ca', '', 'vtt'),
        );
    }

    public function test_transcription_download_uses_requested_language_when_specified(): void
    {
        $this->assertSame(
            'BCN_Raquel_3_EN.srt',
            $this->basename->transcriptionDownloadFilename('BCN_Raquel_3_CA', 'ca', 'en', 'srt'),
        );
    }
}
