#!/usr/bin/env php
<?php

// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('GROQ_API_KEY'))                 { define('GROQ_API_KEY', ''); }
if (!defined('GROQ_TRANSCRIBE_MODEL'))         { define('GROQ_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo'); }
if (!defined('GROQ_BASE_URL'))                 { define('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'); }
if (!defined('GROQ_TIMEOUT_SECONDS'))          { define('GROQ_TIMEOUT_SECONDS', 20); }
if (!defined('STUDIO_LOCAL_TRANSCRIBE_MODEL')) { define('STUDIO_LOCAL_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo'); }

use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeQueue;
use Studio\BulkItemProcessor;
use Studio\GeminiReviser;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\TranslationJobState;
use Studio\TranscriptionOrchestrator;
use Studio\GroqTranscriber;
use Studio\AudioPreprocessor;
use Studio\SrtParser;

$dataDir = '';
$prev = null;
foreach ($argv as $arg) {
    if ($prev === '--data_dir') {
        $dataDir = $arg;
    }
    $prev = $arg;
}

if ($dataDir === '') {
    fwrite(STDERR, "Missing --data_dir\n");
    exit(1);
}

$jobsDir = rtrim($dataDir, '/') . '/jobs';
$bulkQueue = new BulkIntakeQueue($jobsDir);
if (!$bulkQueue->exists()) {
    fwrite(STDERR, "No bulk queue found\n");
    exit(1);
}

$jobManager = new JobManager($jobsDir);
$studioConfig = new StudioConfig(rtrim($dataDir, '/') . '/studio-config.json');
$launcher = new BackgroundJobLauncher(
    __DIR__,
    defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '',
);

$orchestrator = new TranscriptionOrchestrator(
    jobManager: $jobManager,
    groqTranscriber: new GroqTranscriber(
        GROQ_API_KEY,
        GROQ_BASE_URL,
        GROQ_TIMEOUT_SECONDS,
    ),
    audioPreprocessor: new AudioPreprocessor(),
    launcher: $launcher,
    srtParser: new SrtParser(),
    studioConfig: $studioConfig,
    groqApiKey: GROQ_API_KEY,
    groqModel: GROQ_TRANSCRIBE_MODEL,
    localModel: STUDIO_LOCAL_TRANSCRIBE_MODEL,
    pipelineTargetLang: 'en',
);

$geminiApiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$reviser = $geminiApiKey !== '' ? new GeminiReviser($geminiApiKey) : null;

$processor = new BulkItemProcessor(
    bulkQueue: $bulkQueue,
    jobManager: $jobManager,
    orchestrator: $orchestrator,
    launcher: $launcher,
    translationState: new TranslationJobState($jobManager),
    studioConfig: $studioConfig,
    reviser: $reviser,
);

while ($processor->processNext()) {
    // Process each pending item sequentially.
}

exit(0);
