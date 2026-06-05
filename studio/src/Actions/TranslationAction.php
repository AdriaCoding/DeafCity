<?php

namespace Studio\Actions;

use Studio\Container;
use Studio\TranslationJobState;

class TranslationAction
{
    public function __construct(private Container $c) {}

    public function hub(): never
    {
        $c = $this->c;
        if (!$c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        $job = $c->jobManager->read();
        $translationState = new TranslationJobState($c->jobManager);
        $topLevelStatus = $translationState->getTopLevelStatus();
        $masterLang = $job['subtitle_language'] ?? '';

        if (in_array($topLevelStatus, ['pending', 'running'], true)) {
            $languageItems = [];
            foreach ($c->studioConfig->getSubtitleLanguages() as $language) {
                $id = $language['id'] ?? '';
                if ($id === '' || $id === $masterLang) {
                    continue;
                }
                $entry = $translationState->getLanguageStatus($id);
                $languageItems[] = ['id' => $id, 'label' => $language['label'] ?? $id, 'status' => $entry['status'] ?? 'pending'];
            }
            require $this->view('translation-loading.php');
            exit;
        }

        $masterLangLabel = $masterLang;
        $vimeoId = $job['vimeo_id'] ?? '';
        foreach ($c->studioConfig->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $masterLang) {
                $masterLangLabel = $language['label'] ?? $masterLang;
                break;
            }
        }

        $languageCards = [];
        foreach ($c->studioConfig->getSubtitleLanguages() as $language) {
            $id = $language['id'] ?? '';
            if ($id === '' || $id === $masterLang) {
                continue;
            }
            $entry = $translationState->getLanguageStatus($id);
            $status = $entry['status'] ?? 'pending';
            $languageCards[] = [
                'id'         => $id,
                'label'      => $language['label'] ?? $id,
                'badgeClass' => match ($status) { 'error' => 'error', 'reviewed' => 'reviewed', 'done' => 'generated', default => 'pending' },
                'badgeLabel' => match ($status) { 'error' => 'Error', 'reviewed' => 'Revisat', 'done' => 'Generat', default => 'Pendent' },
                'clickable'  => in_array($status, ['reviewed', 'done'], true),
                'canRetry'   => $status === 'error',
            ];
        }

        require $this->view('translation-hub.php');
        exit;
    }

    public function status(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        if (!$this->c->jobManager->exists()) {
            echo json_encode(['status' => 'idle']);
            exit;
        }
        echo json_encode((new TranslationJobState($this->c->jobManager))->read());
        exit;
    }

    public function retry(): never
    {
        $c = $this->c;
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        if (!$c->jobManager->exists()) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => ['No hi ha cap feina activa.']]);
            exit;
        }
        $lang = trim((string) ($_POST['lang'] ?? ''));
        if ($lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => ['Idioma no vàlid.']]);
            exit;
        }
        $job = $c->jobManager->read();
        $c->translationCoordinator()->spawn($job['subtitle_language'] ?? 'es', $lang);
        echo json_encode(['ok' => true]);
        exit;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
