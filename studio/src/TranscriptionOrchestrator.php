<?php

namespace Studio;

/**
 * Decides how a just-created Subtitle-Generation Job is transcribed.
 *
 * Called synchronously on the Intake POST after JobManager::createWithAudio().
 * It transcodes the Interpreter audio, tries the Groq cloud engine, and routes
 * per the fallback matrix (ADR-0006):
 *
 *   success            ⇒ write draft.vtt, stamp transcription_engine, return 'editor'
 *   transport / empty  ⇒ spawn the async local engine, return 'loading'
 *   auth / bad_input   ⇒ destroy the Job, return 'error' with a Catalan message
 *   blank GROQ_API_KEY ⇒ skip Groq entirely, go straight to local
 *
 * Performs no superglobal/header I/O — index.php maps the returned value to a
 * redirect or re-render. The logger and clock are injected seams.
 *
 * @phpstan-type Result array{result: 'editor'|'pipeline_transcribed'|'loading'|'error', message?: string}
 */
class TranscriptionOrchestrator
{
    private JobManager $jobManager;
    private GroqTranscriber $groqTranscriber;
    private AudioPreprocessor $audioPreprocessor;
    private BackgroundJobLauncher $launcher;
    private VttParser $vttParser;
    private string $groqApiKey;
    private string $groqModel;
    private string $localModel;
    private string $pipelineTargetLang;
    /** @var callable(string): void */
    private $logger;
    /** @var callable(): float */
    private $clock;

    /**
     * @param callable(string): void|null $logger  appends one structured line
     * @param callable(): float|null $clock         monotonic seconds, for wall-time
     * @param string $pipelineTargetLang  non-empty enables pipeline mode: skips editor, chains translation
     */
    public function __construct(
        JobManager $jobManager,
        GroqTranscriber $groqTranscriber,
        AudioPreprocessor $audioPreprocessor,
        BackgroundJobLauncher $launcher,
        VttParser $vttParser,
        string $groqApiKey,
        string $groqModel,
        string $localModel,
        ?callable $logger = null,
        ?callable $clock = null,
        string $pipelineTargetLang = '',
    ) {
        $this->jobManager = $jobManager;
        $this->groqTranscriber = $groqTranscriber;
        $this->audioPreprocessor = $audioPreprocessor;
        $this->launcher = $launcher;
        $this->vttParser = $vttParser;
        $this->groqApiKey = $groqApiKey;
        $this->groqModel = $groqModel;
        $this->localModel = $localModel;
        $this->pipelineTargetLang = $pipelineTargetLang;
        $this->logger = $logger ?? static fn(string $line) => null;
        $this->clock = $clock ?? static fn(): float => microtime(true);
    }

    /**
     * @return Result
     */
    public function run(): array
    {
        $job = $this->jobManager->read();
        $language = $job['subtitle_language'] ?? 'es';
        $audioPath = $this->jobManager->interpreterAudioPath();

        // Blank key ⇒ no egress, go straight to local.
        if ($this->groqApiKey === '') {
            return $this->fallbackToLocal($language, 'blank_key', 0.0);
        }

        $start = ($this->clock)();
        $flacPath = null;

        try {
            $flacPath = $this->audioPreprocessor->toGroqUpload($audioPath, dirname($audioPath));
            $cues = $this->groqTranscriber->transcribe($flacPath, $this->groqModel, $language);
        } catch (GroqTranscriptionException $e) {
            $this->deleteFlac($flacPath);
            $wall = ($this->clock)() - $start;
            return $this->routeFailure($e, $language, $wall);
        } catch (\RuntimeException $e) {
            // ffmpeg preprocessing failed — local engine would read the same
            // bytes and reject them too. Fail loud.
            $this->deleteFlac($flacPath);
            return $this->failLoud('bad_input', $language, ($this->clock)() - $start);
        }

        $this->deleteFlac($flacPath);
        $wall = ($this->clock)() - $start;

        // Success — write the draft Caption file and stamp provenance.
        $vtt = $this->vttParser->write([
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'cues' => $cues,
        ]);
        $this->jobManager->writeDraftVtt($vtt);

        $engine = 'groq:' . $this->groqModel;
        $this->jobManager->update(['transcription_engine' => $engine]);

        $this->log($engine, $this->groqModel, $language, $wall, null);

        return $this->pipelineTargetLang !== ''
            ? ['result' => 'pipeline_transcribed']
            : ['result' => 'editor'];
    }

