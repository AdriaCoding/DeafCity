<?php
/**
 * CLI revision entry point.
 *
 * Usage:
 *   GEMINI_API_KEY=<key> php revise.php \
 *     --draft_path <path> \
 *     --revision_status <path> \
 *     --source_lang <lang> \
 *     --job_dir <path>
 */

declare(strict_types=1);


// CLI only. Refuse to run under a web server even if the directory deny rule
// is ever lost: these scripts spend API budget and mutate the catalog.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CaptionReader;
use Studio\GeminiRevisionException;
use Studio\GeminiReviser;
use Studio\SrtParser;
use Studio\SrtValidator;

$opts = getopt('', [
    'draft_path:',
    'revision_status:',
    'source_lang:',
    'job_dir:',
    'dialect_name:',
]);

$draftPath      = $opts['draft_path']       ?? '';
$revisionStatus = $opts['revision_status']  ?? '';
$sourceLang     = $opts['source_lang']      ?? '';
$jobDir         = $opts['job_dir']          ?? '';
$dialectName    = $opts['dialect_name']     ?? '';

if ($draftPath === '' || $revisionStatus === '' || $sourceLang === '' || $jobDir === '') {
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
    $logger(date('Y-m-d H:i:s') . " [revise.php] Starting revision source={$sourceLang} draft={$draftPath}");

    $parsed = (new CaptionReader())->read($draftPath);
    if ($parsed['cues'] === []) {
        throw new GeminiRevisionException('No s\'ha pogut llegir el fitxer de subtítols.');
    }

    $srtParser = new SrtParser();
    $reviser = new GeminiReviser(apiKey: $apiKey);

    /*
     * canonicalize() rather than a bare parse: generative output drifts —
     * dropped blank lines, restarted numbering, the odd WebVTT habit — and all
     * of that is recoverable without failing the whole revision.
     */
    $revised = $srtParser->canonicalize($reviser->revise($srtParser->write($parsed['cues']), $sourceLang, $dialectName));

    $tmpPath = $jobDir . '/.revision_tmp.srt';
    if (file_put_contents($tmpPath, $revised) === false) {
        throw new GeminiRevisionException('No s\'ha pogut desar els subtítols revisats temporalment.');
    }

    try {
        (new SrtValidator())->validate($tmpPath, 'draft.srt');
    } catch (\InvalidArgumentException $e) {
        unlink($tmpPath);
        throw new GeminiRevisionException('Subtítols revisats no vàlids: ' . $e->getMessage());
    }

    if (file_put_contents($draftPath, $revised) === false) {
        unlink($tmpPath);
        throw new GeminiRevisionException('No s\'ha pogut sobreescriure el fitxer de subtítols.');
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
