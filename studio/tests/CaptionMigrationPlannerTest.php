<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionMigrationPlanner;

class CaptionMigrationPlannerTest extends TestCase
{
    private CaptionMigrationPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new CaptionMigrationPlanner();
    }

    public function test_plans_a_rename_for_a_caption_using_the_old_convention(): void
    {
        $videos = [
            [
                'vimeo_id' => '1211691420',
                'title' => '2026_MARSEILLE_Hugo_3_4K',
                'captions' => [
                    ['lang' => 'fr', 'label' => 'French', 'file' => '1211691420.fr.vtt'],
                ],
            ],
        ];

        $this->assertSame([
            [
                'vimeoId' => '1211691420',
                'lang' => 'fr',
                'oldFile' => '1211691420.fr.vtt',
                'newFile' => '2026_MARSEILLE_Hugo_3_FR.srt',
            ],
        ], $this->planner->plan($videos));
    }

    public function test_skips_a_caption_file_already_using_the_new_convention(): void
    {
        $videos = [
            [
                'vimeo_id' => '1211691420',
                'title' => '2026_MARSEILLE_Hugo_3_4K',
                'captions' => [
                    ['lang' => 'fr', 'label' => 'French', 'file' => '2026_MARSEILLE_Hugo_3_FR.srt'],
                ],
            ],
        ];

        $this->assertSame([], $this->planner->plan($videos));
    }

    public function test_ignores_videos_with_no_captions(): void
    {
        $videos = [
            ['vimeo_id' => '1', 'title' => 'Some_Title_1_4k', 'captions' => []],
        ];

        $this->assertSame([], $this->planner->plan($videos));
    }

    public function test_plans_renames_across_multiple_videos_and_languages(): void
    {
        $videos = [
            [
                'vimeo_id' => '1211691420',
                'title' => '2026_MARSEILLE_Hugo_3_4K',
                'captions' => [
                    ['lang' => 'fr', 'label' => 'French', 'file' => '1211691420.fr.vtt'],
                    ['lang' => 'en', 'label' => 'English', 'file' => '1211691420.en.vtt'],
                ],
            ],
            [
                'vimeo_id' => '2',
                'title' => '2020_VALENCIA_Aurora_1_4k',
                'captions' => [
                    ['lang' => 'ca', 'label' => 'Catalan', 'file' => '2.ca.vtt'],
                ],
            ],
        ];

        $this->assertSame([
            ['vimeoId' => '1211691420', 'lang' => 'fr', 'oldFile' => '1211691420.fr.vtt', 'newFile' => '2026_MARSEILLE_Hugo_3_FR.srt'],
            ['vimeoId' => '1211691420', 'lang' => 'en', 'oldFile' => '1211691420.en.vtt', 'newFile' => '2026_MARSEILLE_Hugo_3_EN.srt'],
            ['vimeoId' => '2', 'lang' => 'ca', 'oldFile' => '2.ca.vtt', 'newFile' => '2020_VALENCIA_Aurora_1_CA.srt'],
        ], $this->planner->plan($videos));
    }

    public function test_apply_renames_rewrites_caption_file_entries_in_the_catalog(): void
    {
        $videos = [
            [
                'vimeo_id' => '1211691420',
                'title' => '2026_MARSEILLE_Hugo_3_4K',
                'captions' => [
                    ['lang' => 'fr', 'label' => 'French', 'file' => '1211691420.fr.vtt'],
                    ['lang' => 'en', 'label' => 'English', 'file' => '1211691420.en.vtt'],
                ],
            ],
        ];
        $renames = $this->planner->plan($videos);

        $updated = $this->planner->applyRenames($videos, $renames);

        $this->assertSame('2026_MARSEILLE_Hugo_3_FR.srt', $updated[0]['captions'][0]['file']);
        $this->assertSame('2026_MARSEILLE_Hugo_3_EN.srt', $updated[0]['captions'][1]['file']);
        // Untouched fields survive.
        $this->assertSame('French', $updated[0]['captions'][0]['label']);
    }

    public function test_apply_renames_leaves_videos_without_matching_renames_untouched(): void
    {
        $videos = [
            [
                'vimeo_id' => '2',
                'title' => 'Other',
                'captions' => [
                    ['lang' => 'es', 'label' => 'Spanish', 'file' => 'Other_ES.vtt'],
                ],
            ],
        ];

        $updated = $this->planner->applyRenames($videos, []);

        $this->assertSame($videos, $updated);
    }
}
