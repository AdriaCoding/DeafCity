<?php

namespace Studio\Actions;

use Studio\Actions\CatalogAction;
use Studio\Container;
use Studio\PipelineSteps;
use Studio\StudioHeader;
use Studio\TranscriptionPipelineStatus;

class ShellAction
{
    public function __construct(private Container $c) {}

    public function handle(?string $action): never
    {
        $c = $this->c;

        if ($action === 'resume-job') {
            $this->renderResumeJob();
        }

        if ($action !== null && $c->jobManager->exists()) {
            $job = $c->jobManager->read();
            if (($job['step'] ?? '') === $action) {
                $stepLabel = PipelineSteps::label($action);
                extract($c->headerContext(null, StudioHeader::pipelineStepFromAction($action)));
                require $this->view('step-placeholder.php');
                exit;
            }
        }

        if ($action !== null) {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        $syncStatusPath = $c->dataDir . '/sync-status.json';
        $raw = is_file($syncStatusPath) ? @file_get_contents($syncStatusPath) : false;
        $syncStatus = $raw ? json_decode($raw, true) : null;
        $isSyncing = ($syncStatus['status'] ?? '') === 'running';

        (new CatalogAction($c))->renderContinguts(['syncStatus' => $syncStatus, 'isSyncing' => $isSyncing]);
    }

    private function renderResumeJob(): never
    {
        $c = $this->c;
        extract($c->headerContext());

        $hasActiveJob = $c->jobManager->exists();
        $isTranscriptionJob = $hasActiveJob && ($c->jobManager->read()['job_type'] ?? '') === 'transcription';

        if ($isTranscriptionJob) {
            $job = $c->jobManager->read();
            $pipelineStatus = (new TranscriptionPipelineStatus($c->jobManager))->getState();
            $originalFilename = $job['original_filename'] ?? 'transcripció';
            $subtitleLanguageLabel = $job['subtitle_language'] ?? '';
            foreach ($c->studioConfig->getSubtitleLanguages() as $lang) {
                if (($lang['id'] ?? '') === ($job['subtitle_language'] ?? '')) {
                    $subtitleLanguageLabel = $lang['label'] ?? $subtitleLanguageLabel;
                    break;
                }
            }
            $englishTranslationSkipped = ($job['subtitle_language'] ?? '') === 'en';
            require $this->view('transcription-loading.php');
            exit;
        }

        $job = [];
        $editionLabel = '';
        $stepLabel = '';
        $resumeUrl = './';
        $isTranscribing = false;
        $transcriptionError = null;
        $isLocalFallback = false;

        if ($hasActiveJob) {
            $job = $c->jobManager->read();
            $step = $job['step'] ?? 'translation';
            if ($step === 'subtitle-editor') {
                $c->jobManager->update(['step' => 'translation']);
                $step = 'translation';
            }
            $stepLabel = PipelineSteps::label($step);
            $resumeUrl = PipelineSteps::route($step);
            $editionLabel = $job['edition'];
            foreach ($c->studioConfig->getEditions() as $edition) {
                if ($edition['id'] === $job['edition']) {
                    $editionLabel = $edition['label'];
                    break;
                }
            }
            if (($job['intake_mode'] ?? 'upload') === 'generate' && !$c->jobManager->hasDraftVtt()) {
                $isTranscribing = true;
                $isLocalFallback = str_starts_with($job['transcription_engine'] ?? '', 'local:');
                $statusJson = $c->jobManager->readTranscriptionStatus();
                if ($statusJson !== null) {
                    $statusData = json_decode($statusJson, true);
                    if (($statusData['status'] ?? '') === 'error') {
                        $transcriptionError = $statusData['message'] ?? 'Error en la generació de subtítols';
                    }
                }
            }
        } else {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        require $this->view('shell.php');
        exit;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