    /**
     * @return Result
     */
    private function routeFailure(GroqTranscriptionException $e, string $language, float $wall): array
    {
        return match ($e->category()) {
            GroqTranscriptionException::CATEGORY_TRANSPORT,
            GroqTranscriptionException::CATEGORY_EMPTY
                => $this->fallbackToLocal($language, $e->category(), $wall),
            GroqTranscriptionException::CATEGORY_AUTH,
            GroqTranscriptionException::CATEGORY_BAD_INPUT
                => $this->failLoud($e->category(), $language, $wall),
            default => $this->fallbackToLocal($language, 'transport', $wall),
        };
    }

    /**
     * @return Result
     */
    private function fallbackToLocal(string $language, string $category, float $wall): array
    {
        // Reset the status so the loading screen polls a clean 'pending'.
        file_put_contents(
            $this->jobManager->transcriptionStatusPath(),
            json_encode(['status' => 'pending'])
        );

        // Stamp provenance now so the loading screen knows the local engine is
        // in use and can show the "fast engine unavailable" notice.
        $this->jobManager->update(['transcription_engine' => 'local:' . $this->localModel]);

        if ($this->pipelineTargetLang !== '') {
            $this->launcher->launchTranscriptionPipeline(
                audioPath:            $this->jobManager->interpreterAudioPath(),
                vttOutputPath:        $this->jobManager->draftVttPath(),
                statusPath:           $this->jobManager->transcriptionStatusPath(),
                translationStatePath: $this->jobManager->translationStatePath(),
                jobDir:               dirname($this->jobManager->draftVttPath()),
                sourceLang:           $language,
                targetLang:           $this->pipelineTargetLang,
                model:                $this->localModel,
            );
        } else {
            $this->launcher->launchTranscription(
                $this->jobManager->interpreterAudioPath(),
                $this->jobManager->draftVttPath(),
                $this->jobManager->transcriptionStatusPath(),
                $language,
                $this->localModel,
            );
        }

        $this->log('local:' . $this->localModel, $this->localModel, $language, $wall, $category);

        return ['result' => 'loading'];
    }

    /**
     * @return Result
     */
    private function failLoud(string $category, string $language, float $wall): array
    {
        $this->log('groq', $this->groqModel, $language, $wall, $category);
        $this->jobManager->cancel();

        $message = $category === GroqTranscriptionException::CATEGORY_AUTH
            ? 'No s\'ha pogut generar els subtítols: el servei de transcripció no està configurat correctament. Aviseu un administrador.'
            : 'El format de l\'àudio no es reconeix. Pugeu un fitxer d\'àudio vàlid.';

        return ['result' => 'error', 'message' => $message];
    }

    private function deleteFlac(?string $flacPath): void
    {
        if ($flacPath !== null && is_file($flacPath)) {
            unlink($flacPath);
        }
    }

    private function log(
        string $engine,
        string $model,
        string $language,
        float $wall,
        ?string $fallbackCategory,
    ): void {
        $fields = [
            'engine=' . $engine,
            'model=' . $model,
            'lang=' . $language,
            'wall=' . sprintf('%.3f', $wall),
        ];
        if ($fallbackCategory !== null) {
            $fields[] = 'fallback=' . $fallbackCategory;
        }
        ($this->logger)('transcription ' . implode(' ', $fields));
    }
}
