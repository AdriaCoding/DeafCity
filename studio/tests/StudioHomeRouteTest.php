<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioHomeRoute;

class StudioHomeRouteTest extends TestCase
{
    public function test_default_view_is_continguts_when_no_job(): void
    {
        $this->assertSame(
            StudioHomeRoute::VIEW_CONTINGUTS,
            StudioHomeRoute::resolveDefaultView(false, false),
        );
    }

    public function test_default_view_is_job_shell_when_pipeline_job_active(): void
    {
        $this->assertSame(
            StudioHomeRoute::VIEW_JOB_SHELL,
            StudioHomeRoute::resolveDefaultView(true, false),
        );
    }

    public function test_default_view_is_transcription_loading_when_transcription_job_active(): void
    {
        $this->assertSame(
            StudioHomeRoute::VIEW_TRANSCRIPTION_LOADING,
            StudioHomeRoute::resolveDefaultView(true, true),
        );
    }
}
