<?php

namespace Studio\Actions;

use Studio\Container;
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

        if (!$c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        $job = $c->jobManager->read();
        if (($job['job_type'] ?? '') !== 'transcription') {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        $pipelineStatus = (new TranscriptionPipelineStatus($c->jobManager))->getState();
        $originalFilename = $job['original_filename'] ?? 'transcripció';
        $englishTranslationSkipped = ($job['subtitle_language'] ?? '') === 'en';
        $sourceLanguageLabel = $this->languageLabel((string) ($job['subtitle_language'] ?? ''));
        require $this->view('transcription-loading.php');
        exit;
    }

    private function languageLabel(string $id): string
    {
        foreach ($this->c->studioConfig->getSubtitleLanguages() as $lang) {
            if (($lang['id'] ?? '') === $id) {
                return (string) ($lang['label'] ?? $id);
            }
        }

        return $id;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
