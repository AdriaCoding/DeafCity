<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

if (!defined('GROQ_API_KEY'))                 { define('GROQ_API_KEY', ''); }
if (!defined('GROQ_TRANSCRIBE_MODEL'))         { define('GROQ_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo'); }
if (!defined('GROQ_BASE_URL'))                 { define('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'); }
if (!defined('GROQ_TIMEOUT_SECONDS'))          { define('GROQ_TIMEOUT_SECONDS', 20); }
if (!defined('STUDIO_LOCAL_TRANSCRIBE_MODEL')) { define('STUDIO_LOCAL_TRANSCRIBE_MODEL', 'whisper-large-v3-turbo'); }

use Studio\Actions\CatalogAction;
use Studio\Actions\EditorAction;
use Studio\Actions\IntakeAction;
use Studio\Actions\PublicationAction;
use Studio\Actions\ShellAction;
use Studio\Actions\SyncAction;
use Studio\Actions\TranslationAction;
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
    'intake'                              => (new IntakeAction($container))->handle(),
    'transcription-intake'                => (new IntakeAction($container))->handleTranscription(),
    'transcription-status'                => (new IntakeAction($container))->transcriptionStatus(),
    'add-sign-language'                   => (new CatalogAction($container))->addSignLanguage(),
    'add-edition'                         => (new CatalogAction($container))->addEdition(),
    'add-subtitle-language'               => (new CatalogAction($container))->addSubtitleLanguage(),
    'continguts',
    'continguts-video',
    'continguts-resolve-vimeo',
    'continguts-add-video',
    'continguts-save-video',
    'continguts-set-master-caption',
    'continguts-download-caption-vtt',
    'continguts-download-caption-srt',
    'continguts-save-edition-label',
    'continguts-save-sign-language-label',
    'continguts-delete-edition',
    'continguts-delete-sign-language',
    'continguts-delete-subtitle-language',
    'continguts-delete-caption',
    'continguts-replace-caption',
    'continguts-caption-review' => (new CatalogAction($container))->handle($action),
    'subtitle-editor'                     => (new EditorAction($container))->handle(),
    'translation-review'                  => (new EditorAction($container))->translationReview(),
    'download-vtt'                        => (new EditorAction($container))->downloadVtt(),
    'download-srt'                        => (new EditorAction($container))->downloadSrt(),
    'translation'                         => (new TranslationAction($container))->hub(),
    'translation-status'                  => (new TranslationAction($container))->status(),
    'translation-retry'                   => (new TranslationAction($container))->retry(),
    'tagging'                             => (new PublicationAction($container))->tagging(),
    'skip-to-tagging',
    'proceed-to-tagging'                  => (new PublicationAction($container))->advanceToTagging(),
    'publication'                         => (new PublicationAction($container))->handle(),
    'sync'                                => (new SyncAction($container))->launch(),
    'sync-status'                         => (new SyncAction($container))->status(),
    default                               => (new ShellAction($container))->handle($action),
};
