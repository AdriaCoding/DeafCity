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

        $this->assertSame('Última sincronització: 12 enviats de 26', $msg);
    }

    public function test_resolve_active_nav_from_action(): void
    {
        $this->assertSame(StudioHeader::NAV_CATALOG, StudioHeader::resolveActiveNavFromAction(null));
        $this->assertSame(StudioHeader::NAV_CATALOG, StudioHeader::resolveActiveNavFromAction('continguts'));
        $this->assertSame(StudioHeader::NAV_CATALOG, StudioHeader::resolveActiveNavFromAction('continguts-video'));
        $this->assertSame(
            StudioHeader::NAV_TRANSCRIPTION_INTAKE,
            StudioHeader::resolveActiveNavFromAction('transcription-intake'),
        );
        $this->assertSame(StudioHeader::NAV_SHORTEN, StudioHeader::resolveActiveNavFromAction('shorten-intake'));
        $this->assertSame(StudioHeader::NAV_SHORTEN, StudioHeader::resolveActiveNavFromAction('resume-shorten-job'));
        $this->assertSame(StudioHeader::NAV_SHORTEN, StudioHeader::resolveActiveNavFromAction('shorten-bulk-progress'));
        $this->assertNull(StudioHeader::resolveActiveNavFromAction('translation'));
    }

    public function test_render_includes_catalog_link_and_active_state(): void
    {
        $html = StudioHeader::renderHtml(
            baseUrl: '/studio/',
            syncStatus: ['status' => 'done', 'synced' => 1, 'total' => 2],
            isSyncing: false,
            activeNav: StudioHeader::NAV_CATALOG,
        );

        $this->assertStringContainsString('aria-label="Navegació principal"', $html);
        $this->assertStringContainsString('class="studio-brand" href="/studio/"', $html);
        $this->assertStringContainsString('aria-label="Torna al catàleg"', $html);
        $this->assertStringContainsString('>Catàleg</a>', $html);
        $this->assertStringContainsString('>Nova transcripció</a>', $html);
        $this->assertStringContainsString('>Polir subtítols</a>', $html);
        $this->assertStringNotContainsString('Nova feina', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('class="studio-sync-btn"', $html);
        $this->assertStringContainsString('Tanca la sessió', $html);
        $this->assertStringNotContainsString('Sortir', $html);
        $this->assertStringNotContainsString('studio-pipeline-steps', $html);
    }
}
