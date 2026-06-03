<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\SignLanguageAddHandler;
use Studio\StudioConfig;

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
    }

    public function test_adds_sign_language_to_config_file(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new SignLanguageAddHandler($config);

        $result = $handler->handle('GSS', 'Greek');

        $this->assertTrue($result['ok']);
        $this->assertSame('gss', $result['id']);
        $this->assertSame('GSS Greek Sign Language', $result['label']);

        $reloaded = new StudioConfig($this->configPath);
        $ids = array_column($reloaded->getSignLanguages(), 'id');
        $this->assertContains('gss', $ids);
    }

    public function test_rejects_duplicate_sign_language(): void
    {
        $config = new StudioConfig($this->configPath);
        $handler = new SignLanguageAddHandler($config);

        $this->assertTrue($handler->handle('GSS', 'Greek')['ok']);
        $again = $handler->handle('GSS', 'Greek');

        $this->assertFalse($again['ok']);
    }
}
