#!/usr/bin/env php
<?php

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
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\TranslationJobState;
use Studio\TranscriptionOrchestrator;
use Studio\GroqTranscriber;
use Studio\AudioPreprocessor;
use Studio\VttParser;

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
    vttParser: new VttParser(),
    groqApiKey: GROQ_API_KEY,
    groqModel: GROQ_TRANSCRIBE_MODEL,
    localModel: STUDIO_LOCAL_TRANSCRIBE_MODEL,
    pipelineTargetLang: 'en',
);

$processor = new BulkItemProcessor(
    bulkQueue: $bulkQueue,
    jobManager: $jobManager,
    orchestrator: $orchestrator,
    launcher: $launcher,
    translationState: new TranslationJobState($jobManager),
);

while ($processor->processNext()) {
    // Process each pending item sequentially.
}

exit(0);
