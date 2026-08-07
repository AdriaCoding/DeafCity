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
use Studio\BackgroundJobLauncher;
use Studio\Container;
use Studio\JobManager;
use Studio\StudioConfig;

session_start();
$guard = new AuthGuard($_SESSION);
$baseUrl = (string) strtok($_SERVER['REQUEST_URI'], '?');
$action = $_GET['action'] ?? null;
$dataDir = dirname(__DIR__) . '/data';

// Logout
if ($action === 'logout') {
    $guard->logout();
    session_destroy();
    header('Location: ' . $baseUrl);
    exit;
}

// Login gate
$showError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$guard->isAuthenticated() && isset($_POST['password'])) {
    if ($guard->login((string) $_POST['password'])) {
        header('Location: ' . $baseUrl);
        exit;
    }
    $showError = true;
}
if (!$guard->isAuthenticated()) {
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
    'shorten-download-vtt'                => (new ShortenAction($container))->downloadVtt(),
    'shorten-download-srt'                => (new ShortenAction($container))->downloadSrt(),
    'shorten-bulk-progress'               => (new ShortenAction($container))->bulkProgress(),
    'shorten-bulk-status'                 => (new ShortenAction($container))->bulkStatus(),
    'shorten-bulk-download'               => (new ShortenAction($container))->bulkDownload(),
    'download-vtt'                        => (new DownloadAction($container))->downloadVtt(),
    'download-srt'                        => (new DownloadAction($container))->downloadSrt(),
    'add-sign-language'                   => (new CatalogAction($container))->addSignLanguage(),
    'add-edition'                         => (new CatalogAction($container))->addEdition(),
    'add-typology'                        => (new CatalogAction($container))->addTypology(),
    'add-subtitle-language'               => (new CatalogAction($container))->addSubtitleLanguage(),
    'continguts',
    'continguts-video',
    'continguts-resolve-vimeo',
    'continguts-add-video',
    'continguts-save-video',
    'continguts-set-video-invisible',
    'continguts-set-master-caption',
    'continguts-download-caption-vtt',
    'continguts-download-caption-srt',
    'continguts-download-data-zip',
    'continguts-save-edition-label',
    'continguts-save-sign-language-label',
    'continguts-save-typology-label',
    'continguts-delete-edition',
    'continguts-delete-sign-language',
    'continguts-delete-subtitle-language',
    'continguts-delete-typology',
    'continguts-delete-caption',
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
