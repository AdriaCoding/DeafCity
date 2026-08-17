<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditionVideoOrder;

class CatalogEditionVideoOrderTest extends TestCase
{
    public function test_sorts_participants_alphabetically(): void
    {
        $videos = [
            ['participant' => 'Dani', 'title' => '2020_VALENCIA_Dani_1_HD'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_1_HD'],
            ['participant' => 'Carlos', 'title' => '2020_VALENCIA_Carlos_1_HD'],
        ];

        $sorted = CatalogEditionVideoOrder::sortVideos($videos);

        $this->assertSame(
            ['Aurora', 'Carlos', 'Dani'],
            array_column($sorted, 'participant'),
        );
    }

    public function test_keeps_within_participant_order_by_video_number(): void
    {
        $videos = [
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_3_HD'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_1_HD'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_2_HD'],
        ];

        $sorted = CatalogEditionVideoOrder::sortVideos($videos);

        $this->assertSame(
            ['2020_VALENCIA_Aurora_1_HD', '2020_VALENCIA_Aurora_2_HD', '2020_VALENCIA_Aurora_3_HD'],
            array_column($sorted, 'title'),
        );
    }

    public function test_sorts_by_participant_then_video_number(): void
    {
        $videos = [
            ['participant' => 'Dani', 'title' => '2020_VALENCIA_Dani_2_HD'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_2_HD'],
            ['participant' => 'Dani', 'title' => '2020_VALENCIA_Dani_1_HD'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_1_HD'],
        ];

        $sorted = CatalogEditionVideoOrder::sortVideos($videos);

        $this->assertSame(
            [
                '2020_VALENCIA_Aurora_1_HD',
                '2020_VALENCIA_Aurora_2_HD',
                '2020_VALENCIA_Dani_1_HD',
                '2020_VALENCIA_Dani_2_HD',
            ],
            array_column($sorted, 'title'),
        );
    }

    public function test_videos_without_parseable_number_sort_last_within_participant(): void
    {
        $videos = [
            ['participant' => 'Aurora', 'title' => 'Untitled clip'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_1_HD'],
        ];

        $sorted = CatalogEditionVideoOrder::sortVideos($videos);

        $this->assertSame(
            ['2020_VALENCIA_Aurora_1_HD', 'Untitled clip'],
            array_column($sorted, 'title'),
        );
    }

    public function test_participant_sort_is_case_insensitive(): void
    {
        $videos = [
            ['participant' => 'dani', 'title' => '2020_VALENCIA_dani_1_HD'],
            ['participant' => 'Aurora', 'title' => '2020_VALENCIA_Aurora_1_HD'],
        ];

        $sorted = CatalogEditionVideoOrder::sortVideos($videos);

        $this->assertSame(['Aurora', 'dani'], array_column($sorted, 'participant'));
    }
}
