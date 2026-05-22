<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\InvalidVimeoIdException;
use Studio\VimeoIdParser;

class VimeoIdParserTest extends TestCase
{
    private VimeoIdParser $parser;

    protected function setUp(): void
    {
        $this->parser = new VimeoIdParser();
    }

    public function test_parses_plain_numeric_id(): void
    {
        $this->assertSame('123456789', $this->parser->parse('123456789'));
    }

    public function test_parses_standard_vimeo_url(): void
    {
        $this->assertSame(
            '123456789',
            $this->parser->parse('https://vimeo.com/123456789')
        );
    }

    public function test_parses_url_with_trailing_slug(): void
    {
        $this->assertSame(
            '123456789',
            $this->parser->parse('https://vimeo.com/123456789/some-title')
        );
    }

    public function test_parses_url_with_query_string(): void
    {
        $this->assertSame(
            '123456789',
            $this->parser->parse('https://vimeo.com/123456789?share=copy')
        );
    }

    public function test_parses_manage_videos_url(): void
    {
        $this->assertSame(
            '639494119',
            $this->parser->parse('https://vimeo.com/manage/videos/639494119')
        );
    }

    public function test_rejects_empty_string(): void
    {
        $this->expectException(InvalidVimeoIdException::class);
        $this->parser->parse('');
    }

    public function test_rejects_non_vimeo_url(): void
    {
        $this->expectException(InvalidVimeoIdException::class);
        $this->parser->parse('https://youtube.com/watch?v=abc');
    }
}
