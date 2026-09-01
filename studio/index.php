<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!defined('GROQ_API_KEY'))                 { define('GROQ_API_KEY', ''); }
if (!defined('GROQ_TRANSCRIBE_MODEL'))         { define('GROQ_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo'); }
if (!defined('GROQ_BASE_URL'))                 { define('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'); }
if (!defined('GROQ_TIMEOUT_SECONDS'))          { define('GROQ_TIMEOUT_SECONDS', 20); }
if (!defined('STUDIO_LOCAL_TRANSCRIBE_MODEL')) { define('STUDIO_LOCAL_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo'); }

use Studio\Actions\BulkAction;
use Studio\Actions\CatalogAction;
use Studio\Actions\CreditsEditorAction;
use Studio\Actions\DownloadAction;
use Studio\Actions\IntakeAction;
use Studio\Actions\LocalizationAction;
use Studio\Actions\ShellAction;
use Studio\Actions\ShortenAction;
use Studio\Actions\SyncAction;
use Studio\AuthGuard;
use Studio\AuthThrottle;
use Studio\BackgroundJobLauncher;
use Studio\Container;
use Studio\Csrf;
use Studio\JobManager;
use Studio\StudioConfig;

/**
 * Secure session cookie. Must be configured before session_start(). The
 * cookie path is scoped to the Studio base path rather than the site-wide
 * "/", so the session cookie isn't sent to unrelated parts of deaf.city.
 */
$studioBasePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/studio/index.php'))), '/') . '/';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $studioBasePath,
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$guard = new AuthGuard($_SESSION);
$baseUrl = (string) strtok($_SERVER['REQUEST_URI'], '?');
$action = $_GET['action'] ?? null;
$dataDir = dirname(__DIR__) . '/data';
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$throttle = new AuthThrottle($dataDir . '/auth-throttle');

// Every request gets a stable per-session CSRF token — both the login form
// (pre-auth) and every authenticated form/fetch call need one available.
Csrf::issueToken($_SESSION);

/** After this many failed logins from one IP within the window, reject with 429. */
const STUDIO_KNOWN_WEAK_PASSWORDS = ['hola', '1234', 'password', 'admin'];

function studioAuthLog(string $dataDir, string $message): void
{
    $logFile = $dataDir . '/logs/studio.log';
    @file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' [auth] WARN: ' . $message . "\n",
        FILE_APPEND,
    );
}

function studioCsrfTokenFromRequest(): ?string
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    return is_string($token) && $token !== '' ? $token : null;
}

function studioRejectCsrf(bool $asJson): never
{
    http_response_code(403);
    if ($asJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ok' => false, 'error' => 'Sessió no vàlida o caducada. Recarrega la pàgina i torna-ho a provar.'],
            JSON_UNESCAPED_UNICODE,
        );
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ca"><head><meta charset="UTF-8"><title>Error</title></head>'
        . '<body><p>Sessió no vàlida o caducada. Torna a la pàgina anterior i torna-ho a provar.</p></body></html>';
    exit;
}

function studioRequireValidCsrf(bool $asJson): void
{
    if (!Csrf::validate($_SESSION, studioCsrfTokenFromRequest())) {
        studioRejectCsrf($asJson);
    }
}

// Logout is a state change — it must be a POST with a valid CSRF token,
// not a plain link a page could be tricked into loading (or crawled).
if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Mètode no permès.';
        exit;
    }
    studioRequireValidCsrf(false);
    $guard->logout();
    session_destroy();
    header('Location: ' . $baseUrl);
    exit;
}

// Login gate
$showError = false;
$lockoutMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$guard->isAuthenticated() && isset($_POST['password'])) {
    if ($throttle->isLockedOut($clientIp)) {
        http_response_code(429);
        $lockoutMessage = "Massa intents fallits. Torna-ho a provar d'aquí a 15 minuts.";
    } else {
        studioRequireValidCsrf(false);
        if ($guard->login((string) $_POST['password'])) {
            $throttle->reset($clientIp);
            if (in_array(STUDIO_PASSWORD, STUDIO_KNOWN_WEAK_PASSWORDS, true)) {
                studioAuthLog($dataDir, 'STUDIO_PASSWORD is set to a known-weak value; change it in config/config.php.');
            }
            // Regenerate the session id post-auth (fixation defense). Done
            // here rather than in AuthGuard so AuthGuard stays unit-testable
            // with a plain array session, with no real PHP session involved.
            session_regenerate_id(true);
            header('Location: ' . $baseUrl);
            exit;
        }
        $throttle->recordFailure($clientIp);
        $showError = true;
    }
}
if (!$guard->isAuthenticated()) {
    if ($lockoutMessage === null && $throttle->isLockedOut($clientIp)) {
        http_response_code(429);
        $lockoutMessage = "Massa intents fallits. Torna-ho a provar d'aquí a 15 minuts.";
    }
    require __DIR__ . '/views/blocker.php';
    exit;
}

$container = new Container(
    dataDir: $dataDir,
    baseUrl: $baseUrl,
    jobManager: new JobManager($dataDir . '/jobs'),
    studioConfig: new StudioConfig($dataDir . '/studio-config.json'),
    launcher: new BackgroundJobLauncher(
        __DIR__ . '/scripts',
        defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '',
    ),
);

/**
 * Actions safely reachable by a plain GET request: page renders, status
 * polls, and downloads. Everything else is a state change and must arrive
 * as POST with a valid CSRF token (checked below, before dispatch).
 *
 * Note: 'continguts-caption-review', 'transcription-intake' and
 * 'shorten-intake' render on GET but also handle a POST branch internally
 * (they read $_SERVER['REQUEST_METHOD'] themselves) — whitelisting them
 * here only exempts their GET path; a POST to the same action name still
 * falls through to the POST+CSRF branch below.
 */
