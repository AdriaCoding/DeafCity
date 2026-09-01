<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CaptionUploadHandler;
use Studio\CatalogEditor;
use Studio\StudioConfig;
use Studio\VimeoClient;

class CaptionUploadHandlerTest extends TestCase
{
    private string $baseDir;
    private string $captionsDir;
    private string $catalogFile;
    private StudioConfig $config;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/caption-upload-' . uniqid();
        mkdir($this->baseDir, 0777, true);
        $this->captionsDir = $this->baseDir . '/captions';
        mkdir($this->captionsDir, 0777, true);
        $this->catalogFile = $this->baseDir . '/catalog.json';
        $this->config = new StudioConfig(__DIR__ . '/fixtures/studio-config.json');

        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Test',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [],
            ],
        ]]));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->baseDir);
    }

    public function test_saves_vtt_file_and_updates_catalog(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->once())->method('uploadAndActivateTextTrack')
            ->with('111', $this->captionsDir . '/Test_ES.srt', 'es', 'Spanish');

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['vimeoWarnings']);
        $this->assertFileExists($this->captionsDir . '/Test_ES.srt');

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111');
        $this->assertCount(1, $entry['captions']);
        $this->assertSame('es', $entry['captions'][0]['lang']);
        $this->assertSame('Test_ES.srt', $entry['captions'][0]['file']);

        $this->assertCount(1, $result['captions']);
        $this->assertSame('es', $result['captions'][0]['lang']);
        $this->assertSame('Test_ES.srt', $result['captions'][0]['file']);
    }

    public function test_rejects_invalid_language(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        $result = $this->makeHandler($this->createMock(VimeoClient::class))->handle('111', [[
            'lang' => 'zz',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('llengua', $result['error'] ?? '');
    }

    public function test_replaces_existing_caption_for_same_language(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Test',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => 'Test_ES.srt']],
            ],
        ]]));
        file_put_contents($this->captionsDir . '/Test_ES.srt', "1\n00:00:00,000 --> 00:00:02,000\nold\n");

        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nNou\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $vtt,
            'originalName' => 'new.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Nou', file_get_contents($this->captionsDir . '/Test_ES.srt'));

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111');
        $this->assertCount(1, $entry['captions']);
    }

    public function test_upload_invalidates_the_translation_job_state(): void
    {
        $jobDir = $this->baseDir . '/caption-translation/111/current';
        mkdir($jobDir, 0777, true);
        file_put_contents($jobDir . '/translation.json', json_encode([
            'status' => 'saved',
            'master' => 'es',
            'savedLangs' => ['en'],
        ]));

        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertDirectoryDoesNotExist($jobDir);
    }

    public function test_vimeo_failure_still_saves_file_and_catalog(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->method('uploadAndActivateTextTrack')->willThrowException(new \RuntimeException('upload failed'));

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'en',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['vimeoWarnings']);
        $this->assertFileExists($this->captionsDir . '/Test_EN.srt');
    }

    public function test_upload_sends_caption_lang_directly_to_vimeo(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nSalut\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->method('getTextTracks')->willReturn([]);
        $vimeo->expects($this->once())->method('uploadAndActivateTextTrack')
            ->with('111', $this->captionsDir . '/Test_AR.srt', 'ar', 'Arabic');

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'ar',
            'tmpPath' => $vtt,
            'originalName' => 'subtitles.vtt',
        ]]);

        $this->assertTrue($result['ok']);
    }

    public function test_empty_uploads_returns_ok_without_changes(): void
    {
        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');

        $result = $this->makeHandler($vimeo)->handle('111', []);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['vimeoWarnings']);
    }

    public function test_syncToVimeo_false_overwrites_file_without_vimeo_calls(): void
    {
        file_put_contents($this->catalogFile, json_encode(['videos' => [
            [
                'id' => 'lse_111',
                'vimeo_id' => '111',
                'title' => 'Test',
                'sign_language' => 'lse',
                'edition' => '2020-valencia',
                'tags' => [],
                'captions' => [['lang' => 'es', 'label' => 'Spanish', 'file' => 'Test_ES.srt']],
            ],
        ]]));
        file_put_contents($this->captionsDir . '/Test_ES.srt', "1\n00:00:00,000 --> 00:00:02,000\nold\n");

        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nNou\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('getTextTracks');
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $vtt,
            'originalName' => 'new.vtt',
        ]], syncToVimeo: false);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['vimeoWarnings']);
        $this->assertStringContainsString('Nou', file_get_contents($this->captionsDir . '/Test_ES.srt'));
    }

    public function test_mid_batch_failure_leaves_no_orphan_caption_file_on_disk(): void
    {
        // First upload is fine, second names a language the config does not
        // know. The batch is rejected as a whole — so the first file must not
        // survive on disk, referenced by nothing in the catalog.
        $good = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($good, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");
        $alsoGood = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($alsoGood, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHello\n");

        $result = $this->makeHandler($this->createMock(VimeoClient::class))->handle('111', [
            ['lang' => 'es', 'tmpPath' => $good, 'originalName' => 'a.vtt'],
            ['lang' => 'not-a-language', 'tmpPath' => $alsoGood, 'originalName' => 'b.vtt'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertFileDoesNotExist($this->captionsDir . '/Test_ES.srt');

        $entry = (new CatalogEditor($this->catalogFile))->findVideoByVimeoId('111');
        $this->assertSame([], $entry['captions']);
    }

    public function test_mid_batch_failure_restores_a_caption_it_had_already_overwritten(): void
    {
        // The batch replaces an existing Spanish caption and then fails on the
        // second file. Rolling back by deleting would destroy a caption the
        // Producer never asked to remove, so the previous bytes must come back.
        $existing = $this->captionsDir . '/Test_ES.srt';
        file_put_contents($existing, "1\n00:00:00,000 --> 00:00:02,000\nOriginal\n");
        $original = file_get_contents($existing);

        $replacement = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($replacement, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nReemplaç\n");
        $second = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($second, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHello\n");

        $result = $this->makeHandler($this->createMock(VimeoClient::class))->handle('111', [
            ['lang' => 'es', 'tmpPath' => $replacement, 'originalName' => 'a.vtt'],
            ['lang' => 'not-a-language', 'tmpPath' => $second, 'originalName' => 'b.vtt'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertFileExists($existing);
        $this->assertSame($original, file_get_contents($existing));
    }

    public function test_publish_failure_also_rolls_back_written_caption_files(): void
    {
        $vtt = tempnam(sys_get_temp_dir(), 'vtt');
        file_put_contents($vtt, "WEBVTT\n\n00:00:00.000 --> 00:00:02.000\nHola\n");

        // A video id that is not in the catalog makes publish() throw after
        // the caption file has already been written.
        $result = $this->makeHandler($this->createMock(VimeoClient::class))->handle('999', [
            ['lang' => 'es', 'tmpPath' => $vtt, 'originalName' => 'a.vtt'],
        ]);

        $this->assertFalse($result['ok']);
        $this->assertCount(
            0,
            glob($this->captionsDir . '/*.srt'),
            'a failed publish must not leave caption files behind'
        );
    }

    private function makeHandler(VimeoClient $vimeo): CaptionUploadHandler
    {
        return new CaptionUploadHandler(
            $vimeo,
            new CatalogEditor($this->catalogFile),
            $this->config,
            $this->captionsDir,
            $this->baseDir . '/caption-translation',
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

    /**
     * A malformed upload must not take the existing caption with it. The
     * pre-normalizer order unlinked the destination first and only then tried
     * to convert, so a bad .srt destroyed the caption it was replacing.
     */
    public function test_failed_upload_leaves_the_existing_caption_intact(): void
    {
        $existing = $this->captionsDir . '/Test_ES.srt';
        $good = "1\n00:00:00,000 --> 00:00:02,000\nBona\n";
        file_put_contents($existing, $good);

        $bad = tempnam(sys_get_temp_dir(), 'srt');
        file_put_contents($bad, "1\n00:00:01,000 --> 00:00:04,000\n");

        $vimeo = $this->createMock(VimeoClient::class);
        $vimeo->expects($this->never())->method('uploadAndActivateTextTrack');

        $result = $this->makeHandler($vimeo)->handle('111', [[
            'lang' => 'es',
            'tmpPath' => $bad,
            'originalName' => 'broken.srt',
        ]]);

        $this->assertFalse($result['ok']);
        $this->assertFileExists($existing);
        $this->assertSame($good, file_get_contents($existing));
    }
}
