<?php

// Required constants from config/config.php:
//   STUDIO_PASSWORD         — plaintext password string
//   STUDIO_SESSION_LIFETIME — session duration in seconds (default: 86400)
//   VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Studio\AuthGuard;
use Studio\CaptionFileIntegrityChecker;
use Studio\CatalogTagPool;
use Studio\IntakeHandler;
use Studio\JobManager;
use Studio\PipelineSteps;
use Studio\PublicationHandler;
use Studio\StudioConfig;
use Studio\SubtitleEditorHandler;
use Studio\TaggingHandler;
use Studio\TranslationJobState;
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

/**
 * @return list<string>
 */
function targetSubtitleLanguages(StudioConfig $studioConfig, string $masterLang): array
{
    $targets = [];
    foreach ($studioConfig->getSubtitleLanguages() as $language) {
        $id = $language['id'] ?? '';
        if ($id !== '' && $id !== $masterLang) {
            $targets[] = $id;
        }
    }

    return $targets;
}

function spawnTranslationJob(
    JobManager $jobManager,
    TranslationJobState $translationState,
    StudioConfig $studioConfig,
    string $masterLang,
    ?string $singleLang = null,
): void {
    $targets = $singleLang !== null
        ? [$singleLang]
        : targetSubtitleLanguages($studioConfig, $masterLang);

    if ($targets === []) {
        $translationState->initiate([]);
        return;
    }

    if ($singleLang === null) {
        $translationState->initiate($targets);
    } else {
        $translationState->resetLanguage($singleLang);
    }

    $jobDir = dirname($jobManager->draftVttPath());
    $cmd = sprintf(
        'GEMINI_API_KEY=%s nohup %s --master_vtt %s --status_file %s --source_lang %s --job_dir %s --target_langs %s > /dev/null 2>&1 &',
        escapeshellarg(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''),
        escapeshellarg(__DIR__ . '/scripts/run_translate.sh'),
        escapeshellarg($jobManager->draftVttPath()),
        escapeshellarg($jobManager->translationStatePath()),
        escapeshellarg($masterLang),
        escapeshellarg($jobDir),
        escapeshellarg(implode(',', $targets))
    );
    exec($cmd);
}

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
        'intake_mode' => 'upload',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = $intakeHandler->handlePost($_POST, $_FILES);
        $errors = $result['errors'];
        $values = $result['values'];
        if (!empty($result['created'])) {
            if (($values['intake_mode'] ?? 'upload') === 'generate') {
                $job = $jobManager->read();
                $cmd = sprintf(
                    'nohup %s --audio_file %s --vtt_output %s --status_file %s --language %s > /dev/null 2>&1 &',
                    escapeshellarg(__DIR__ . '/scripts/run_transcribe.sh'),
                    escapeshellarg($jobManager->interpreterAudioPath()),
                    escapeshellarg($jobManager->draftVttPath()),
                    escapeshellarg($jobManager->transcriptionStatusPath()),
                    escapeshellarg($job['subtitle_language'] ?? 'es')
                );
                exec($cmd);
            }
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
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    ob_start();
    try {
        $job = $jobManager->read();
        $lang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
        $savePath = $lang !== ''
            ? $jobManager->draftVttPathForLang($lang)
            : $jobManager->draftVttPath();

        $handler = new SubtitleEditorHandler(
            new VttParser(),
            new CaptionFileIntegrityChecker(),
            $jobManager,
        );
        $body = (string) file_get_contents('php://input');
        $result = $handler->handleRawJson($body, ['savePath' => $savePath]);

        if ($result['ok'] && $lang !== '') {
            (new TranslationJobState($jobManager))->markLanguageReviewed($lang);
        }

        if ($result['ok'] && !empty($result['translate'])) {
            $masterLang = $job['subtitle_language'] ?? 'es';
            spawnTranslationJob(
                $jobManager,
                new TranslationJobState($jobManager),
                $studioConfig,
                $masterLang,
            );
        }
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
    $lang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
    $vttPath = $lang !== ''
        ? $jobManager->draftVttPathForLang($lang)
        : $jobManager->draftVttPath();

    if (!is_file($vttPath)) {
        header('Location: ?action=translation');
        exit;
    }

    $vttParser = new VttParser();
    $parsed = $vttParser->parse($vttPath);
    $cues = $parsed['cues'];
    $vimeoId = $job['vimeo_id'] ?? '';
    $editorMode = $lang !== '' ? 'translation' : 'master';
    $langLabel = '';
    if ($lang !== '') {
        foreach ($studioConfig->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $lang) {
                $langLabel = $language['label'] ?? $lang;
                break;
            }
        }
        if ($langLabel === '') {
            $langLabel = $lang;
        }
    }
    require __DIR__ . '/views/subtitle-editor.php';
    exit;
}

