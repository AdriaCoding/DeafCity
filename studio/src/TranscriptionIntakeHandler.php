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
        private readonly CaptionIntakeNormalizer $normalizer = new CaptionIntakeNormalizer(),
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
            $errors['intake_file'] = 'Pugeu un fitxer d\'àudio o de subtítols (.vtt / .srt).';
        } elseif (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['intake_file'] = 'No s\'ha pogut pujar el fitxer.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $originalName = $upload['name'] ?? 'upload';
        $fileKind = TranscriptionIntakeFileKind::fromFilename($originalName);
        if ($fileKind === null) {
            $errors['intake_file'] = 'Format no reconegut. Pugeu un fitxer d\'àudio o subtítols (.vtt / .srt).';
            return ['errors' => $errors, 'values' => $values];
        }

        if ($fileKind === 'subtitle') {
            return $this->handleSubtitleUpload($upload, $originalName, $values);
        }

        return $this->handleAudioUpload($upload, $originalName, $values);
    }

    /**
     * @param array<string, mixed> $upload
     * @param array{subtitle_language: string} $values
     * @return array{errors: array<string, string>, values: array<string, string>, created?: bool}
     */
    private function handleAudioUpload(array $upload, string $originalName, array $values): array
    {
        $errors = [];
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
            return $this->startRevisionPipeline($values);
        }

        if ($outcome['result'] === 'loading') {
            return ['errors' => [], 'values' => $values, 'created' => true];
        }

        $errors['_form'] = $outcome['message'] ?? 'Error en la generació de subtítols.';
        return ['errors' => $errors, 'values' => $values];
    }

    /**
     * @param array<string, mixed> $upload
     * @param array{subtitle_language: string} $values
     * @return array{errors: array<string, string>, values: array<string, string>, created?: bool}
     */
    private function handleSubtitleUpload(array $upload, string $originalName, array $values): array
    {
        $errors = [];
        $meta = [
            'job_type'          => 'transcription',
            'subtitle_language' => $values['subtitle_language'],
            'original_filename' => pathinfo($originalName, PATHINFO_FILENAME),
            'intake_mode'       => 'upload',
        ];

        try {
            $this->jobManager->createWithContent(
                $meta,
                $this->normalizer->normalize(
                    $upload['tmp_name'],
                    $originalName,
                ),
            );
        } catch (\InvalidArgumentException $e) {
            $errors['intake_file'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        } catch (\RuntimeException $e) {
            $errors['_form'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        return $this->startRevisionPipeline($values);
    }

    /**
     * @param array{subtitle_language: string} $values
     * @return array{errors: array<string, string>, values: array<string, string>, created: true}
     */
    private function startRevisionPipeline(array $values): array
    {
        $revisionPath = $this->jobManager->revisionStatePath();
        file_put_contents($revisionPath, json_encode(['status' => 'pending']) . "\n");

        $dialectId = $values['subtitle_language'];
        $baseLang = $this->studioConfig->getBaseLanguageFor($dialectId);
        $dialectName = $dialectId !== $baseLang ? $this->studioConfig->languageLabelFor($dialectId) : '';

        if ($this->shouldSkipEnglishTranslation($dialectId)) {
            $this->translationState->initiate([], $baseLang);
            $targetLangs = [];
        } else {
            $this->translationState->initiate(['en'], $baseLang);
            $targetLangs = ['en'];
        }

        $this->launcher->launchRevisionAndTranslation(
            $this->jobManager->draftPath(),
            $revisionPath,
            $this->jobManager->translationStatePath(),
            $dialectId,
            dirname($this->jobManager->draftPath()),
            $targetLangs,
            $baseLang,
            $dialectName,
        );

        return ['errors' => [], 'values' => $values, 'created' => true];
    }


    private function isValidLanguage(string $id): bool
    {
        return $this->studioConfig->isValidInputLanguage($id);
    }

    private function shouldSkipEnglishTranslation(string $sourceLang): bool
    {
        return $this->studioConfig->getBaseLanguageFor($sourceLang) === 'en';
    }
}
