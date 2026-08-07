<?php

namespace Studio;

/**
 * Single-file intake for the Polir subtítols tab. Only accepts caption files
 * (.vtt / .srt) — no audio, no transcription — and always runs the
 * revision pipeline with zero translation targets, since the output must
 * stay in the same language as the input.
 */
class ShortenIntakeHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly JobManager $jobManager,
        private readonly BackgroundJobLauncher $launcher,
        private readonly TranslationJobState $translationState,
        private readonly IntakeSourceDetector $sourceDetector = new IntakeSourceDetector(),
        private readonly SrtToVttConverter $srtConverter = new SrtToVttConverter(),
        private readonly WebVttValidator $vttValidator = new WebVttValidator(),
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
            $errors['intake_file'] = 'Pugeu un fitxer de subtítols (.vtt / .srt).';
        } elseif (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['intake_file'] = 'No s\'ha pogut pujar el fitxer.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $originalName = $upload['name'] ?? 'upload';
        if (TranscriptionIntakeFileKind::fromFilename($originalName) !== 'subtitle') {
            $errors['intake_file'] = 'Format no reconegut. Pugeu un fitxer de subtítols (.vtt / .srt).';
            return ['errors' => $errors, 'values' => $values];
        }

        $meta = [
            'job_type'          => 'shorten',
            'subtitle_language' => $values['subtitle_language'],
            'original_filename' => pathinfo($originalName, PATHINFO_FILENAME),
            'intake_mode'       => 'upload',
        ];

        try {
            if ($this->sourceDetector->isSubRip($upload['tmp_name'], $originalName)) {
                $vttContent = $this->srtConverter->convert($upload['tmp_name']);
                $vttLabel = pathinfo($originalName, PATHINFO_FILENAME) . '.vtt';
                $this->validateVttContent($vttContent, $vttLabel);
                $this->jobManager->createWithContent($meta, $vttContent);
            } else {
                $this->vttValidator->validate($upload['tmp_name'], $originalName);
                $this->jobManager->create($meta, new UploadedFile($upload['tmp_name'], $originalName));
            }
        } catch (\InvalidArgumentException $e) {
            $errors['intake_file'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        } catch (\RuntimeException $e) {
            $errors['_form'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        $this->startRevisionPipeline($values['subtitle_language']);

        return ['errors' => [], 'values' => $values, 'created' => true];
    }

    private function startRevisionPipeline(string $sourceLang): void
    {
        $revisionPath = $this->jobManager->revisionStatePath();
        file_put_contents($revisionPath, json_encode(['status' => 'pending']) . "\n");

        $this->translationState->initiate([], $sourceLang);

        $this->launcher->launchRevisionAndTranslation(
            $this->jobManager->draftVttPath(),
            $revisionPath,
            $this->jobManager->translationStatePath(),
            $sourceLang,
            dirname($this->jobManager->draftVttPath()),
            [],
        );
    }

    private function validateVttContent(string $vttContent, string $label): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'studio-shorten-vtt-');
        if ($tmpPath === false) {
            throw new \RuntimeException('No s\'ha pogut validar el fitxer de subtítols.');
        }

        try {
            if (file_put_contents($tmpPath, $vttContent) === false) {
                throw new \RuntimeException('No s\'ha pogut validar el fitxer de subtítols.');
            }
            $this->vttValidator->validate($tmpPath, $label);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }
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
