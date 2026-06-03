<?php

// Required constants from config/config.php:
//   STUDIO_PASSWORD         — plaintext password string
//   STUDIO_SESSION_LIFETIME — session duration in seconds (default: 86400)
//   VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

// Transcription config — defined()-guarded so an un-updated config.php still
// boots. See config/config.example.php for documentation. A blank GROQ_API_KEY
// means "use the local engine only" (no egress).
if (!defined('GROQ_API_KEY')) {
    define('GROQ_API_KEY', '');
}
if (!defined('GROQ_TRANSCRIBE_MODEL')) {
    define('GROQ_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo');
}
if (!defined('GROQ_BASE_URL')) {
    define('GROQ_BASE_URL', 'https://api.groq.com/openai/v1');
}
if (!defined('GROQ_TIMEOUT_SECONDS')) {
    define('GROQ_TIMEOUT_SECONDS', 20);
}
if (!defined('STUDIO_LOCAL_TRANSCRIBE_MODEL')) {
    define('STUDIO_LOCAL_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo');
}

use Studio\AudioPreprocessor;
use Studio\AuthGuard;
use Studio\BackgroundJobLauncher;
use Studio\CaptionFileIntegrityChecker;
use Studio\CatalogTagPool;
use Studio\GroqTranscriber;
use Studio\EditionAddHandler;
use Studio\SignLanguageAddHandler;
use Studio\IntakeHandler;
use Studio\JobManager;
use Studio\PipelineSteps;
use Studio\PublicationHandler;
use Studio\StudioConfig;
use Studio\SubtitleEditorHandler;
use Studio\TaggingHandler;
use Studio\SrtConversionOrchestrator;
use Studio\TranscriptionOrchestrator;
use Studio\TranslationJobState;
use Studio\VimeoClient;
use Studio\VimeoIdParser;
use Studio\VttParser;
use Studio\VttToSrtConverter;
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
$launcher = new BackgroundJobLauncher(
    __DIR__ . '/scripts',
    defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '',
);

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
    BackgroundJobLauncher $launcher,
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

    $launcher->launchTranslation(
        $jobManager->draftVttPath(),
        $jobManager->translationStatePath(),
        $masterLang,
        dirname($jobManager->draftVttPath()),
        $targets
    );
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

// Add sign language to studio-config (intake helper)
if ($action === 'add-sign-language' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $handler = new SignLanguageAddHandler($studioConfig);
    $result = $handler->handle(
        (string) ($_POST['sign_language_code'] ?? ''),
        (string) ($_POST['sign_language_qualifier'] ?? ''),
    );
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// Add edition to studio-config (intake helper)
if ($action === 'add-edition' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $handler = new EditionAddHandler($studioConfig);
    $result = $handler->handle(
        (string) ($_POST['edition_city'] ?? ''),
        (string) ($_POST['edition_year'] ?? ''),
    );
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
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
            if (($result['intake_format'] ?? '') === 'srt') {
                $conversionOutcome = (new SrtConversionOrchestrator($jobManager, $launcher))->run();
                if ($conversionOutcome['result'] === 'error') {
                    $errors['_form'] = $conversionOutcome['message'] ?? 'Error en la conversió de subtítols';
                } else {
                    header('Location: ' . $baseUrl);
                    exit;
                }
            } elseif (($values['intake_mode'] ?? 'upload') === 'generate') {
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
                    logger: function (string $line) use ($dataDir): void {
                        $logFile = $dataDir . '/logs/studio.log';
                        @file_put_contents(
                            $logFile,
                            date('Y-m-d H:i:s') . ' [orchestrator] INFO: ' . $line . "\n",
                            FILE_APPEND
                        );
                    },
                );

                $outcome = $orchestrator->run();

                if ($outcome['result'] === 'editor') {
                    header('Location: ?action=subtitle-editor');
                    exit;
                }
                if ($outcome['result'] === 'loading') {
                    header('Location: ' . $baseUrl);
                    exit;
                }

                // 'error' — the Job was destroyed; re-render Intake with the message.
                $errors['_form'] = $outcome['message'] ?? 'Error en la generació de subtítols';
            } else {
                header('Location: ' . $baseUrl);
                exit;
            }
        }
    }

    $signLanguages = $studioConfig->getSignLanguages();
    $editions = $studioConfig->getEditions();
    $subtitleLanguages = $studioConfig->getSubtitleLanguages();
    require __DIR__ . '/views/intake.php';
    exit;
}

