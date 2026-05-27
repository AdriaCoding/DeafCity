<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogTagPool;

class CatalogTagPoolTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'catalog');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function writeCatalog(array $data): void
    {
        file_put_contents($this->tmpFile, json_encode($data));
    }

    public function test_returns_tags_deduplicated_and_sorted_alphabetically(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'a', 'tags' => ['humor', 'installation']],
            ['id' => 'b', 'tags' => ['ciudad', 'humor']],
        ]]);
        $pool = new CatalogTagPool($this->tmpFile);
        $this->assertSame(['ciudad', 'humor', 'installation'], $pool->getTagsSortedAlphabetically());
    }

    public function test_handles_entries_without_tags_key(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'a', 'tags' => ['beta']],
            ['id' => 'b'],
        ]]);
        $pool = new CatalogTagPool($this->tmpFile);
        $this->assertSame(['beta'], $pool->getTagsSortedAlphabetically());
    }

    public function test_returns_empty_array_for_catalog_with_no_tagged_videos(): void
    {
        $this->writeCatalog(['videos' => [
            ['id' => 'a'],
            ['id' => 'b', 'tags' => []],
        ]]);
        $pool = new CatalogTagPool($this->tmpFile);
        $this->assertSame([], $pool->getTagsSortedAlphabetically());
    }
}
