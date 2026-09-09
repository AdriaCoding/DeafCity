<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\VimeoClient;
use Studio\VimeoForbiddenException;
use Studio\VimeoNotFoundException;
use Studio\VimeoRateLimitedException;
use Studio\VimeoRequestException;
use Studio\VimeoTransientException;
use Studio\VimeoUnauthorizedException;
use Vimeo\Vimeo;

class VimeoClientReadClassificationTest extends TestCase
{
    private function makeClient(Vimeo $sdk): VimeoClient
    {
        return new VimeoClient('id', 'secret', 'token', $sdk);
    }

    public function test_getVideo_uses_fields_name_and_returns_title(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->expects($this->once())
            ->method('request')
            ->with('/videos/111?fields=name', [], 'GET')
            ->willReturn([
                'status' => 200,
                'body' => ['name' => 'Hello'],
                'headers' => [],
            ]);

        $this->assertSame('Hello', $this->makeClient($sdk)->getVideo('111'));
    }

    public function test_getVideo_404_throws_not_found(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 404,
            'body' => [
                'error' => 'Not found',
                'developer_message' => 'The requested video could not be found.',
                'error_code' => 5000,
            ],
            'headers' => [],
        ]);

        try {
            $this->makeClient($sdk)->getVideo('404');
            $this->fail('Expected VimeoNotFoundException');
        } catch (VimeoNotFoundException $e) {
            $this->assertStringContainsString('5000', $e->getMessage());
            $this->assertStringContainsString('could not be found', $e->getMessage());
        }
    }

    public function test_getVideo_429_throws_rate_limited_with_reset(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 429,
            'body' => [
                'error' => 'Rate limit',
                'developer_message' => 'Quota exceeded',
                'error_code' => 9000,
            ],
            'headers' => [
                'X-RateLimit-Reset' => '1700000060',
            ],
        ]);

        try {
            $this->makeClient($sdk)->getVideo('111');
            $this->fail('Expected VimeoRateLimitedException');
        } catch (VimeoRateLimitedException $e) {
            $this->assertSame(1700000060, $e->resetAt);
            $this->assertStringContainsString('9000', $e->getMessage());
            $this->assertSame(10, $e->secondsUntilReset(1700000050));
        }
    }

    public function test_getVideo_401_throws_unauthorized(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 401,
            'body' => ['error_code' => 8000, 'developer_message' => 'Bad token'],
            'headers' => [],
        ]);

        $this->expectException(VimeoUnauthorizedException::class);
        $this->makeClient($sdk)->getVideo('111');
    }

    public function test_getVideo_403_throws_forbidden_not_not_found(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 403,
            'body' => ['error' => 'Forbidden'],
            'headers' => [],
        ]);

        $this->expectException(VimeoForbiddenException::class);
        $this->makeClient($sdk)->getVideo('111');
    }

    public function test_getVideo_503_throws_transient(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 503,
            'body' => ['error' => 'Unavailable'],
            'headers' => [],
        ]);

        $this->expectException(VimeoTransientException::class);
        $this->makeClient($sdk)->getVideo('111');
    }

    public function test_getVideo_error_code_5033_throws_not_found(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 404,
            'body' => [
                'error_code' => 5033,
                'developer_message' => 'Video in purgatory',
            ],
            'headers' => [],
        ]);

        $this->expectException(VimeoNotFoundException::class);
        $this->makeClient($sdk)->getVideo('111');
    }

    public function test_getVideo_other_4xx_throws_request_exception_not_not_found(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')->willReturn([
            'status' => 400,
            'body' => ['error' => 'Bad request'],
            'headers' => [],
        ]);

        $this->expectException(VimeoRequestException::class);
        $this->makeClient($sdk)->getVideo('111');
    }

    public function test_parse_rate_limit_reset_iso8601(): void
    {
        $ts = VimeoClient::parseRateLimitReset('2024-01-01T00:01:00+00:00');
        $this->assertSame(strtotime('2024-01-01T00:01:00+00:00'), $ts);
    }

    public function test_fetchVideoForCatalogSync_requests_combined_fields(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $path) {
                if ($path === '/videos/111?fields=name,pictures,player_embed_url') {
                    return [
                        'status' => 200,
                        'body' => [
                            'name' => 'Title',
                            'player_embed_url' => 'https://player.vimeo.com/video/111',
                            'pictures' => [
                                'base_link' => 'https://i.vimeocdn.com/video/abc-d?region=us',
                                'sizes' => [
                                    ['width' => 640, 'link' => 'https://i.vimeocdn.com/video/abc-d_640x360?r=pad&region=us'],
                                    ['width' => 1920, 'link' => 'https://i.vimeocdn.com/video/abc-d_1920x1080?r=pad&region=us'],
                                ],
                            ],
                        ],
                        'headers' => ['X-RateLimit-Remaining' => '50'],
                    ];
                }
                if ($path === '/videos/111/pictures?sizes=3840x2160') {
                    return [
                        'status' => 200,
                        'body' => [
                            'data' => [[
                                'sizes' => [
                                    ['width' => 3840, 'link' => 'https://i.vimeocdn.com/video/abc-d_3840x2160?r=pad&region=us'],
                                ],
                            ]],
                        ],
                        'headers' => [],
                    ];
                }
                $this->fail('Unexpected Vimeo path: ' . $path);
            });

        $meta = $this->makeClient($sdk)->fetchVideoForCatalogSync('111', true, true);

        $this->assertSame('Title', $meta['title']);
        $this->assertSame('https://i.vimeocdn.com/video/abc-d_1920x1080?r=pad&region=us', $meta['thumbnail_url']);
        $this->assertSame('https://i.vimeocdn.com/video/abc-d?region=us', $meta['thumbnail_base']);
        $this->assertSame('https://player.vimeo.com/video/111', $meta['embed_url']);
    }

    public function test_fetchVideoForCatalogSync_keeps_1920_when_4k_pictures_fail(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->method('request')
            ->willReturnCallback(function (string $path) {
                if (str_contains($path, '/pictures?sizes=')) {
                    return ['status' => 500, 'body' => ['error' => 'nope'], 'headers' => []];
                }
                return [
                    'status' => 200,
                    'body' => [
                        'name' => 'Title',
                        'pictures' => [
                            'base_link' => 'https://i.vimeocdn.com/video/abc-d',
                            'sizes' => [
                                ['width' => 1920, 'link' => 'https://i.vimeocdn.com/video/abc-d_1920x1080'],
                            ],
                        ],
                    ],
                    'headers' => [],
                ];
            });

        $meta = $this->makeClient($sdk)->fetchVideoForCatalogSync('111', true, false);

        $this->assertSame('https://i.vimeocdn.com/video/abc-d_1920x1080', $meta['thumbnail_url']);
        $this->assertSame('https://i.vimeocdn.com/video/abc-d', $meta['thumbnail_base']);
    }

    public function test_fetchVideoForCatalogSync_title_only_when_media_not_needed(): void
    {
        $sdk = $this->createMock(Vimeo::class);
        $sdk->expects($this->once())
            ->method('request')
            ->with('/videos/111?fields=name', [], 'GET')
            ->willReturn([
                'status' => 200,
                'body' => ['name' => 'Title only'],
                'headers' => [],
            ]);

        $meta = $this->makeClient($sdk)->fetchVideoForCatalogSync('111', false, false);

        $this->assertSame('Title only', $meta['title']);
        $this->assertNull($meta['thumbnail_url']);
        $this->assertNull($meta['thumbnail_base']);
        $this->assertNull($meta['embed_url']);
    }
}