// Translation review — POST (JSON save)
if ($action === 'translation-review' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobManager->exists()) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    ob_start();
    try {
        $lang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
        if ($lang === '') {
            $result = ['ok' => false, 'errors' => ['Idioma no especificat.']];
        } else {
            $handler = new SubtitleEditorHandler(
                new VttParser(),
                new CaptionFileIntegrityChecker(),
                $jobManager,
            );
            $body = (string) file_get_contents('php://input');
            $result = $handler->handleRawJson($body, ['lang' => $lang]);
            if ($result['ok']) {
                (new TranslationJobState($jobManager))->markLanguageReviewed($lang);
            }
        }
    } catch (\Throwable $e) {
        $result = ['ok' => false, 'errors' => ['Error del servidor: ' . $e->getMessage()]];
    }
    ob_end_clean();
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result);
    exit;
}

// Translation review — GET
if ($action === 'translation-review' && $jobManager->exists()) {
    $lang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
    if ($lang === '') {
        header('Location: ?action=translation');
        exit;
    }
    $translatedVttPath = $jobManager->draftVttPathForLang($lang);
    if (!is_file($translatedVttPath)) {
        header('Location: ?action=translation');
        exit;
    }
    $job = $jobManager->read();
    $vimeoId = $job['vimeo_id'] ?? '';
    $vttParser = new VttParser();
    $translatedCues = $vttParser->parse($translatedVttPath)['cues'];
    $masterCues = [];
    $masterVttPath = $jobManager->draftVttPath();
    if (is_file($masterVttPath)) {
        $masterCues = $vttParser->parse($masterVttPath)['cues'];
    }
    $langLabel = $lang;
    foreach ($studioConfig->getSubtitleLanguages() as $language) {
        if (($language['id'] ?? '') === $lang) {
            $langLabel = $language['label'] ?? $lang;
            break;
        }
    }
    require __DIR__ . '/views/translation-review.php';
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

        $handler = new SubtitleEditorHandler(
            new VttParser(),
            new CaptionFileIntegrityChecker(),
            $jobManager,
        );
        $body = (string) file_get_contents('php://input');
        $result = $handler->handleRawJson($body, ['lang' => $lang !== '' ? $lang : null]);

        if ($result['ok'] && $lang !== '') {
            (new TranslationJobState($jobManager))->markLanguageReviewed($lang);
        }

        if ($result['ok'] && !empty($result['translate'])) {
            $masterLang = $job['subtitle_language'] ?? 'es';
            spawnTranslationJob(
                $jobManager,
                new TranslationJobState($jobManager),
                $studioConfig,
                $launcher,
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
        if ($jobManager->needsSrtConversion() || (($job['intake_mode'] ?? '') === 'generate' && !$jobManager->hasDraftVtt())) {
            header('Location: ' . $baseUrl);
            exit;
        }
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

// SRT conversion status — GET (JSON)
if ($action === 'conversion-status' && $jobManager->exists()) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    echo $jobManager->readConversionStatus() ?? json_encode(['status' => 'pending']);
    exit;
}

// Transcription status — GET (JSON)
if ($action === 'transcription-status' && $jobManager->exists()) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    echo $jobManager->readTranscriptionStatus() ?? json_encode(['status' => 'pending']);
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
        $launcher,
        $job['subtitle_language'] ?? 'es',
        $lang,
    );
    echo json_encode(['ok' => true]);
    exit;
}

