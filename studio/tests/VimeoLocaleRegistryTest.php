<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\VimeoLocaleRegistry;

class VimeoLocaleRegistryTest extends TestCase
{
    private VimeoLocaleRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new VimeoLocaleRegistry(__DIR__ . '/../js/vimeo-texttrack-locales.json');
    }

    public function test_accepts_known_vimeo_locale(): void
    {
        $this->assertTrue($this->registry->isValidCode('es'));
    }

    public function test_accepts_maltese_locale_for_tunisian_mapping(): void
    {
        $this->assertTrue($this->registry->isValidCode('mt'));
    }

    public function test_rejects_unknown_locale(): void
    {
        $this->assertFalse($this->registry->isValidCode('zzz'));
    }

    public function test_rejects_dialect_code_not_in_vimeo_list(): void
    {
        $this->assertFalse($this->registry->isValidCode('arq'));
    }

    public function test_all_codes_includes_common_locales(): void
    {
        $codes = $this->registry->allCodes();

        $this->assertContains('es', $codes);
        $this->assertContains('ar', $codes);
        $this->assertContains('mt', $codes);
    }
}
