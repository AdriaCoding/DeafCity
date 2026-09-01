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

use Studio\BackgroundJobLauncher;
use Studio\BulkIntakeQueue;
use Studio\JobManager;
use Studio\ShortenBulkItemProcessor;
use Studio\TranslationJobState;

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

$jobsDir = rtrim($dataDir, '/') . '/shorten-jobs';
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

$processor = new ShortenBulkItemProcessor(
    bulkQueue: $bulkQueue,
    jobManager: $jobManager,
    launcher: $launcher,
    translationState: new TranslationJobState($jobManager),
);

while ($processor->processNext()) {
    // Process each pending item sequentially.
}

exit(0);