// Download VTT — GET
if ($action === 'download-vtt' && $jobManager->exists()) {
    $lang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
    $vttPath = $lang !== ''
        ? $jobManager->draftVttPathForLang($lang)
        : $jobManager->draftVttPath();

    if (!is_file($vttPath)) {
        http_response_code(404);
        exit;
    }

    $job = $jobManager->read();
    $vimeoId = $job['vimeo_id'] ?? 'draft';
    $filename = $vimeoId . ($lang !== '' ? '_' . $lang : '') . '.vtt';

    header('Content-Type: text/vtt; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($vttPath);
    exit;
}

// Download SRT — GET
if ($action === 'download-srt' && $jobManager->exists()) {
    $lang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
    $vttPath = $lang !== ''
        ? $jobManager->draftVttPathForLang($lang)
        : $jobManager->draftVttPath();

    if (!is_file($vttPath)) {
        http_response_code(404);
        exit;
    }

    $job = $jobManager->read();
    $vimeoId = $job['vimeo_id'] ?? 'draft';
    $filename = $vimeoId . ($lang !== '' ? '_' . $lang : '') . '.srt';
    $srtContent = (new VttToSrtConverter())->convert($vttPath);

    header('Content-Type: application/x-subrip; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $srtContent;
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

    $masterLang = $job['subtitle_language'] ?? '';
    $masterLangLabel = $masterLang;
    $vimeoId = $job['vimeo_id'] ?? '';
    foreach ($studioConfig->getSubtitleLanguages() as $language) {
        if (($language['id'] ?? '') === $masterLang) {
            $masterLangLabel = $language['label'] ?? $masterLang;
            break;
        }
    }

    $languageCards = [];
    foreach ($studioConfig->getSubtitleLanguages() as $language) {
        $id = $language['id'] ?? '';
        if ($id === '' || $id === $masterLang) {
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
    $publicationError = null;

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
        try {
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
        } catch (\Throwable $e) {
            $publicationError = 'No s\'ha pogut completar la publicació. ' . $e->getMessage();
        }
    }

    require __DIR__ . '/views/publication.php';
    exit;
}

// Sync — POST (launch background process)
if ($action === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $syncStatusPath = $dataDir . '/sync-status.json';
    $currentStatus = null;
    if (is_file($syncStatusPath)) {
        $raw = @file_get_contents($syncStatusPath);
        $currentStatus = $raw ? json_decode($raw, true) : null;
    }
    if (($currentStatus['status'] ?? '') !== 'running') {
        file_put_contents($syncStatusPath, json_encode(['status' => 'running', 'synced' => 0, 'total' => 0]));
        $launcher->launchSync($syncStatusPath);
    }
    header('Location: ' . $baseUrl);
    exit;
}

// Sync status — GET (JSON)
if ($action === 'sync-status') {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    $syncStatusPath = $dataDir . '/sync-status.json';
    echo is_file($syncStatusPath) ? (file_get_contents($syncStatusPath) ?: '{}') : json_encode(['status' => 'idle']);
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
$syncStatusPath = $dataDir . '/sync-status.json';
$syncStatus = null;
if (is_file($syncStatusPath)) {
    $raw = @file_get_contents($syncStatusPath);
    $syncStatus = ($raw ? json_decode($raw, true) : null) ?? null;
}
$isSyncing = ($syncStatus['status'] ?? '') === 'running';

$hasActiveJob = $jobManager->exists();
$job = [];
$editionLabel = '';
$stepLabel = '';
$resumeUrl = './';
$isTranscribing = false;
$transcriptionError = null;
$isLocalFallback = false;
$isConvertingSrt = false;
$conversionError = null;

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
        $isLocalFallback = str_starts_with($job['transcription_engine'] ?? '', 'local:');
        $statusJson = $jobManager->readTranscriptionStatus();
        if ($statusJson !== null) {
            $statusData = json_decode($statusJson, true);
            if (($statusData['status'] ?? '') === 'error') {
                $transcriptionError = $statusData['message'] ?? 'Error en la generació de subtítols';
            }
        }
    } elseif ($jobManager->needsSrtConversion()) {
        $isConvertingSrt = true;
        $statusJson = $jobManager->readConversionStatus();
        if ($statusJson !== null) {
            $statusData = json_decode($statusJson, true);
            if (($statusData['status'] ?? '') === 'error') {
                $conversionError = $statusData['message'] ?? 'Error en la conversió de subtítols';
            }
        }
    }
}

require __DIR__ . '/views/shell.php';
