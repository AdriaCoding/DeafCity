<?php

namespace Studio;

final class StudioHeader
{
    public const NAV_CATALOG = 'catalog';
    public const NAV_TRANSCRIPTION_INTAKE = 'transcription-intake';
    public const NAV_LOCALIZATIONS = 'localizations';
    public const NAV_CREDITS_EDITOR = 'credits-editor';

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

            return "Última sincronització: $n enviats de $t";
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
        if ($action === 'transcription-intake') {
            return self::NAV_TRANSCRIPTION_INTAKE;
        }
        if ($action === 'localitzacions' || str_starts_with($action, 'localitzacions-')) {
            return self::NAV_LOCALIZATIONS;
        }
        if ($action === 'credits-editor' || str_starts_with($action, 'credits-editor-')) {
            return self::NAV_CREDITS_EDITOR;
        }

        return null;
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
        ];
    }

    /** @param array<string, mixed>|null $syncStatus */
    public static function renderHtml(
        string $baseUrl,
        ?array $syncStatus,
        bool $isSyncing,
        ?string $activeNav,
    ): string {
        ob_start();
        self::includePartial(compact('baseUrl', 'syncStatus', 'isSyncing', 'activeNav'));
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
