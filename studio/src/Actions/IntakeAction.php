<?php

namespace Studio\Actions;

use Studio\BulkIntakeHandler;
use Studio\Container;
use Studio\StudioHeader;
use Studio\TranscriptionIntakeHandler;
use Studio\TranslationJobState;

class IntakeAction
{
    public function __construct(private Container $c) {}

    public function cancel(): never
    {
        $this->c->jobManager->cancel();
        header('Location: ' . $this->c->baseUrl);
        exit;
    }

    public function handleTranscription(): never
    {
        $c = $this->c;
        $bulkQueue = $c->bulkIntakeQueue();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($bulkQueue->exists()) {
                header('Location: ?action=bulk-progress');
                exit;
            }
            if ($c->jobManager->exists()) {
                header('Location: ?action=resume-job');
                exit;
            }
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            $errors = [];
            $values = ['subtitle_language' => ''];
            extract($c->headerContext(StudioHeader::NAV_TRANSCRIPTION_INTAKE));
            require $this->view('transcription-intake.php');
            exit;
        }

        if ($bulkQueue->exists()) {
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            $errors = ['_form' => 'Ja hi ha una transcripció en massa en curs.'];
            $values = ['subtitle_language' => ''];
            extract($c->headerContext(StudioHeader::NAV_TRANSCRIPTION_INTAKE));
            require $this->view('transcription-intake.php');
            exit;
        }

        if ($this->isBulkUpload($_FILES)) {
            $handler = new BulkIntakeHandler(
                studioConfig: $c->studioConfig,
                jobManager: $c->jobManager,
                bulkQueue: $bulkQueue,
                launcher: $c->launcher,
                dataDir: $c->dataDir,
            );
            $result = $handler->handlePost($_POST, $_FILES);
            if (!empty($result['created'])) {
                header('Location: ?action=bulk-progress');
                exit;
            }
            $errors = $result['errors'];
            $values = $result['values'];
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            extract($c->headerContext(StudioHeader::NAV_TRANSCRIPTION_INTAKE));
            require $this->view('transcription-intake.php');
            exit;
        }

        $files = $this->normalizeSingleUpload($_FILES);
        $handler = new TranscriptionIntakeHandler(
            studioConfig: $c->studioConfig,
            jobManager: $c->jobManager,
            orchestrator: $c->transcriptionOrchestrator('en'),
            launcher: $c->launcher,
            translationState: new TranslationJobState($c->jobManager),
        );
        $result = $handler->handlePost($_POST, $files);
        if (!empty($result['created'])) {
            header('Location: ?action=resume-job');
            exit;
        }
        $errors = $result['errors'];
        $values = $result['values'];
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        extract($c->headerContext(StudioHeader::NAV_TRANSCRIPTION_INTAKE));
        require $this->view('transcription-intake.php');
        exit;
    }

    public function transcriptionStatus(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        echo $this->c->jobManager->readTranscriptionStatus() ?? json_encode(['status' => 'pending']);
        exit;
    }

    public function jobTranslationStatus(): never
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

    public function jobTranslationRetry(): never
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
        $masterLang = $job['subtitle_language'] ?? 'es';
        $state = new TranslationJobState($c->jobManager);
        $state->resetLanguage($lang);
        $c->launcher->launchTranslation(
            $c->jobManager->draftPath(),
            $c->jobManager->translationStatePath(),
            $masterLang,
            dirname($c->jobManager->draftPath()),
            [$lang],
        );
        echo json_encode(['ok' => true]);
        exit;
    }

    private function isBulkUpload(array $files): bool
    {
        $upload = $files['intake_file'] ?? null;
        if (!$upload || !is_array($upload['name'] ?? null)) {
            return false;
        }

        return count($upload['name']) >= 2;
    }

    private function normalizeSingleUpload(array $files): array
    {
        $upload = $files['intake_file'] ?? null;
        if (!$upload || !is_array($upload['name'] ?? null)) {
            return $files;
        }

        if (count($upload['name']) === 1) {
            $files['intake_file'] = [
                'name' => $upload['name'][0],
                'type' => $upload['type'][0] ?? '',
                'tmp_name' => $upload['tmp_name'][0] ?? '',
                'error' => $upload['error'][0] ?? UPLOAD_ERR_NO_FILE,
                'size' => $upload['size'][0] ?? 0,
            ];
        }

        return $files;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
