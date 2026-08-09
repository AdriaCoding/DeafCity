<?php

namespace Studio;

class BulkItemProcessor
{
    /** @var callable(): array{success: bool, reason?: string} */
    private $waitForCompletion;

    private readonly CaptionIntakeNormalizer $normalizer;

    /**
     * @param callable(): array{success: bool, reason?: string}|null $waitForCompletion
     */
    public function __construct(
        private readonly BulkIntakeQueue $bulkQueue,
        private readonly JobManager $jobManager,
        private readonly object $orchestrator,
        private readonly BackgroundJobLauncher $launcher,
        private readonly TranslationJobState $translationState,
        private readonly StudioConfig $studioConfig,
        ?callable $waitForCompletion = null,
        private readonly ?GeminiReviser $reviser = null,
        ?CaptionIntakeNormalizer $normalizer = null,
        private readonly int $pollTimeoutSeconds = 3600,
    ) {
        $this->waitForCompletion = $waitForCompletion ?? fn (): array => $this->pollUntilReady();
        $this->normalizer = $normalizer ?? new CaptionIntakeNormalizer();
    }

    public function processNext(): bool
    {
        $item = $this->bulkQueue->current();
        if ($item === null) {
            return false;
        }

        $this->bulkQueue->markProcessing($item['id']);

        try {
            if (!is_file($item['tmpAudioPath'])) {
                throw new \RuntimeException('No s\'ha trobat el fitxer d\'entrada.');
            }

            $kind = $item['kind'] ?? 'audio';
            if ($kind === 'subtitle') {
                $this->processSubtitleItem($item);
            } else {
                $this->processAudioItem($item);
            }

            $wait = ($this->waitForCompletion)();
            if (!$wait['success']) {
                throw new \RuntimeException($wait['reason'] ?? 'Error en el processament.');
            }

            $enSource = $this->resolveEnglishDraftPath($item['language']);
            if (!is_file($enSource)) {
                throw new \RuntimeException('No s\'ha generat el fitxer de subtítols.');
            }

            $srcSource = $this->jobManager->draftPath();
            if (!is_file($srcSource)) {
                throw new \RuntimeException('No s\'ha generat el fitxer de subtítols original.');
            }

            if (!is_dir($this->bulkQueue->bulkOutputDir())) {
                mkdir($this->bulkQueue->bulkOutputDir(), 0775, true);
            }

            $enDest = $this->bulkQueue->bulkOutputDir() . '/' . $item['id'] . '_EN.srt';
            if (!copy($enSource, $enDest)) {
                throw new \RuntimeException('No s\'ha pogut desar el fitxer de sortida en anglès.');
            }

            $srcDest = $this->bulkQueue->bulkOutputDir() . '/' . $item['id'] . '_SRC.srt';
            if (!copy($srcSource, $srcDest)) {
                throw new \RuntimeException('No s\'ha pogut desar el fitxer de sortida original.');
            }

            $this->jobManager->cancel();
            $this->bulkQueue->markDone($item['id'], $enDest, $srcDest);
        } catch (\Throwable $e) {
            $this->jobManager->cancel();
            $this->bulkQueue->markFailed($item['id'], $e->getMessage());
        }

        return true;
    }

    /** @param array<string, mixed> $item */
    private function processAudioItem(array $item): void
    {
        $ext = pathinfo($item['tmpAudioPath'], PATHINFO_EXTENSION);
        $originalName = $item['originalFilename'] . ($ext !== '' ? ".$ext" : '');

        $this->jobManager->createWithAudio(
            [
                'job_type' => 'transcription',
                'subtitle_language' => $item['language'],
                'original_filename' => $item['originalFilename'],
                'intake_mode' => 'generate',
            ],
            new UploadedFile($item['tmpAudioPath'], $originalName),
        );

        $outcome = $this->orchestrator->run();

        if ($outcome['result'] === 'error') {
            throw new \RuntimeException($outcome['message'] ?? 'Error en la transcripció.');
        }

        if ($outcome['result'] === 'pipeline_transcribed') {
            if ($this->reviser !== null) {
                $draftPath = $this->jobManager->draftPath();
                $dialectId = $item['language'];
                $baseLang = $this->studioConfig->getBaseLanguageFor($dialectId);
                $dialectName = $dialectId !== $baseLang ? $this->studioConfig->languageLabelFor($dialectId) : '';
                $revised = $this->reviser->revise(
                    (string) file_get_contents($draftPath),
                    $dialectId,
                    $dialectName,
                );
                file_put_contents($draftPath, $revised);
            }
            $this->startTranslationIfNeeded($item['language']);
        }
    }

