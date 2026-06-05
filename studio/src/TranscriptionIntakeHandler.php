<?php

namespace Studio;

class TranscriptionIntakeHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly JobManager $jobManager,
        private readonly ?object $orchestrator = null,
        private readonly ?BackgroundJobLauncher $launcher = null,
        private readonly ?TranslationJobState $translationState = null,
    ) {}

    /**
     * @return array{errors: array<string, string>, values: array<string, string>, created?: bool}
     */
    public function handlePost(array $post, array $files): array
    {
        $values = ['subtitle_language' => trim($post['subtitle_language'] ?? '')];
        $errors = [];

        if ($this->jobManager->exists()) {
            $errors['_form'] = 'Ja hi ha una feina en curs.';
            return ['errors' => $errors, 'values' => $values];
        }

        if ($values['subtitle_language'] === '' || !$this->isValidLanguage($values['subtitle_language'])) {
            $errors['subtitle_language'] = 'Seleccioneu una llengua.';
        }

        $upload = $files['intake_file'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['intake_file'] = 'Pugeu un fitxer d\'àudio de l\'intèrpret.';
        } elseif (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['intake_file'] = 'No s\'ha pogut pujar el fitxer.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $originalName = $upload['name'] ?? 'audio';
        $meta = [
            'job_type'          => 'transcription',
            'subtitle_language' => $values['subtitle_language'],
            'original_filename' => pathinfo($originalName, PATHINFO_FILENAME),
            'intake_mode'       => 'generate',
        ];

        try {
            $this->jobManager->createWithAudio($meta, new UploadedFile($upload['tmp_name'], $originalName));
        } catch (\RuntimeException $e) {
            $errors['_form'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        $outcome = $this->orchestrator->run();

        if ($outcome['result'] === 'pipeline_transcribed') {
            $this->translationState->initiate(['en']);
            $this->launcher->launchTranslation(
                $this->jobManager->draftVttPath(),
                $this->jobManager->translationStatePath(),
                $values['subtitle_language'],
                dirname($this->jobManager->draftVttPath()),
                ['en'],
            );
            return ['errors' => [], 'values' => $values, 'created' => true];
        }

        if ($outcome['result'] === 'loading') {
            return ['errors' => [], 'values' => $values, 'created' => true];
        }

        // error — job was destroyed by orchestrator
        $errors['_form'] = $outcome['message'] ?? 'Error en la generació de subtítols.';
        return ['errors' => $errors, 'values' => $values];
    }

    private function isValidLanguage(string $id): bool
    {
        foreach ($this->studioConfig->getSubtitleLanguages() as $lang) {
            if (($lang['id'] ?? '') === $id) {
                return true;
            }
        }
        return false;
    }
}
