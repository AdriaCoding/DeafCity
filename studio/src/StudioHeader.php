<?php

namespace Studio;

final class StudioHeader
{
    public const NAV_CATALOG = 'catalog';
    public const NAV_INTAKE = 'intake';
    public const NAV_TRANSCRIPTION_INTAKE = 'transcription-intake';

    /** @param array<string, mixed>|null $syncStatus */
    public static function syncStatusMessage(?array $syncStatus): string
    {
        $status = $syncStatus['status'] ?? 'idle';
        if ($status === 'running') {
            $n = (int) ($syncStatus['synced'] ?? 0);
            $t = (int) ($syncStatus['total'] ?? 0);

            return "Sincronitzant… ($n/$t)";
        }
        if ($status === 'done') {
            $n = (int) ($syncStatus['synced'] ?? 0);
            $t = (int) ($syncStatus['total'] ?? 0);

            return "Sincronitzat ($n/$t vídeos)";
        }

        return '';
    }

    public static function resolveActiveNavFromAction(?string $action): ?string
    {
        if ($action === null || $action === '' || $action === 'continguts') {
            return self::NAV_CATALOG;
        }
        if (str_starts_with($action, 'continguts')) {
            return self::NAV_CATALOG;
        }
        if ($action === 'intake') {
            return self::NAV_INTAKE;
        }
        if ($action === 'transcription-intake') {
            return self::NAV_TRANSCRIPTION_INTAKE;
        }

        return null;
    }

    public static function pipelineStepFromAction(?string $action): ?string
    {
        return match ($action) {
            'translation', 'translation-review', 'translation-loading', 'translation-status', 'translation-retry' => 'translation',
            'tagging', 'skip-to-tagging', 'proceed-to-tagging' => 'tagging',
            'publication' => 'publication',
            default => null,
        };
    }

    /** @param array<string, mixed>|null $syncStatus */
    public static function loadSyncFromDataDir(string $dataDir): array
    {
        $syncStatusPath = rtrim($dataDir, '/') . '/sync-status.json';
        $raw = is_file($syncStatusPath) ? @file_get_contents($syncStatusPath) : false;
        $syncStatus = $raw ? json_decode($raw, true) : null;
        $isSyncing = is_array($syncStatus) && ($syncStatus['status'] ?? '') === 'running';

        return [$syncStatus, $isSyncing];
    }

    /** @param array<string, mixed>|null $syncStatus */
    public static function vars(
        Container $c,
        ?string $activeNav = null,
        ?string $pipelineStep = null,
        ?array $syncStatus = null,
        ?bool $isSyncing = null,
    ): array {
        if ($syncStatus === null || $isSyncing === null) {
            [$loadedStatus, $loadedSyncing] = self::loadSyncFromDataDir($c->dataDir);
            $syncStatus ??= $loadedStatus;
            $isSyncing ??= $loadedSyncing;
        }

        return [
            'baseUrl' => $c->baseUrl,
            'syncStatus' => $syncStatus,
            'isSyncing' => $isSyncing,
            'activeNav' => $activeNav,
            'pipelineStep' => $pipelineStep,
        ];
    }

    /** @param array<string, mixed>|null $syncStatus */
    public static function renderHtml(
        string $baseUrl,
        ?array $syncStatus,
        bool $isSyncing,
        ?string $activeNav,
        ?string $pipelineStep,
    ): string {
        ob_start();
        self::includePartial(compact('baseUrl', 'syncStatus', 'isSyncing', 'activeNav', 'pipelineStep'));
        return (string) ob_get_clean();
    }

    /** @param array<string, mixed> $vars */
    public static function include(array $vars): void
    {
        self::includePartial($vars);
    }

    /** @param array<string, mixed> $vars */
    private static function includePartial(array $vars): void
    {
        extract($vars, EXTR_SKIP);
        require dirname(__DIR__) . '/views/partials/studio-header.php';
    }
}
