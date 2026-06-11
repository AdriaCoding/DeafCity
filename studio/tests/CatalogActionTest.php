<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\Actions\CatalogAction;
use Studio\BackgroundJobLauncher;
use Studio\Container;
use Studio\JobManager;
use Studio\StudioConfig;

class CatalogActionTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/studio-catalog-action-' . uniqid();
        mkdir($this->dataDir, 0777, true);
        mkdir($this->dataDir . '/jobs', 0777, true);
        file_put_contents($this->dataDir . '/catalog.json', json_encode(['videos' => []]));
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dataDir);
    }

    public function test_continguts_context_includes_catalog_and_sync_state(): void
    {
        file_put_contents(
            $this->dataDir . '/sync-status.json',
            json_encode(['status' => 'done', 'synced' => 3, 'total' => 3]),
        );

        $context = $this->action()->contingutsContext();

        $this->assertSame([], $context['catalogVideos']);
        $this->assertSame('done', $context['syncStatus']['status']);
        $this->assertFalse($context['isSyncing']);
    }

    private function action(): CatalogAction
    {
        return new CatalogAction(new Container(
            dataDir: $this->dataDir,
            baseUrl: '/studio/',
            jobManager: new JobManager($this->dataDir . '/jobs'),
            studioConfig: new StudioConfig(__DIR__ . '/fixtures/studio-config.json'),
            launcher: new BackgroundJobLauncher(__DIR__ . '/../scripts', ''),
        ));
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
