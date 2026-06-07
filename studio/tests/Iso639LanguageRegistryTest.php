<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\Iso639LanguageRegistry;

class Iso639LanguageRegistryTest extends TestCase
{
    private Iso639LanguageRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new Iso639LanguageRegistry(__DIR__ . '/../js/iso-639-3.json');
    }

    public function test_accepts_known_iso_code(): void
    {
        $this->assertTrue($this->registry->isValidCode('es'));
    }

    public function test_accepts_three_letter_dialect_code(): void
    {
        $this->assertTrue($this->registry->isValidCode('arq'));
    }

    public function test_rejects_unknown_code(): void
    {
        $this->assertFalse($this->registry->isValidCode('zzz'));
    }

    public function test_returns_label_for_known_code(): void
    {
        $this->assertSame('German', $this->registry->labelFor('de'));
    }

    public function test_returns_null_for_unknown_code(): void
    {
        $this->assertNull($this->registry->labelFor('zzz'));
    }
}
