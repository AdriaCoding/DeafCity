<?php

namespace Studio\Actions;

use Studio\Container;
use Studio\StudioHeader;

/**
 * Editor scoped to views/about/credits_body.html — the one file non-technical
 * staff (Toni) need to update by hand each time a city/edition is added. Not a
 * general file browser: the path is fixed on purpose.
 *
 * The body is inert HTML with a fixed {{token}} vocabulary, never PHP. It used
 * to be views/about/credits_i18n.php, which the public About page includes on
 * every visit — so anyone holding a Studio session could publish arbitrary PHP
 * that then ran for every visitor. The computation and translation half now
 * lives in credits_i18n.php / credits_render.php, which this editor cannot
 * reach; saving here can only ever change markup and text.
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
        return dirname($this->c->dataDir) . '/views/about/credits_body.html';
    }

    private function backupPath(): string
    {
        return $this->c->dataDir . '/credits_body.backup.html';
    }

    /**
     * Reject anything that would make the body executable or active again, and
     * any {{token}} the renderer does not know how to fill.
     *
     * @return string|null Catalan error message, or null when the body is fine.
     */
    public static function validateBody(string $content): ?string
    {
        if (trim($content) === '') {
            return 'El contingut no pot estar buit.';
        }

        if (preg_match('/<\\?/', $content)) {
            return "El text dels crèdits és HTML, no PHP: no pot contenir '<?'.";
        }
        if (preg_match('/<\\s*(script|iframe|object|embed|form|style|link|meta|base)\\b/i', $content, $m)) {
            return "No es permet l'etiqueta <" . strtolower($m[1]) . "> al text dels crèdits.";
        }
        // on…= handlers (onclick, onerror, …) would run script without a <script> tag.
        if (preg_match('/\\son[a-z]+\\s*=/i', $content)) {
            return "No es permeten atributs d'esdeveniment (onclick, onerror…) al text dels crèdits.";
        }
        if (preg_match('/(href|src)\\s*=\\s*["\\\']?\\s*javascript:/i', $content)) {
            return "No es permeten enllaços 'javascript:' al text dels crèdits.";
        }

        require_once dirname(__DIR__, 3) . '/views/about/credits_render.php';
        $known = credits_render_known_tokens();
        preg_match_all('/\\{\\{([^}]*)\\}\\}/', $content, $found);
        foreach ($found[1] as $token) {
            if (!in_array($token, $known, true)) {
                return 'Marcador desconegut: {{' . $token . '}}. Els disponibles són: {{' . implode('}}, {{', $known) . '}}.';
            }
        }

        return null;
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
        $invalid = self::validateBody($content);
        if ($invalid !== null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $invalid], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $path = $this->filePath();
        $tmpPath = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmpPath, $content) === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => "No s'ha pogut escriure el fitxer temporal."], JSON_UNESCAPED_UNICODE);
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
