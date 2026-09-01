<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\Actions\CatalogAction;

/**
 * Proves CatalogAction::resolveSafeCaptionPath() — used before every read,
 * write and unlink built from a catalog-supplied caption `file` value —
 * refuses to resolve outside the captions directory, no matter how the
 * traversal is spelled.
 */
class CaptionPathSafetyTest extends TestCase
{
    private string $root;
    private string $captionsDir;
    private string $secretFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/studio-caption-path-safety-' . uniqid();
        $this->captionsDir = $this->root . '/captions';
        mkdir($this->captionsDir, 0777, true);
        mkdir($this->root . '/secret', 0777, true);

        $this->secretFile = $this->root . '/secret/config.php';
        file_put_contents($this->secretFile, "<?php // SECRET\n");

        file_put_contents($this->captionsDir . '/111.es.vtt', "WEBVTT\n\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_resolves_a_legitimate_file_inside_the_captions_dir(): void
    {
        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, '111.es.vtt');

        $this->assertSame(realpath($this->captionsDir . '/111.es.vtt'), $result);
    }

    public function test_rejects_relative_traversal_out_of_the_captions_dir(): void
    {
        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, '../secret/config.php');

        $this->assertFalse($result);
        $this->assertNotSame(realpath($this->secretFile), $result);
    }

    public function test_rejects_deep_relative_traversal_to_repo_config(): void
    {
        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, '../../config/config.php');

        $this->assertFalse($result);
    }

    public function test_rejects_an_absolute_path(): void
    {
        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, $this->secretFile);

        $this->assertFalse($result);
        $this->assertNotSame(realpath($this->secretFile), $result);
    }

    public function test_rejects_an_absolute_path_even_when_a_same_named_file_exists_in_captions_dir(): void
    {
        // The dangerous case: basename('/root/secret/config.php') is
        // 'config.php'. If a file that happens to be named 'config.php'
        // also legitimately lives in the captions dir, resolving to *that*
        // file (not the absolute path) is correct and expected — it must
        // never leak the outside file.
        file_put_contents($this->captionsDir . '/config.php', 'not a secret, just a caption file');

        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, $this->secretFile);

        $this->assertSame(realpath($this->captionsDir . '/config.php'), $result);
        $this->assertNotSame(realpath($this->secretFile), $result);
    }

    public function test_rejects_url_encoded_style_traversal_values(): void
    {
        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, '..%2f..%2fsecret%2fconfig.php');

        $this->assertFalse($result);
    }

    public function test_rejects_null_and_empty_file(): void
    {
        $this->assertFalse(CatalogAction::resolveSafeCaptionPath($this->captionsDir, null));
        $this->assertFalse(CatalogAction::resolveSafeCaptionPath($this->captionsDir, ''));
    }

    public function test_rejects_dot_and_dot_dot_as_the_whole_value(): void
    {
        $this->assertFalse(CatalogAction::resolveSafeCaptionPath($this->captionsDir, '.'));
        $this->assertFalse(CatalogAction::resolveSafeCaptionPath($this->captionsDir, '..'));
    }

    public function test_rejects_a_file_that_does_not_exist(): void
    {
        $result = CatalogAction::resolveSafeCaptionPath($this->captionsDir, 'does-not-exist.vtt');

        $this->assertFalse($result);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
