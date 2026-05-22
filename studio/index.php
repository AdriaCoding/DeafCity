<?php

// Required constants from config/config.php:
//   STUDIO_PASSWORD         — plaintext password string
//   STUDIO_SESSION_LIFETIME — session duration in seconds (default: 86400)
//   VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Studio\AuthGuard;
use Studio\IntakeHandler;
use Studio\JobManager;
use Studio\PipelineSteps;
use Studio\StudioConfig;
use Studio\VimeoClient;
use Studio\VimeoIdParser;
use Studio\WebVttValidator;

session_start();
$guard = new AuthGuard($_SESSION);

$baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
$action = $_GET['action'] ?? null;

$dataDir = dirname(__DIR__) . '/data';
$jobsDir = $dataDir . '/jobs';
$studioConfigPath = $dataDir . '/studio-config.json';

$jobManager = new JobManager($jobsDir);
$studioConfig = new StudioConfig($studioConfigPath);

// Logout
if ($action === 'logout') {
    $guard->logout();
    session_destroy();
    header('Location: ' . $baseUrl);
    exit;
}

// Login attempt
$showError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === null) {
    $submitted = $_POST['password'] ?? '';
    if ($guard->login($submitted)) {
        header('Location: ' . $baseUrl);
        exit;
    }
    $showError = true;
}

// Gate: show blocker if not authenticated
if (!$guard->isAuthenticated()) {
    require __DIR__ . '/views/blocker.php';
    exit;
}

// Cancel active job
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobManager->cancel();
    header('Location: ' . $baseUrl);
    exit;
}

// Intake form
if ($action === 'intake') {
    if ($jobManager->exists() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . $baseUrl);
        exit;
    }

    $intakeHandler = new IntakeHandler(
        new VimeoIdParser(),
        new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN),
        $studioConfig,
        $jobManager,
        new WebVttValidator(),
    );

    $errors = [];
    $values = [
        'vimeo_input' => '',
        'sign_language' => '',
        'edition' => '',
        'subtitle_language' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = $intakeHandler->handlePost($_POST, $_FILES);
        $errors = $result['errors'];
        $values = $result['values'];
        if (!empty($result['created'])) {
            header('Location: ' . $baseUrl);
            exit;
        }
    }

    $signLanguages = $studioConfig->getSignLanguages();
    $editions = $studioConfig->getEditions();
    $subtitleLanguages = $studioConfig->getSubtitleLanguages();
    require __DIR__ . '/views/intake.php';
    exit;
}

// Pipeline step routes (placeholders until later slices ship)
if ($action !== null && $jobManager->exists()) {
    $job = $jobManager->read();
    if (($job['step'] ?? '') === $action) {
        $stepLabel = PipelineSteps::label($action);
        require __DIR__ . '/views/step-placeholder.php';
        exit;
    }
}

// Studio shell
$hasActiveJob = $jobManager->exists();
$job = [];
$editionLabel = '';
$stepLabel = '';
$resumeUrl = './';

if ($hasActiveJob) {
    $job = $jobManager->read();
    $step = $job['step'] ?? 'subtitle-editor';
    $stepLabel = PipelineSteps::label($step);
    $resumeUrl = PipelineSteps::route($step);
    $editionLabel = $job['edition'];
    foreach ($studioConfig->getEditions() as $edition) {
        if ($edition['id'] === $job['edition']) {
            $editionLabel = $edition['label'];
            break;
        }
    }
}

require __DIR__ . '/views/shell.php';
