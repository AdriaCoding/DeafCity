<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;

/**
 * "Descarrega SRT" in the catalog caption editor must export the cues
 * currently in the right-hand column (including unsaved edits) via a real
 * HTTP attachment POST — not the last saved file, and not a Blob click.
 */
class ContingutsCaptionEditorDownloadTest extends TestCase
{
    public function test_descarrega_srt_posts_the_cues_being_edited(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../views/continguts-caption-editor.php');

        $this->assertMatchesRegularExpression(
            '/<form[^>]*id="download-srt-form"[^>]*>/',
            $view,
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]*id="download-srt-form"[^>]*method="post"/i',
            $view,
        );
        $this->assertStringContainsString('action=continguts-download-edited-srt', $view);
        $this->assertStringContainsString('name="cues"', $view);
        $this->assertStringContainsString('name="vimeo_id"', $view);
        $this->assertStringContainsString('name="lang"', $view);
        $this->assertStringContainsString('name="csrf_token"', $view);
        $this->assertMatchesRegularExpression(
            '/<(button|input)[^>]*id="download-srt-btn"/',
            $view,
        );
    }

    public function test_editor_script_submits_in_memory_cues_without_a_blob(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../js/continguts-caption-editor.js');

        $this->assertStringNotContainsString('triggerDownload', $js);
        $this->assertStringContainsString('download-srt-form', $js);
        $this->assertStringContainsString('JSON.stringify(translatedCues)', $js);
    }
}
