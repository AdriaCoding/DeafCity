<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\BackgroundJobLauncher;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\TranslationCoordinator;
use Studio\TranslationJobState;

class TranslationCoordinatorTest extends TestCase
{
    private string $jobsDir;
    private string $configPath;
    private JobManager $jobManager;

    protected function setUp(): void
    {
        $this->jobsDir = sys_get_temp_dir() . '/studio-coordinator-' . uniqid();
        mkdir($this->jobsDir);
        mkdir($this->jobsDir . '/current');

        $this->configPath = sys_get_temp_dir() . '/studio-coordinator-config-' . uniqid() . '.json';
        file_put_contents($this->configPath, json_encode([
            'sign_languages' => [],
            'editions' => [],
            'subtitle_languages' => [
                ['id' => 'es', 'label' => 'Spanish', 'vimeo_code' => 'es', 'translation_target' => true],
                ['id' => 'en', 'label' => 'English', 'vimeo_code' => 'en', 'translation_target' => true],
                ['id' => 'fr', 'label' => 'French', 'vimeo_code' => 'fr', 'translation_target' => true],
                ['id' => 'arq', 'label' => 'Algerian Darija', 'vimeo_code' => 'ar', 'translation_target' => false],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        file_put_contents($this->jobsDir . '/current/job.json', json_encode([
            'step' => 'translation',
            'subtitle_language' => 'es',
            'vimeo_id' => '123',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        file_put_contents($this->jobsDir . '/current/draft.vtt', "WEBVTT\n\n00:00:01.000 --> 00:00:04.000\nHola\n");

        $this->jobManager = new JobManager($this->jobsDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->jobsDir);
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function test_spawn_translates_only_flagged_targets_excluding_master(): void
    {
        $capturedCmd = null;
        $launcher = new BackgroundJobLauncher('/srv/scripts', '', function ($cmd) use (&$capturedCmd) {
            $capturedCmd = $cmd;
        });

        $coordinator = new TranslationCoordinator(
            $this->jobManager,
            new StudioConfig($this->configPath),
            $launcher,
        );

        $coordinator->spawn('es');

        $this->assertNotNull($capturedCmd);
        $this->assertStringContainsString(escapeshellarg('en,fr'), $capturedCmd);
        $this->assertStringNotContainsString('arq', $capturedCmd);
    }

    public function test_spawn_with_no_targets_advances_job_to_tagging(): void
    {
        file_put_contents($this->configPath, json_encode([
            'sign_languages' => [],
            'editions' => [],
            'subtitle_languages' => [
                ['id' => 'es', 'label' => 'Spanish', 'vimeo_code' => 'es', 'translation_target' => false],
                ['id' => 'arq', 'label' => 'Algerian Darija', 'vimeo_code' => 'ar', 'translation_target' => false],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        $launched = false;
        $launcher = new BackgroundJobLauncher('/srv/scripts', '', function () use (&$launched) {
            $launched = true;
        });

        $coordinator = new TranslationCoordinator(
            $this->jobManager,
            new StudioConfig($this->configPath),
            $launcher,
        );

        $coordinator->spawn('es');

        $this->assertFalse($launched);
        $this->assertSame('tagging', $this->jobManager->read()['step']);

        $state = (new TranslationJobState($this->jobManager))->read();
        $this->assertSame('done', $state['status']);
        $this->assertSame([], $state['languages']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
