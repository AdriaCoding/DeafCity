<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for caption-translate modal UX on the video detail page:
 * manual close only (no auto-reload), retry available while running or after save.
 */
class ContingutsVideoCaptionTranslateUiTest extends TestCase
{
    private string $viewSource;

    protected function setUp(): void
    {
        $this->viewSource = (string) file_get_contents(__DIR__ . '/../views/continguts-video.php');
    }

    public function test_modal_does_not_auto_reload_on_completion(): void
    {
        $this->assertStringNotContainsString('ctScheduleReload', $this->viewSource);
        $this->assertStringNotContainsString('Actualitzant la pàgina', $this->viewSource);
    }

    public function test_running_state_offers_retry_for_failed_languages(): void
    {
        $this->assertStringContainsString("status === 'error'", $this->viewSource);
        $this->assertStringContainsString('class="ct-retry-btn"', $this->viewSource);
    }

    public function test_saved_state_always_shows_manual_close(): void
    {
        $this->assertStringContainsString('ctPendingReload', $this->viewSource);
        $this->assertMatchesRegularExpression(
            "/case 'saved':[\\s\\S]*id=\"ct-close-btn\"/",
            $this->viewSource,
        );
    }
}
