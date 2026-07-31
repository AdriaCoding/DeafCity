<?php

namespace Studio;

class Container
{
    private ?VimeoClient $vimeoClient = null;
    private ?CatalogEditor $catalogEditor = null;
    private ?StudioConfigMutation $configMutation = null;

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

    public function configMutation(): StudioConfigMutation
    {
        return $this->configMutation ??= new StudioConfigMutation(
            $this->studioConfig,
            $this->catalogEditor(),
        );
    }

    public function catalogSheetSync(): CatalogSheetSync
    {
        if (!defined('SPREADSHEET_ID') || SPREADSHEET_ID === '') {
            throw new \RuntimeException('SPREADSHEET_ID is not configured.');
        }
        $serviceAccountPath = dirname($this->dataDir) . '/config/google-sheets-service-account.json';
        if (!is_readable($serviceAccountPath)) {
            throw new \RuntimeException('Google Sheets service-account JSON not readable.');
        }

        return new CatalogSheetSync(
            new GoogleSheetsClient($serviceAccountPath, SPREADSHEET_ID),
            $this->vimeoClient(),
            $this->catalogEditor(),
            new SheetCatalogParser(),
        );
    }

    public function bulkIntakeQueue(): BulkIntakeQueue
    {
        return new BulkIntakeQueue($this->dataDir . '/jobs');
    }

    /** @return array<string, mixed> */
    public function headerContext(?string $activeNav = null): array
    {
        return StudioHeader::vars($this, $activeNav);
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
