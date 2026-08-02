<?php

use PHPUnit\Framework\TestCase;

final class CreditsEditorViewTest extends TestCase
{
    public function test_credits_editor_uses_esm_sh_bundles_for_compatible_codemirror_modules(): void
    {
        $view = file_get_contents(__DIR__ . '/../views/credits-editor.php');

        self::assertIsString($view);
        self::assertStringContainsString(
            "https://esm.sh/codemirror@6.0.1",
            $view
        );
        self::assertStringNotContainsString(
            "https://cdn.jsdelivr.net/npm/codemirror@6/+esm",
            $view
        );
        self::assertStringContainsString(
            "https://esm.sh/@codemirror/lang-php@6.0.1",
            $view
        );
        self::assertStringNotContainsString(
            "https://cdn.jsdelivr.net/npm/@codemirror/lang-php@6.0.1/+esm",
            $view
        );
    }
}