    /** @param array<string, mixed> $item */
    private function processSubtitleItem(array $item): void
    {
        $ext = pathinfo($item['tmpAudioPath'], PATHINFO_EXTENSION);
        $originalName = $item['originalFilename'] . ($ext !== '' ? ".$ext" : '');
        $meta = [
            'job_type' => 'transcription',
            'subtitle_language' => $item['language'],
            'original_filename' => $item['originalFilename'],
            'intake_mode' => 'upload',
        ];

        try {
            $this->jobManager->createWithContent(
                $meta,
                $this->normalizer->normalize(
                    $item['tmpAudioPath'],
                    $originalName,
                ),
            );
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }

        $this->startRevisionPipeline($item['language']);
    }

    private function startRevisionPipeline(string $sourceLang): void
    {
        $revisionPath = $this->jobManager->revisionStatePath();
        file_put_contents($revisionPath, json_encode(['status' => 'pending']) . "\n");

        $baseLang = $this->studioConfig->getBaseLanguageFor($sourceLang);
        $dialectName = $sourceLang !== $baseLang ? $this->studioConfig->languageLabelFor($sourceLang) : '';

        if ($baseLang === 'en') {
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
            $sourceLang,
            dirname($this->jobManager->draftPath()),
            $targetLangs,
            $baseLang,
            $dialectName,
        );
    }


    private function startTranslationIfNeeded(string $sourceLang): void
    {
        $baseLang = $this->studioConfig->getBaseLanguageFor($sourceLang);

        if ($baseLang === 'en') {
            $this->translationState->initiate([], $baseLang);
            return;
        }

        $this->translationState->initiate(['en'], $baseLang);
        $this->launcher->launchTranslation(
            $this->jobManager->draftPath(),
            $this->jobManager->translationStatePath(),
            $baseLang,
            dirname($this->jobManager->draftPath()),
            ['en'],
        );
    }

    private function resolveEnglishDraftPath(string $sourceLang): string
    {
        if ($this->studioConfig->getBaseLanguageFor($sourceLang) === 'en') {
            return $this->jobManager->draftPath();
        }

        return $this->jobManager->draftPathForLang('en');
    }

    /** @return array{success: bool, reason?: string} */
    private function pollUntilReady(): array
    {
        $status = new TranscriptionPipelineStatus($this->jobManager, $this->studioConfig);
        $deadline = time() + $this->pollTimeoutSeconds;

        while (time() < $deadline) {
            $state = $status->getState();
            if ($state === 'download_ready') {
                return ['success' => true];
            }
            if ($state === 'revision_error') {
                return ['success' => false, 'reason' => 'Error en la revisió.'];
            }
            if ($state === 'translation_error') {
                return ['success' => false, 'reason' => 'Error en la traducció a l\'anglès.'];
            }

            $raw = $this->jobManager->readTranscriptionStatus();
            if ($raw !== null) {
                $data = json_decode($raw, true);
                if (($data['status'] ?? '') === 'error') {
                    return ['success' => false, 'reason' => $data['message'] ?? 'Error en la transcripció.'];
                }
            }

            sleep(2);
        }

        return ['success' => false, 'reason' => 'Temps d\'espera esgotat.'];
    }
}
