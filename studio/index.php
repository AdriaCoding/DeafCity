<?php

// Required constants from config/config.php:
//   STUDIO_PASSWORD         — plaintext password string
//   STUDIO_SESSION_LIFETIME — session duration in seconds (default: 86400)
//   VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Studio\AuthGuard;
use Studio\CaptionFileIntegrityChecker;
use Studio\IntakeHandler;
use Studio\JobManager;
use Studio\PipelineSteps;
use Studio\StudioConfig;
use Studio\SubtitleEditorHandler;
use Studio\VimeoClient;
use Studio\VimeoIdParser;
use Studio\VttParser;
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

// Login attempt (must not depend on $action — blocker POSTs from the current URL)
$showError = false;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !$guard->isAuthenticated()
    && array_key_exists('password', $_POST)
) {
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

// Subtitle editor — POST (JSON save)
if ($action === 'subtitle-editor' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobManager->exists()) {
    // Set JSON content-type and suppress error output immediately so that any PHP
    // warning/notice (e.g. from display_errors On in the root .htaccess) does not
    // contaminate the JSON response body and break the client-side fetch.
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    ob_start();
    try {
        $handler = new SubtitleEditorHandler(
            new VttParser(),
            new CaptionFileIntegrityChecker(),
            $jobManager,
        );
        $body = (string) file_get_contents('php://input');
        $result = $handler->handleRawJson($body);
    } catch (\Throwable $e) {
        $result = ['ok' => false, 'errors' => ['Error del servidor: ' . $e->getMessage()]];
    }
    ob_end_clean();
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result);
    exit;
}

// Subtitle editor — GET
if ($action === 'subtitle-editor' && $jobManager->exists()) {
    $job = $jobManager->read();
    $vttParser = new VttParser();
    $parsed = $vttParser->parse($jobManager->draftVttPath());
    $cues = $parsed['cues'];
    $vimeoId = $job['vimeo_id'] ?? '';
    require __DIR__ . '/views/subtitle-editor.php';
    exit;
}

// Skip to Tagging — POST
if ($action === 'skip-to-tagging' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobManager->exists()) {
    $jobManager->update(['step' => 'tagging']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
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
