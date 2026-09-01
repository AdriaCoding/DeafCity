<?php

use PHPUnit\Framework\TestCase;

final class CreditsEditorViewTest extends TestCase
{
    public function test_credits_editor_uses_esm_sh_bundles_for_compatible_codemirror_modules(): void
    {
        // The editor edits views/about/credits_body.html — inert HTML with
        // {{token}} placeholders — so the syntax mode is HTML. It used to edit
        // credits_i18n.php directly, which made any Studio session able to
        // publish PHP that ran for every visitor to the About page.
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
            "https://esm.sh/@codemirror/lang-html@6.4.9",
            $view
        );
        self::assertStringNotContainsString(
            "https://cdn.jsdelivr.net/npm/@codemirror/lang-php@6.0.1/+esm",
            $view
        );
        self::assertStringNotContainsString(
            "https://esm.sh/@codemirror/lang-php@6.0.1",
            $view
        );
    }
}
