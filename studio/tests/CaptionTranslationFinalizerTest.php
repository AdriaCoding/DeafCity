<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionTranslationFinalizer;
use Studio\CatalogEditor;
use Studio\JobManager;
use Studio\TranslationJobState;
use Studio\VimeoClient;

class CaptionTranslationFinalizerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/caption-translation-finalizer-' . uniqid();
        mkdir($this->dir . '/captions', 0777, true);
        mkdir($this->dir . '/jobs/111/current', 0777, true);
        file_put_contents($this->dir . '/catalog.json', json_encode([
            'videos' => [[
                'vimeo_id' => '111',
                'title' => '2026_TEST_Ada_1',
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => '2026_TEST_Ada_ES.srt']],
            ]],
        ]));
        file_put_contents($this->dir . '/jobs/111/current/draft_en.srt', "1\n00:00:00,000 --> 00:00:01,000\nHi\n");
        file_put_contents($this->dir . '/jobs/111/current/translation.json', json_encode([
            'status' => 'done',
            'master' => 'es',
            'languages' => [
                'en' => ['status' => 'done'],
                'fr' => ['status' => 'error', 'message' => 'fail'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function test_poll_path_saves_server_captions_without_calling_vimeo(): void
    {
        $catalog = new CatalogEditor($this->dir . '/catalog.json');
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');
        $vimeo->expects($this->never())->method('getTextTracks');

        $jobManager = new JobManager($this->dir . '/jobs/111');
        $state = new TranslationJobState($jobManager);
        $finalizer = new CaptionTranslationFinalizer(
            $catalog,
            $vimeo,
            $this->dir . '/captions',
            ['en' => 'English', 'fr' => 'French'],
        );

        $result = $finalizer->finalize(
            '111',
            $this->dir . '/jobs/111/current',
            $state,
            false,
        );

        $this->assertSame(['en'], $result['saved']);
        $this->assertSame(['fr'], $result['errors']);
        $this->assertFileExists($this->dir . '/captions/2026_TEST_Ada_1_EN.srt');
        $this->assertSame('saved', $state->getTopLevelStatus());
        $en = array_values(array_filter(
            $catalog->findVideoByVimeoId('111')['captions'],
            static fn (array $c): bool => ($c['lang'] ?? '') === 'en',
        ));
        $this->assertNotSame([], $en);
    }

    public function test_http_status_action_finalizes_without_vimeo_sync(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/src/Actions/CatalogAction.php');
        $this->assertMatchesRegularExpression(
            '/\$finalizer->finalize\(\s*\$vimeoId,\s*\$jobDir,\s*\$this->translationJobState\(\$vimeoId\),\s*false\s*\)/',
            $src,
        );
    }

    public function test_cli_translate_script_finalizes_with_vimeo_sync(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/translate.php');
        $this->assertStringContainsString('CaptionTranslationFinalizer', $src);
        $this->assertMatchesRegularExpression(
            '/->finalize\([^;]*true\s*\)/',
            $src,
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