$readOnlyGetActions = [
    null,
    'continguts',
    'continguts-video',
    'continguts-caption-review',
    'continguts-download-caption-srt',
    'continguts-download-data-zip',
    'continguts-caption-translate-status',
    'continguts-batch-translate-status',
    'transcription-status',
    'translation-status',
    'transcription-intake',
    'bulk-progress',
    'bulk-status',
    'bulk-download',
    'shorten-intake',
    'resume-shorten-job',
    'shorten-download-srt',
    'shorten-bulk-progress',
    'shorten-bulk-status',
    'shorten-bulk-download',
    'download-srt',
    'localitzacions',
    'credits-editor',
    'sync-status',
    'resume-job',
];

/** Mutating actions whose handler responds with JSON, so a CSRF failure should too. */
$jsonPostActions = [
    'add-sign-language',
    'add-edition',
    'add-typology',
    'add-subtitle-language',
    'add-input-language',
    'continguts-resolve-vimeo',
    'continguts-add-video',
    'continguts-save-video',
    'continguts-set-video-invisible',
    'continguts-set-master-caption',
    'continguts-save-edition-label',
    'continguts-save-sign-language-label',
    'continguts-save-typology-label',
    'continguts-delete-edition',
    'continguts-delete-sign-language',
    'continguts-delete-subtitle-language',
    'continguts-delete-input-language',
    'continguts-delete-typology',
    'continguts-delete-caption',
    'continguts-delete-all-translations',
    'continguts-replace-caption',
    'continguts-caption-review',
    'continguts-caption-translate-start',
    'continguts-caption-translate-retry',
    'continguts-sync-from-sheet',
    'continguts-batch-translate',
    'localitzacions-save',
    'localitzacions-seed',
    'credits-editor-save',
    'credits-editor-revert',
    'translation-retry',
];

$isWhitelistedGet = $_SERVER['REQUEST_METHOD'] === 'GET' && in_array($action, $readOnlyGetActions, true);
if (!$isWhitelistedGet) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Mètode no permès.';
        exit;
    }
    studioRequireValidCsrf(in_array($action, $jsonPostActions, true));
}

match ($action) {
    'cancel'                              => (new IntakeAction($container))->cancel(),
    'transcription-intake'                => (new IntakeAction($container))->handleTranscription(),
    'transcription-status'                => (new IntakeAction($container))->transcriptionStatus(),
    'translation-status'                  => (new IntakeAction($container))->jobTranslationStatus(),
    'translation-retry'                   => (new IntakeAction($container))->jobTranslationRetry(),
    'bulk-progress'                       => (new BulkAction($container))->progress(),
    'bulk-status'                         => (new BulkAction($container))->status(),
    'bulk-download'                       => (new BulkAction($container))->download(),
    'shorten-intake'                      => (new ShortenAction($container))->intake(),
    'resume-shorten-job'                  => (new ShortenAction($container))->resume(),
    'shorten-cancel'                      => (new ShortenAction($container))->cancel(),
    'shorten-download-srt'                => (new ShortenAction($container))->downloadSrt(),
    'shorten-bulk-progress'               => (new ShortenAction($container))->bulkProgress(),
    'shorten-bulk-status'                 => (new ShortenAction($container))->bulkStatus(),
    'shorten-bulk-download'               => (new ShortenAction($container))->bulkDownload(),
    'download-srt'                        => (new DownloadAction($container))->downloadSrt(),
    'add-sign-language'                   => (new CatalogAction($container))->addSignLanguage(),
    'add-edition'                         => (new CatalogAction($container))->addEdition(),
    'add-typology'                        => (new CatalogAction($container))->addTypology(),
    'add-subtitle-language'               => (new CatalogAction($container))->addSubtitleLanguage(),
    'add-input-language'                  => (new CatalogAction($container))->addInputLanguage(),
    'continguts',
    'continguts-video',
    'continguts-resolve-vimeo',
    'continguts-add-video',
    'continguts-save-video',
    'continguts-set-video-invisible',
    'continguts-set-master-caption',
    'continguts-download-caption-srt',
    'continguts-download-data-zip',
    'continguts-save-edition-label',
    'continguts-save-sign-language-label',
    'continguts-save-typology-label',
    'continguts-delete-edition',
    'continguts-delete-sign-language',
    'continguts-delete-subtitle-language',
    'continguts-delete-input-language',
    'continguts-delete-typology',
    'continguts-delete-caption',
    'continguts-delete-all-translations',
    'continguts-replace-caption',
    'continguts-caption-review',
    'continguts-caption-translate-start',
    'continguts-caption-translate-status',
    'continguts-caption-translate-retry',
    'continguts-sync-from-sheet',
    'continguts-batch-translate',
    'continguts-batch-translate-status' => (new CatalogAction($container))->handle($action),
    'localitzacions',
    'localitzacions-save',
    'localitzacions-seed'               => (new LocalizationAction($container))->handle($action),
    'credits-editor',
    'credits-editor-save',
    'credits-editor-revert'             => (new CreditsEditorAction($container))->handle($action),
    'sync'                                => (new SyncAction($container))->launch(),
    'sync-status'                         => (new SyncAction($container))->status(),
    'resume-job'                          => (new ShellAction($container))->handle('resume-job'),
    default                               => (new ShellAction($container))->handle($action),
};
