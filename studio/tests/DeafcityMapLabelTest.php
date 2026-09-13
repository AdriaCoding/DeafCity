<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\DeafcityMapLabel;

class DeafcityMapLabelTest extends TestCase
{
    public function test_formats_city_and_sign_language_code(): void
    {
        $label = (new DeafcityMapLabel())->format('Barcelona', 'lsc');

        $this->assertSame('DEAF.city BARCELONA LSC', $label);
    }

    public function test_keeps_accents_when_uppercasing_city(): void
    {
        $label = (new DeafcityMapLabel())->format('València', 'LSE');

        $this->assertSame('DEAF.city VALÈNCIA LSE', $label);
    }

    public function test_rejects_blank_city_or_code(): void
    {
        $formatter = new DeafcityMapLabel();

        $this->assertNull($formatter->format(' ', 'LSC'));
        $this->assertNull($formatter->format('Barcelona', ''));
    }
}
