<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\CatalogEditor;
use Studio\SignLanguageAddHandler;
use Studio\StudioConfig;
use Studio\StudioConfigMutation;

class SignLanguageAddHandlerTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/studio-sign-lang-add-' . uniqid() . '.json';
        copy(__DIR__ . '/fixtures/studio-config.json', $this->configPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->configPath)) {
            unlink($this->configPath);
        }
        @unlink($this->configPath . '.lock');
    }

    private function makeMutation(): StudioConfigMutation
    {
        return new StudioConfigMutation(
            new StudioConfig($this->configPath),
            new CatalogEditor($this->configPath . '.catalog-unused'),
        );
    }

    public function test_adds_sign_language_to_config_file(): void
    {
        $handler = new SignLanguageAddHandler($this->makeMutation());

        $result = $handler->handle('GSS', 'Greek');

        $this->assertTrue($result['ok']);
        $this->assertSame('gss', $result['id']);
        $this->assertSame('GSS Greek', $result['label']);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getSignLanguages(), 'id');
        $this->assertContains('gss', $ids);
    }

    public function test_rejects_duplicate_sign_language(): void
    {
        $handler = new SignLanguageAddHandler($this->makeMutation());

        $this->assertTrue($handler->handle('GSS', 'Greek')['ok']);
        $again = $handler->handle('GSS', 'Greek');

        $this->assertFalse($again['ok']);
    }
}
