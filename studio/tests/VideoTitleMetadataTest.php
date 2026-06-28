<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\VideoTitleMetadata;

class VideoTitleMetadataTest extends TestCase
{
    public function test_extracts_participant_from_year_city_hd_title(): void
    {
        $this->assertSame('Serena', VideoTitleMetadata::extractParticipant('2026_ROMA_Serena_3_HD'));
    }

    public function test_extracts_participant_from_sao_paulo_hd_title(): void
    {
        $this->assertSame('Edinho', VideoTitleMetadata::extractParticipant('SaoPAULO_Edinho_1_HD'));
    }

    public function test_extracts_participant_with_leading_zero_and_stray_space(): void
    {
        $this->assertSame('Carlota', VideoTitleMetadata::extractParticipant('2026_BARCELONA_Carlota_01_HD'));
        $this->assertSame('Hassen', VideoTitleMetadata::extractParticipant('2026_ALGER_Hassen _5_HD'));
    }

    public function test_extracts_participant_from_legacy_bulk_title(): void
    {
        $this->assertSame('Edinho', VideoTitleMetadata::extractParticipant('LIBRAS_São Paulo_Edinho_1 #HEARING CROWD'));
    }

    public function test_extracts_participant_from_studio_title(): void
    {
        $this->assertSame('Hamida', VideoTitleMetadata::extractParticipant('#SHEEP by Hamida'));
    }

    public function test_resolves_edition_and_sign_language_for_rome(): void
    {
        $resolved = VideoTitleMetadata::resolveEditionAndSignLanguage('2026_ROMA_Serena_3_HD');

        $this->assertSame('2026-rome', $resolved['edition']);
        $this->assertSame('lis', $resolved['sign_language']);
    }

    public function test_resolves_edition_and_sign_language_for_sao_paulo_without_year(): void
    {
        $resolved = VideoTitleMetadata::resolveEditionAndSignLanguage('SaoPAULO_Fabio_1_HD');

        $this->assertSame('2023-sao-paulo', $resolved['edition']);
        $this->assertSame('libras', $resolved['sign_language']);
    }

    public function test_resolves_bilbao_with_accented_participant_title(): void
    {
        $resolved = VideoTitleMetadata::resolveEditionAndSignLanguage('2023_BILBAO_Iñaki_4_HD');

        $this->assertSame('2023-bilbao', $resolved['edition']);
        $this->assertSame('lse', $resolved['sign_language']);
        $this->assertSame('Iñaki', VideoTitleMetadata::extractParticipant('2023_BILBAO_Iñaki_4_HD'));
    }
}
