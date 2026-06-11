<?php
/**
 * CLI revision entry point.
 *
 * Usage:
 *   GEMINI_API_KEY=<key> php revise.php \
 *     --vtt_path <path> \
 *     --revision_status <path> \
 *     --source_lang <lang> \
 *     --job_dir <path>
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\GeminiRevisionException;
use Studio\GeminiReviser;
use Studio\VttParser;
use Studio\WebVttValidator;

$opts = getopt('', [
    'vtt_path:',
    'revision_status:',
    'source_lang:',
    'job_dir:',
]);

$vttPath        = $opts['vtt_path']         ?? '';
$revisionStatus = $opts['revision_status']  ?? '';
$sourceLang     = $opts['source_lang']      ?? '';
$jobDir         = $opts['job_dir']          ?? '';

if ($vttPath === '' || $revisionStatus === '' || $sourceLang === '' || $jobDir === '') {
    fwrite(STDERR, "Missing required arguments.\n");
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

$writeRevisionStatus = static function (array $data) use ($revisionStatus): void {
    file_put_contents(
        $revisionStatus,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
    );
};

register_shutdown_function(static function () use ($revisionStatus, $logger, $writeRevisionStatus): void {
    if (!is_file($revisionStatus)) {
        return;
    }
    $data = json_decode(file_get_contents($revisionStatus) ?: '{}', true);
    if (!is_array($data)) {
        return;
    }
    $status = $data['status'] ?? '';
    if (!in_array($status, ['running', 'pending'], true)) {
        return;
    }
    $writeRevisionStatus([
        'status' => 'error',
        'message' => 'Error inesperat en la revisió',
    ]);
    $logger(date('Y-m-d H:i:s') . ' [revise.php] Shutdown handler: marked revision as error');
});

try {
    $writeRevisionStatus(['status' => 'running']);
    $logger(date('Y-m-d H:i:s') . " [revise.php] Starting revision source={$sourceLang} vtt={$vttPath}");

    $rawVtt = file_get_contents($vttPath);
    if ($rawVtt === false || $rawVtt === '') {
        throw new GeminiRevisionException('No s\'ha pogut llegir el fitxer VTT.');
    }

    $reviser = new GeminiReviser(apiKey: $apiKey);
    $revisedVtt = (new VttParser())->canonicalize($reviser->revise($rawVtt, $sourceLang));

    $tmpPath = $jobDir . '/.revision_tmp.vtt';
    if (file_put_contents($tmpPath, $revisedVtt) === false) {
        throw new GeminiRevisionException('No s\'ha pogut desar el VTT revisat temporalment.');
    }

    try {
        (new WebVttValidator())->validate($tmpPath, 'draft.vtt');
    } catch (\InvalidArgumentException $e) {
        unlink($tmpPath);
        throw new GeminiRevisionException('VTT revisat no vàlid: ' . $e->getMessage());
    }

    if (file_put_contents($vttPath, $revisedVtt) === false) {
        unlink($tmpPath);
        throw new GeminiRevisionException('No s\'ha pogut sobreescriure draft.vtt.');
    }
    unlink($tmpPath);

    $writeRevisionStatus(['status' => 'done']);
    $logger(date('Y-m-d H:i:s') . ' [revise.php] Revision complete');
} catch (\Throwable $e) {
    $msg = $e->getMessage();
    $logger(date('Y-m-d H:i:s') . " [revise.php] FATAL: $msg");
    $writeRevisionStatus(['status' => 'error', 'message' => $msg]);
    exit(1);
}