// Transcription status — GET (JSON)
if ($action === 'transcription-status' && $jobManager->exists()) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    $statusPath = $jobManager->transcriptionStatusPath();
    if (!is_file($statusPath)) {
        echo json_encode(['status' => 'pending']);
    } else {
        echo file_get_contents($statusPath);
    }
    exit;
}

// Translation status — GET (JSON)
if ($action === 'translation-status' && $jobManager->exists()) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    $translationState = new TranslationJobState($jobManager);
    echo json_encode($translationState->read());
    exit;
}

// Translation retry — POST
if ($action === 'translation-retry' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobManager->exists()) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    $lang = trim((string) ($_POST['lang'] ?? ''));
    if ($lang === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'errors' => ['Idioma no vàlid.']]);
        exit;
    }

    $job = $jobManager->read();
    spawnTranslationJob(
        $jobManager,
        new TranslationJobState($jobManager),
        $studioConfig,
        $job['subtitle_language'] ?? 'es',
        $lang,
    );
    echo json_encode(['ok' => true]);
    exit;
}

// Skip to Tagging — POST (from Master subtitle editor)
if ($action === 'skip-to-tagging' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobManager->exists()) {
    $jobManager->update(['step' => 'tagging']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Proceed to Tagging from Translation Hub — POST
if ($action === 'proceed-to-tagging' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobManager->exists()) {
    $jobManager->update(['step' => 'tagging']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Translation step — GET (loading screen or hub)
if ($action === 'translation' && $jobManager->exists()) {
    $job = $jobManager->read();
    $translationState = new TranslationJobState($jobManager);
    $topLevelStatus = $translationState->getTopLevelStatus();

    if (in_array($topLevelStatus, ['pending', 'running'], true)) {
        $languageItems = [];
        foreach ($studioConfig->getSubtitleLanguages() as $language) {
            $id = $language['id'] ?? '';
            if ($id === '' || $id === ($job['subtitle_language'] ?? '')) {
                continue;
            }
            $entry = $translationState->getLanguageStatus($id);
            $languageItems[] = [
                'id' => $id,
                'label' => $language['label'] ?? $id,
                'status' => $entry['status'] ?? 'pending',
            ];
        }
        require __DIR__ . '/views/translation-loading.php';
        exit;
    }

    $languageCards = [];
    foreach ($studioConfig->getSubtitleLanguages() as $language) {
        $id = $language['id'] ?? '';
        if ($id === '' || $id === ($job['subtitle_language'] ?? '')) {
            continue;
        }

        $entry = $translationState->getLanguageStatus($id);
        $status = $entry['status'] ?? 'pending';
        $badgeClass = 'pending';
        $badgeLabel = 'Pendent';
        $clickable = false;
        $canRetry = false;

        if ($status === 'error') {
            $badgeClass = 'error';
            $badgeLabel = 'Error';
            $canRetry = true;
        } elseif ($status === 'reviewed') {
            $badgeClass = 'reviewed';
            $badgeLabel = 'Revisat';
            $clickable = true;
        } elseif ($status === 'done') {
            $badgeClass = 'generated';
            $badgeLabel = 'Generat';
            $clickable = true;
        }

        $languageCards[] = [
            'id' => $id,
            'label' => $language['label'] ?? $id,
            'badgeClass' => $badgeClass,
            'badgeLabel' => $badgeLabel,
            'clickable' => $clickable,
            'canRetry' => $canRetry,
        ];
    }

    require __DIR__ . '/views/translation-hub.php';
    exit;
}

// Tagging step — GET and POST
if ($action === 'tagging' && $jobManager->exists()) {
    $job = $jobManager->read();
    $catalogFilePath = $dataDir . '/catalog.json';
    $catalogTagPool = new CatalogTagPool($catalogFilePath);
    $catalogTags = $catalogTagPool->getTagsSortedAlphabetically();
    $errors = [];
    $jobTags = $job['tags'] ?? [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $handler = new TaggingHandler($jobManager);
        $result = $handler->handle($_POST);
        if ($result['ok']) {
            header('Location: ' . $baseUrl);
            exit;
        }
        $jobTags = is_array($_POST['tags'] ?? null) ? $_POST['tags'] : [];
        $errors = $result['errors'];
    }

    require __DIR__ . '/views/tagging.php';
    exit;
}

// Publication step — GET and POST
if ($action === 'publication' && $jobManager->exists()) {
    $job = $jobManager->read();
    $vimeoWarnings = [];

    $signLanguageLabel = $job['sign_language'];
    foreach ($studioConfig->getSignLanguages() as $sl) {
        if ($sl['id'] === $job['sign_language']) {
            $signLanguageLabel = $sl['label'];
            break;
        }
    }
    $editionLabel = $job['edition'];
    foreach ($studioConfig->getEditions() as $ed) {
        if ($ed['id'] === $job['edition']) {
            $editionLabel = $ed['label'];
            break;
        }
    }
    $langLabelMap = [];
    foreach ($studioConfig->getSubtitleLanguages() as $l) {
        $langLabelMap[$l['id']] = $l['label'];
    }

    $summaryTitle = $job['video_title'] ?? '';
    $summarySignLanguage = $signLanguageLabel;
    $summaryEdition = $editionLabel;
    $summaryTags = implode(', ', $job['tags'] ?? []);

    $captionLangs = [$langLabelMap[$job['subtitle_language'] ?? ''] ?? ($job['subtitle_language'] ?? '')];
    $translationState = new TranslationJobState($jobManager);
    foreach ($studioConfig->getSubtitleLanguages() as $l) {
        $lang = $l['id'];
        if ($lang === ($job['subtitle_language'] ?? '')) {
            continue;
        }
        if (is_file($jobManager->draftVttPathForLang($lang))) {
            $captionLangs[] = $langLabelMap[$lang] ?? $lang;
        }
    }
    $summaryCaptions = implode(', ', $captionLangs);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $handler = new PublicationHandler(
            new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN),
            $jobManager,
            $studioConfig,
            $dataDir . '/captions',
            $dataDir . '/catalog.json',
        );
        $result = $handler->handle();
        if (empty($result['vimeoWarnings'])) {
            header('Location: ' . $baseUrl);
            exit;
        }
        $vimeoWarnings = $result['vimeoWarnings'];
    }

    require __DIR__ . '/views/publication.php';
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
$isTranscribing = false;
$transcriptionError = null;

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

    if (($job['intake_mode'] ?? 'upload') === 'generate' && !$jobManager->hasDraftVtt()) {
        $isTranscribing = true;
        $statusPath = $jobManager->transcriptionStatusPath();
        if (is_file($statusPath)) {
            $statusData = json_decode((string) file_get_contents($statusPath), true);
            if (($statusData['status'] ?? '') === 'error') {
                $transcriptionError = $statusData['message'] ?? 'Error en la generació de subtítols';
            }
        }
    }
}

require __DIR__ . '/views/shell.php';
