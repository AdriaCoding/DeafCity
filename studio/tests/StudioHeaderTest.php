<?php

namespace Studio\Tests;

use PHPUnit\Framework\TestCase;
use Studio\StudioHeader;

class StudioHeaderTest extends TestCase
{
    public function test_sync_status_message_when_idle(): void
    {
        $this->assertSame('', StudioHeader::syncStatusMessage(null));
        $this->assertSame('', StudioHeader::syncStatusMessage(['status' => 'idle']));
    }

    public function test_sync_status_message_when_running(): void
    {
        $msg = StudioHeader::syncStatusMessage(['status' => 'running', 'synced' => 3, 'total' => 10]);

        $this->assertSame('Sincronitzant… (3/10)', $msg);
    }

    public function test_sync_status_message_when_done(): void
    {
        $msg = StudioHeader::syncStatusMessage(['status' => 'done', 'synced' => 12, 'total' => 26]);

        $this->assertSame('Sincronitzat (12/26 vídeos)', $msg);
    }

    public function test_resolve_active_nav_from_action(): void
    {
        $this->assertSame(StudioHeader::NAV_CATALOG, StudioHeader::resolveActiveNavFromAction(null));
        $this->assertSame(StudioHeader::NAV_CATALOG, StudioHeader::resolveActiveNavFromAction('continguts'));
        $this->assertSame(StudioHeader::NAV_CATALOG, StudioHeader::resolveActiveNavFromAction('continguts-video'));
        $this->assertSame(StudioHeader::NAV_INTAKE, StudioHeader::resolveActiveNavFromAction('intake'));
        $this->assertSame(
            StudioHeader::NAV_TRANSCRIPTION_INTAKE,
            StudioHeader::resolveActiveNavFromAction('transcription-intake'),
        );
        $this->assertNull(StudioHeader::resolveActiveNavFromAction('translation'));
    }

    public function test_pipeline_step_from_action(): void
    {
        $this->assertSame('translation', StudioHeader::pipelineStepFromAction('translation'));
        $this->assertSame('translation', StudioHeader::pipelineStepFromAction('translation-review'));
        $this->assertSame('tagging', StudioHeader::pipelineStepFromAction('tagging'));
        $this->assertSame('publication', StudioHeader::pipelineStepFromAction('publication'));
        $this->assertNull(StudioHeader::pipelineStepFromAction('intake'));
    }

    public function test_render_includes_catalog_link_and_active_state(): void
    {
        $html = StudioHeader::renderHtml(
            baseUrl: '/studio/',
            syncStatus: ['status' => 'done', 'synced' => 1, 'total' => 2],
            isSyncing: false,
            activeNav: StudioHeader::NAV_CATALOG,
            pipelineStep: null,
        );

        $this->assertStringContainsString('aria-label="Navegació principal"', $html);
        $this->assertStringContainsString('class="studio-brand" href="/studio/"', $html);
        $this->assertStringContainsString('aria-label="Torna al catàleg"', $html);
        $this->assertStringContainsString('>Catàleg</a>', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('Tanca la sessió', $html);
        $this->assertStringNotContainsString('Sortir', $html);
    }

    public function test_render_includes_pipeline_steps_when_in_pipeline(): void
    {
        $html = StudioHeader::renderHtml(
            baseUrl: '/studio/',
            syncStatus: null,
            isSyncing: false,
            activeNav: null,
            pipelineStep: 'tagging',
        );

        $this->assertStringContainsString('studio-pipeline-steps', $html);
        $this->assertStringContainsString('Etiquetatge', $html);
        $this->assertStringContainsString('aria-current="step"', $html);
    }
}
