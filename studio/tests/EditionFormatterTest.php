<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\EditionFormatter;

class EditionFormatterTest extends TestCase
{
    private EditionFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new EditionFormatter();
    }

    public function test_builds_year_city_id_and_label(): void
    {
        $result = $this->formatter->format('Valencia', '2020');

        $this->assertSame([
            'id' => '2020-valencia',
            'label' => 'Valencia 2020',
        ], $result);
    }

    public function test_preserves_city_casing_in_label(): void
    {
        $result = $this->formatter->format('São Paulo', '2023');

        $this->assertSame('2023-sao-paulo', $result['id']);
        $this->assertSame('São Paulo 2023', $result['label']);
    }

    public function test_slugifies_multi_word_city(): void
    {
        $result = $this->formatter->format('Mexico City', '2021');

        $this->assertSame('2021-mexico-city', $result['id']);
        $this->assertSame('Mexico City 2021', $result['label']);
    }

    public function test_returns_null_for_invalid_year(): void
    {
        $this->assertNull($this->formatter->format('Rome', '26'));
        $this->assertNull($this->formatter->format('Rome', ''));
    }

    public function test_returns_null_for_empty_city(): void
    {
        $this->assertNull($this->formatter->format('   ', '2026'));
    }
}
