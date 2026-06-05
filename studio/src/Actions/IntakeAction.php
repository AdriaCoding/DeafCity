<?php

namespace Studio\Actions;

use Studio\Container;
use Studio\IntakeHandler;
use Studio\TranscriptionIntakeHandler;
use Studio\TranslationJobState;
use Studio\VimeoIdParser;
use Studio\WebVttValidator;

class IntakeAction
{
    public function __construct(private Container $c) {}

    public function cancel(): never
    {
        $this->c->jobManager->cancel();
        header('Location: ' . $this->c->baseUrl);
        exit;
    }

    public function handle(): never
    {
        $c = $this->c;
        if ($c->jobManager->exists() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        $handler = new IntakeHandler(
            new VimeoIdParser(),
            $c->vimeoClient(),
            $c->studioConfig,
            $c->jobManager,
            new WebVttValidator(),
        );

        $errors = [];
        $values = ['vimeo_input' => '', 'sign_language' => '', 'edition' => '', 'subtitle_language' => '', 'intake_mode' => 'upload'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $handler->handlePost($_POST, $_FILES);
            $errors = $result['errors'];
            $values = $result['values'];
            if (!empty($result['created'])) {
                if (($values['intake_mode'] ?? 'upload') === 'generate') {
                    $outcome = $c->transcriptionOrchestrator()->run();
                    if ($outcome['result'] === 'editor') {
                        header('Location: ?action=subtitle-editor');
                        exit;
                    }
                    if ($outcome['result'] === 'loading') {
                        header('Location: ' . $c->baseUrl);
                        exit;
                    }
                    $errors['_form'] = $outcome['message'] ?? 'Error en la generació de subtítols';
                } else {
                    header('Location: ' . $c->baseUrl);
                    exit;
                }
            }
        }

        $signLanguages = $c->studioConfig->getSignLanguages();
        $editions = $c->studioConfig->getEditions();
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        require $this->view('intake.php');
        exit;
    }

    public function handleTranscription(): never
    {
        $c = $this->c;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($c->jobManager->exists()) {
                header('Location: ' . $c->baseUrl);
                exit;
            }
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            $errors = [];
            $values = ['subtitle_language' => ''];
            require $this->view('transcription-intake.php');
            exit;
        }

        $handler = new TranscriptionIntakeHandler(
            studioConfig: $c->studioConfig,
            jobManager: $c->jobManager,
            orchestrator: $c->transcriptionOrchestrator('en'),
            launcher: $c->launcher,
            translationState: new TranslationJobState($c->jobManager),
        );
        $result = $handler->handlePost($_POST, $_FILES);
        if (!empty($result['created'])) {
            header('Location: ' . $c->baseUrl);
            exit;
        }
        $errors = $result['errors'];
        $values = $result['values'];
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
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

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
