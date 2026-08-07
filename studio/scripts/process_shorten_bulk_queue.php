#!/usr/bin/env php
<?php

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
