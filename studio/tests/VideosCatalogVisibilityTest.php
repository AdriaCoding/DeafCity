<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;

class VideosCatalogVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/lib/videos_catalog.php';
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

    public function test_sign_language_options_are_sorted_alphabetically_by_second_word(): void
    {
        $catalog = [
            'videos' => [
                ['id' => 'libras_111', 'vimeo_id' => '111', 'sign_language' => 'libras', 'captions' => []],
                ['id' => 'lsa_222', 'vimeo_id' => '222', 'sign_language' => 'lsa', 'captions' => []],
            ],
        ];

        $configPath = sys_get_temp_dir() . '/studio-config-sl-sort-' . uniqid() . '.json';
        file_put_contents($configPath, json_encode([
            'sign_languages' => [
                // Full-label A–Z would be LIBRAS then LSA; second-word A–Z is Algerian then Brazilian.
                ['id' => 'libras', 'label' => 'LIBRAS Brazilian Sign Language'],
                ['id' => 'lsa', 'label' => 'LSA Algerian Sign Language'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        try {
            $opts = vpc_sign_language_options_from_catalog($catalog, $configPath);

            $this->assertSame(['lsa', 'libras'], array_column($opts, 'value'));
        } finally {
            if (is_file($configPath)) {
                unlink($configPath);
            }
        }
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
}
