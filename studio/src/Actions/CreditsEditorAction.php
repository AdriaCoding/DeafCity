<?php

namespace Studio\Actions;

use Studio\Container;
use Studio\StudioHeader;

/**
 * Raw PHP-file editor scoped to views/about/credits_i18n.php — the one file
 * non-technical staff (Toni) need to update by hand each time a city/edition
 * is added. Not a general file browser: the path is fixed on purpose.
 */
class CreditsEditorAction
{
    public function __construct(private Container $c) {}

    public function handle(string $action): never
    {
        match ($action) {
            'credits-editor' => $this->renderEditor(),
            'credits-editor-save' => $this->save(),
            'credits-editor-revert' => $this->revert(),
            default => throw new \InvalidArgumentException("Unknown credits editor action: $action"),
        };
    }

    private function filePath(): string
    {
        return dirname($this->c->dataDir) . '/views/about/credits_i18n.php';
    }

    private function backupPath(): string
    {
        return $this->c->dataDir . '/credits_i18n.backup.php';
    }

    private function renderEditor(): never
    {
        $path = $this->filePath();
        if (!is_readable($path)) {
            throw new \RuntimeException('Credits file not readable: ' . $path);
        }
        $fileContent = (string) file_get_contents($path);
        $hasBackup = is_readable($this->backupPath());

        extract($this->c->headerContext(StudioHeader::NAV_CREDITS_EDITOR));
        require dirname(__DIR__, 2) . '/views/credits-editor.php';
        exit;
    }

    private function save(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $content = (string) ($_POST['content'] ?? '');
        if (trim($content) === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'El contingut no pot estar buit.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $path = $this->filePath();
        $tmpPath = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmpPath, $content) === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => "No s'ha pogut escriure el fitxer temporal."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $lintOutput = [];
        $lintStatus = 0;
        exec('php -l ' . escapeshellarg($tmpPath) . ' 2>&1', $lintOutput, $lintStatus);
        if ($lintStatus !== 0) {
            @unlink($tmpPath);
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => implode("\n", $lintOutput)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (is_readable($path)) {
            copy($path, $this->backupPath());
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => "No s'ha pogut desar el fitxer."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function revert(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $backupPath = $this->backupPath();
        if (!is_readable($backupPath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'No hi ha cap còpia de seguretat.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $path = $this->filePath();
        $currentContent = is_readable($path) ? (string) file_get_contents($path) : '';
        $backupContent = (string) file_get_contents($backupPath);

        if (!copy($backupPath, $path)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No s\'ha pogut restaurar.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Swap rather than delete: current (possibly-just-reverted-from) content
        // becomes the new backup, so hitting revert again toggles back.
        file_put_contents($backupPath, $currentContent);

        echo json_encode(['ok' => true, 'content' => $backupContent], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
