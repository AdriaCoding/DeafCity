<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\VimeoClient;
use Vimeo\Vimeo;

class VimeoClientWriteTest extends TestCase
{
    private function makeClient(Vimeo $sdk): VimeoClient
    {
        return new VimeoClient('id', 'secret', 'token', $sdk);
    }

    // ── updateTitle ───────────────────────────────────────────────────────────

    public function test_update_title_sends_patch_with_name(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->expects($this->once())
            ->method('request')
            ->with('/videos/111', ['name' => 'New Title'], 'PATCH')
            ->willReturn(['status' => 200, 'body' => []]);

        $this->makeClient($sdk)->updateTitle('111', 'New Title');
    }

    public function test_update_title_throws_on_api_error(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn(['status' => 403, 'body' => []]);

        $this->expectException(\RuntimeException::class);
        $this->makeClient($sdk)->updateTitle('111', 'Title');
    }

    // ── setTags ───────────────────────────────────────────────────────────────

    public function test_set_tags_deletes_old_tags_and_adds_new(): void
    {
        $sdk = $this->createMock(Vimeo::class);

        $callLog = [];
        $sdk->method('request')
            ->willReturnCallback(function (string $uri, array $params, string $method) use (&$callLog) {
                $callLog[] = [$uri, $method];
                if ($method === 'GET') {
                    return ['status' => 200, 'body' => ['data' => [
                        ['tag' => 'old-tag', 'uri' => '/tags/old-tag'],
                    ]]];
                }
                return ['status' => 204, 'body' => []];
            });

        $this->makeClient($sdk)->setTags('111', ['new-tag']);

        $this->assertContains(['/videos/111/tags', 'GET'], $callLog);
        $this->assertContains(['/videos/111/tags/old-tag', 'DELETE'], $callLog);
        $this->assertContains(['/videos/111/tags/new-tag', 'PUT'], $callLog);
    }

    public function test_set_tags_adds_only_when_no_existing_tags(): void
    {
        $sdk = $this->createMock(Vimeo::class);

        $callLog = [];
        $sdk->method('request')
            ->willReturnCallback(function (string $uri, array $params, string $method) use (&$callLog) {
                $callLog[] = [$uri, $method];
                if ($method === 'GET') {
                    return ['status' => 200, 'body' => ['data' => []]];
                }
                return ['status' => 204, 'body' => []];
            });

        $this->makeClient($sdk)->setTags('111', ['alpha', 'beta']);

        $methods = array_column($callLog, 1);
        $this->assertNotContains('DELETE', $methods);
        $this->assertContains(['/videos/111/tags/alpha', 'PUT'], $callLog);
        $this->assertContains(['/videos/111/tags/beta', 'PUT'], $callLog);
    }

    public function test_set_tags_skips_add_when_empty_new_list(): void
    {
        $sdk = $this->createMock(Vimeo::class);

        $callLog = [];
        $sdk->method('request')
            ->willReturnCallback(function (string $uri, array $params, string $method) use (&$callLog) {
                $callLog[] = [$uri, $method];
                if ($method === 'GET') {
                    return ['status' => 200, 'body' => ['data' => [
                        ['tag' => 'old', 'uri' => '/tags/old'],
                    ]]];
                }
                return ['status' => 204, 'body' => []];
            });

        $this->makeClient($sdk)->setTags('111', []);

        $methods = array_column($callLog, 1);
        $this->assertNotContains('PUT', $methods);
        $this->assertContains('DELETE', $methods);
    }

    public function test_set_tags_throws_on_get_tags_failure(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn(['status' => 500, 'body' => []]);

        $this->expectException(\RuntimeException::class);
        $this->makeClient($sdk)->setTags('111', ['tag']);
    }
}
