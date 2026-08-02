<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionFilename;

class CaptionFilenameTest extends TestCase
{
    private CaptionFilename $captionFilename;

    protected function setUp(): void
    {
        $this->captionFilename = new CaptionFilename();
    }

    public function test_strips_resolution_suffix_and_appends_uppercase_lang(): void
    {
        $this->assertSame(
            '2020_VALENCIA_Aurora_1_EN.vtt',
            $this->captionFilename->forVideo('2020_VALENCIA_Aurora_1_4k', 'en'),
        );
    }

    public function test_strips_suffix_regardless_of_its_literal_value(): void
    {
        $this->assertSame(
            '2021_MEXICO_Indy_1_CA.vtt',
            $this->captionFilename->forVideo('2021_MEXICO_Indy_1_4K', 'ca'),
        );
        $this->assertSame(
            '2026_MARSEILLE_Hugo_3_ES.vtt',
            $this->captionFilename->forVideo('2026_MARSEILLE_Hugo_3_HD', 'es'),
        );
    }

    public function test_uppercases_lang_code_regardless_of_input_case(): void
    {
        $this->assertSame(
            '2020_VALENCIA_Aurora_1_EN.vtt',
            $this->captionFilename->forVideo('2020_VALENCIA_Aurora_1_4k', 'EN'),
        );
    }

    public function test_title_without_a_numeric_sequence_segment_is_kept_as_is(): void
    {
        $this->assertSame(
            'Test_ES.vtt',
            $this->captionFilename->forVideo('Test', 'es'),
        );
    }

    public function test_preserves_a_stray_space_baked_into_the_stored_title(): void
    {
        $this->assertSame(
            '2026_ALGER_Hassen _1_EN.vtt',
            $this->captionFilename->forVideo('2026_ALGER_Hassen _1_4K', 'en'),
        );
    }
}
