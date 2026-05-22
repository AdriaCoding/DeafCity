<?php
/**
 * CLI translation entry point.
 *
 * Usage:
 *   GEMINI_API_KEY=<key> php translate.php \
 *     --master_vtt <path> \
 *     --status_file <path> \
 *     --source_lang <lang> \
 *     --job_dir <path> \
 *     --target_langs <lang1,lang2,...>
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\GeminiTranslationException;
use Studio\GeminiTranslator;
use Studio\JobManager;
use Studio\TranslationJobState;
use Studio\TranslationRunner;
use Studio\VttParser;

$opts = getopt('', [
    'master_vtt:',
    'status_file:',
    'source_lang:',
    'job_dir:',
    'target_langs:',
]);

$masterVtt  = $opts['master_vtt']   ?? '';
$statusFile = $opts['status_file']  ?? '';
$sourceLang = $opts['source_lang']  ?? '';
$jobDir     = $opts['job_dir']      ?? '';
$targetLangsRaw = $opts['target_langs'] ?? '';

if ($masterVtt === '' || $statusFile === '' || $sourceLang === '' || $jobDir === '' || $targetLangsRaw === '') {
    fwrite(STDERR, "Missing required arguments.\n");
    exit(1);
}

$targetLangs = array_filter(array_map('trim', explode(',', $targetLangsRaw)));
if ($targetLangs === []) {
    fwrite(STDERR, "No target languages provided.\n");
    exit(1);
}

$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not set.\n");
    exit(1);
}

$logFile = dirname(__DIR__, 2) . '/data/logs/studio.log';

$logger = static function (string $line) use ($logFile): void {
    @mkdir(dirname($logFile), 0775, true);
    file_put_contents($logFile, $line . "\n", FILE_APPEND | LOCK_EX);
};

// On any unexpected exit (exception, fatal error) mark unresolved languages as error
// so the UI does not poll indefinitely. Not called on SIGKILL — the shell fallback covers that.
register_shutdown_function(static function () use ($statusFile, $logger): void {
    if (!is_file($statusFile)) {
        return;
    }
    $data = json_decode(file_get_contents($statusFile) ?: '{}', true);
    if (!is_array($data)) {
        return;
    }
    $topStatus = $data['status'] ?? 'done';
    if (!in_array($topStatus, ['running', 'pending'], true)) {
        return;
    }
    $changed = false;
    foreach (array_keys($data['languages'] ?? []) as $lang) {
        $ls = $data['languages'][$lang]['status'] ?? '';
        if (in_array($ls, ['running', 'pending'], true)) {
            $data['languages'][$lang] = ['status' => 'error', 'message' => 'Error inesperat en la traducció'];
            $changed = true;
        }
    }
    if ($changed) {
        $data['status'] = 'done';
        file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        $logger(date('Y-m-d H:i:s') . ' [translate.php] Shutdown handler: marked unresolved languages as error');
    }
});

try {
    $jobManager = new JobManager(dirname($jobDir));
    $state      = new TranslationJobState($jobManager);
    $vttParser  = new VttParser();
    $translator = new GeminiTranslator(apiKey: $apiKey);
    $runner     = new TranslationRunner(
        jobManager: $jobManager,
        state: $state,
        vttParser: $vttParser,
        translator: $translator,
        logger: $logger,
    );

    $runner->run($masterVtt, $sourceLang, array_values($targetLangs));
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    $logger(date('Y-m-d H:i:s') . " [translate.php] FATAL: $msg");

    // Write fallback error to status file if it still shows running
    if (is_file($statusFile)) {
        $raw = file_get_contents($statusFile);
        $data = json_decode($raw ?: '', true);
        if (is_array($data) && ($data['status'] ?? '') === 'running') {
            $data['status'] = 'error';
            $data['message'] = $msg;
            file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        }
    }

    exit(1);
}
