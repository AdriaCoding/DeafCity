<?php

namespace Studio;

class BulkItemProcessor
{
    /** @var callable(): array{success: bool, reason?: string} */
    private $waitForCompletion;

    /**
     * @param callable(): array{success: bool, reason?: string}|null $waitForCompletion
     */
    public function __construct(
        private readonly BulkIntakeQueue $bulkQueue,
        private readonly JobManager $jobManager,
        private readonly object $orchestrator,
        private readonly BackgroundJobLauncher $launcher,
        private readonly TranslationJobState $translationState,
        ?callable $waitForCompletion = null,
    ) {
        $this->waitForCompletion = $waitForCompletion ?? fn (): array => $this->pollUntilReady();
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
                throw new \RuntimeException('No s\'ha trobat el fitxer d\'àudio.');
            }

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
                $this->startTranslationIfNeeded($item['language']);
            }

            $wait = ($this->waitForCompletion)();
            if (!$wait['success']) {
                throw new \RuntimeException($wait['reason'] ?? 'Error en el processament.');
            }

            $enVttSource = $this->resolveEnglishVttPath($item['language']);
            if (!is_file($enVttSource)) {
                throw new \RuntimeException('No s\'ha generat el fitxer de subtítols.');
            }

            $srcVttSource = $this->jobManager->draftVttPath();
            if (!is_file($srcVttSource)) {
                throw new \RuntimeException('No s\'ha generat el fitxer de subtítols original.');
            }

            if (!is_dir($this->bulkQueue->bulkOutputDir())) {
                mkdir($this->bulkQueue->bulkOutputDir(), 0775, true);
            }

            $enDest = $this->bulkQueue->bulkOutputDir() . '/' . $item['id'] . '_EN.vtt';
            if (!copy($enVttSource, $enDest)) {
                throw new \RuntimeException('No s\'ha pogut desar el fitxer de sortida en anglès.');
            }

            $srcDest = $this->bulkQueue->bulkOutputDir() . '/' . $item['id'] . '_SRC.vtt';
            if (!copy($srcVttSource, $srcDest)) {
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

    private function startTranslationIfNeeded(string $sourceLang): void
    {
        if ($sourceLang === 'en') {
            $this->translationState->initiate([]);
            return;
        }

        $this->translationState->initiate(['en']);
        $this->launcher->launchTranslation(
            $this->jobManager->draftVttPath(),
            $this->jobManager->translationStatePath(),
            $sourceLang,
            dirname($this->jobManager->draftVttPath()),
            ['en'],
        );
    }

    private function resolveEnglishVttPath(string $sourceLang): string
    {
        if ($sourceLang === 'en') {
            return $this->jobManager->draftVttPath();
        }

        return $this->jobManager->draftVttPathForLang('en');
    }

    /** @return array{success: bool, reason?: string} */
    private function pollUntilReady(): array
    {
        $status = new TranscriptionPipelineStatus($this->jobManager);
        $deadline = time() + 3600;

        while (time() < $deadline) {
            $state = $status->getState();
            if ($state === 'download_ready') {
                return ['success' => true];
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
