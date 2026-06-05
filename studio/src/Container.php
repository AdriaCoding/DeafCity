<?php

namespace Studio;

class Container
{
    private ?VimeoClient $vimeoClient = null;
    private ?CatalogEditor $catalogEditor = null;
    private ?TranslationCoordinator $translationCoordinator = null;

    public function __construct(
        public readonly string $dataDir,
        public readonly string $baseUrl,
        public readonly JobManager $jobManager,
        public readonly StudioConfig $studioConfig,
        public readonly BackgroundJobLauncher $launcher,
    ) {}

    public function vimeoClient(): VimeoClient
    {
        return $this->vimeoClient ??= new VimeoClient(
            VIMEO_CLIENT_ID,
            VIMEO_CLIENT_SECRET,
            VIMEO_ACCESS_TOKEN,
        );
    }

    public function catalogEditor(): CatalogEditor
    {
        return $this->catalogEditor ??= new CatalogEditor($this->dataDir . '/catalog.json');
    }

    public function translationCoordinator(): TranslationCoordinator
    {
        return $this->translationCoordinator ??= new TranslationCoordinator(
            $this->jobManager,
            $this->studioConfig,
            $this->launcher,
        );
    }

    public function transcriptionOrchestrator(string $pipelineTargetLang = ''): TranscriptionOrchestrator
    {
        return new TranscriptionOrchestrator(
            jobManager: $this->jobManager,
            groqTranscriber: new GroqTranscriber(
                GROQ_API_KEY,
                GROQ_BASE_URL,
                GROQ_TIMEOUT_SECONDS,
            ),
            audioPreprocessor: new AudioPreprocessor(),
            launcher: $this->launcher,
            vttParser: new VttParser(),
            groqApiKey: GROQ_API_KEY,
            groqModel: GROQ_TRANSCRIBE_MODEL,
            localModel: STUDIO_LOCAL_TRANSCRIBE_MODEL,
            logger: $this->logger(),
            pipelineTargetLang: $pipelineTargetLang,
        );
    }

    private function logger(): callable
    {
        $logFile = $this->dataDir . '/logs/studio.log';
        return static function (string $line) use ($logFile): void {
            @file_put_contents(
                $logFile,
                date('Y-m-d H:i:s') . ' [orchestrator] INFO: ' . $line . "\n",
                FILE_APPEND,
            );
        };
    }
}
