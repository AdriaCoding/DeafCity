<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;

class VideosCatalogVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/preview/lib/videos_catalog.php';
    }

    public function test_playlist_excludes_invisible_videos(): void
    {
        $catalog = [
            'videos' => [
                [
                    'id' => 'lse_111',
                    'vimeo_id' => '111',
                    'sign_language' => 'lse',
                    'captions' => [['file' => '111.es.vtt', 'label' => 'Spanish']],
                ],
                [
                    'id' => 'lse_222',
                    'vimeo_id' => '222',
                    'sign_language' => 'lse',
                    'invisible' => true,
                    'captions' => [['file' => '222.es.vtt', 'label' => 'Spanish']],
                ],
            ],
        ];

        $playlist = vpc_vimeo_playlist_all_from_catalog($catalog);

        $this->assertCount(1, $playlist);
        $this->assertSame('111', $playlist[0]['video_id']);
    }

    public function test_sign_language_options_exclude_languages_with_only_invisible_videos(): void
    {
        $catalog = [
            'videos' => [
                [
                    'id' => 'lse_111',
                    'vimeo_id' => '111',
                    'sign_language' => 'lse',
                    'captions' => [],
                ],
                [
                    'id' => 'lsm_222',
                    'vimeo_id' => '222',
                    'sign_language' => 'lsm',
                    'invisible' => true,
                    'captions' => [],
                ],
            ],
        ];

        $opts = vpc_sign_language_options_from_catalog($catalog, '/nonexistent/studio-config.json');

        $this->assertCount(1, $opts);
        $this->assertSame('lse', $opts[0]['value']);
    }

    public function test_playlist_from_catalog_skips_invisible_ids(): void
    {
        $catalog = [
            'videos' => [
                [
                    'id' => 'lse_111',
                    'vimeo_id' => '111',
                    'sign_language' => 'lse',
                    'captions' => [['file' => '111.es.vtt', 'label' => 'Spanish']],
                ],
                [
                    'id' => 'lse_222',
                    'vimeo_id' => '222',
                    'sign_language' => 'lse',
                    'invisible' => true,
                    'captions' => [['file' => '222.es.vtt', 'label' => 'Spanish']],
                ],
            ],
        ];

        $playlist = vpc_vimeo_playlist_from_catalog($catalog, ['lse_111', 'lse_222']);

        $this->assertCount(1, $playlist);
        $this->assertSame('111', $playlist[0]['video_id']);
    }
}
