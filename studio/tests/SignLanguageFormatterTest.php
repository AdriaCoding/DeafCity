<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SignLanguageFormatter;

class SignLanguageFormatterTest extends TestCase
{
    private SignLanguageFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SignLanguageFormatter();
    }

    public function test_builds_id_and_label(): void
    {
        $result = $this->formatter->format('GSS', 'Greek');

        $this->assertSame([
            'id' => 'gss',
            'label' => 'GSS Greek Sign Language',
        ], $result);
    }

    public function test_preserves_code_casing_in_label(): void
    {
        $result = $this->formatter->format('LIBRAS', 'Brazilian');

        $this->assertSame('libras', $result['id']);
        $this->assertSame('LIBRAS Brazilian Sign Language', $result['label']);
    }

    public function test_slugifies_multi_word_code(): void
    {
        $result = $this->formatter->format('Tunisian SL', 'Tunisian');

        $this->assertSame('tunisian-sl', $result['id']);
    }

    public function test_returns_null_for_empty_fields(): void
    {
        $this->assertNull($this->formatter->format('', 'Greek'));
        $this->assertNull($this->formatter->format('GSS', '   '));
    }
}
