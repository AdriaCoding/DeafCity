<?php

namespace Studio;

/**
 * Bulk-queue worker for the Polir subtítols tab. Only ever processes caption
 * files (no audio/transcription) and never translates — the revision
 * pipeline runs with zero target languages, so the output stays in the
 * item's own source language.
 *
 * Reuses BulkIntakeQueue as-is: since there is no separate translated
 * output here, the single revised file is recorded under both the
 * enVttPath and srcVttPath slots of markDone()/doneEntries() (they're
 * identical for a shorten item) rather than forking the queue's storage
 * format.
 */
class ShortenBulkItemProcessor
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
        private readonly BackgroundJobLauncher $launcher,
        private readonly TranslationJobState $translationState,
        ?callable $waitForCompletion = null,
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

            $this->processItem($item);

            $wait = ($this->waitForCompletion)();
            if (!$wait['success']) {
                throw new \RuntimeException($wait['reason'] ?? 'Error en el processament.');
            }

            $vttSource = $this->jobManager->draftVttPath();
            if (!is_file($vttSource)) {
                throw new \RuntimeException('No s\'ha generat el fitxer de subtítols.');
            }

            if (!is_dir($this->bulkQueue->bulkOutputDir())) {
                mkdir($this->bulkQueue->bulkOutputDir(), 0775, true);
            }

            $dest = $this->bulkQueue->bulkOutputDir() . '/' . $item['id'] . '.vtt';
            if (!copy($vttSource, $dest)) {
                throw new \RuntimeException('No s\'ha pogut desar el fitxer de sortida.');
            }

            $this->jobManager->cancel();
            $this->bulkQueue->markDone($item['id'], $dest, $dest);
        } catch (\Throwable $e) {
            $this->jobManager->cancel();
            $this->bulkQueue->markFailed($item['id'], $e->getMessage());
        }

        return true;
    }

    /** @param array<string, mixed> $item */
    private function processItem(array $item): void
    {
        $ext = pathinfo($item['tmpAudioPath'], PATHINFO_EXTENSION);
        $originalName = $item['originalFilename'] . ($ext !== '' ? ".$ext" : '');
        $meta = [
            'job_type'          => 'shorten',
            'subtitle_language' => $item['language'],
            'original_filename' => $item['originalFilename'],
            'intake_mode'       => 'upload',
        ];

        try {
            $this->jobManager->createWithContent(
                $meta,
                $this->normalizer->normalize(
                    $item['tmpAudioPath'],
                    $originalName,
                    CaptionIntakeNormalizer::FORMAT_VTT,
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


    /** @return array{success: bool, reason?: string} */
    private function pollUntilReady(): array
    {
        $status = new TranscriptionPipelineStatus($this->jobManager, alwaysSkipTranslation: true);
        $deadline = time() + $this->pollTimeoutSeconds;

        while (time() < $deadline) {
            $state = $status->getState();
            if ($state === 'download_ready') {
                return ['success' => true];
            }
            if ($state === 'revision_error') {
                return ['success' => false, 'reason' => 'Error en la revisió.'];
            }

            sleep(2);
        }

        return ['success' => false, 'reason' => 'Temps d\'espera esgotat.'];
    }
}
