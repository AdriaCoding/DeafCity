<?php

/**
 * Batch-translate every catalog video's captions into any subtitle language
 * currently missing from that video — e.g. after adding a new studio
 * language, so the whole catalog gets backfilled instead of requiring one
 * "Genera subtítols" click per video. Videos with no master caption defined
 * are skipped: there is nothing to translate from.
 *
 * Usage: php studio/scripts/batch_translate_captions.php [--status-file /path/to/status.json]
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\CaptionFilename;
use Studio\CaptionPublication;
use Studio\CatalogEditor;
use Studio\GeminiTranslator;
use Studio\JobManager;
use Studio\StudioConfig;
use Studio\TranslationJobState;
use Studio\TranslationRunner;
use Studio\VimeoClient;
use Studio\SrtParser;

$statusFile = null;
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--status-file' && isset($argv[$i + 1])) {
        $statusFile = $argv[$i + 1];
        break;
    }
}

function writeStatus(
    string $status,
    int $processed,
    int $translated,
    int $skipped,
    int $total,
    ?string $statusFile,
    string $lastMessage = '',
): void {
    if ($statusFile === null) {
        return;
    }
    file_put_contents($statusFile, json_encode([
        'status'       => $status,
        'processed'    => $processed,
        'translated'   => $translated,
        'skipped'      => $skipped,
        'total'        => $total,
        'last_message' => $lastMessage,
    ]));
}

$dataDir     = realpath(__DIR__ . '/../../data');
$catalogPath = $dataDir !== false ? $dataDir . '/catalog.json' : '';
$captionsDir = $dataDir !== false ? $dataDir . '/captions' : '';
$configPath  = $dataDir !== false ? $dataDir . '/studio-config.json' : '';

if ($dataDir === false || !is_file($catalogPath) || !is_dir($captionsDir) || !is_file($configPath)) {
    fwrite(STDERR, "catalog.json, captions dir, or studio-config.json not found.\n");
    exit(1);
}

$apiKey = getenv('GEMINI_API_KEY') ?: (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not set.\n");
    exit(1);
}

$logFile = $dataDir . '/logs/studio.log';
$rawLog = static function (string $line) use ($logFile): void {
    @mkdir(dirname($logFile), 0775, true);
    file_put_contents($logFile, $line . "\n", FILE_APPEND | LOCK_EX);
    echo $line . "\n";
};
$logger = static function (string $line) use ($rawLog): void {
    $rawLog(date('Y-m-d H:i:s') . ' [batch_translate_captions.php] ' . $line);
};

$catalogEditor   = new CatalogEditor($catalogPath);
$studioConfig    = new StudioConfig($configPath);
$vimeoClient     = new VimeoClient(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);
$srtParser       = new SrtParser();
$captionFilename = new CaptionFilename();

$langLabels = [];
foreach ($studioConfig->getSubtitleLanguages() as $language) {
    $langLabels[(string) ($language['id'] ?? '')] = (string) ($language['label'] ?? '');
}

$videos     = $catalogEditor->getAllVideos();
$total      = count($videos);
$processed  = 0;
$translated = 0;
$skipped    = 0;

$logger("Starting batch translation for $total video(s)");
writeStatus('running', 0, 0, 0, $total, $statusFile, "Començant… ($total vídeos)");

foreach ($videos as $entry) {
    $processed++;
    $vimeoId = (string) ($entry['vimeo_id'] ?? '');
    $prefix = "[$processed/$total] $vimeoId";

    if ($vimeoId === '') {
        $msg = "$prefix SKIP (no vimeo_id)";
        $logger($msg);
        $skipped++;
        writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);
        continue;
    }

    $masterLang = (string) ($entry['master_caption_lang'] ?? ($entry['captions'][0]['lang'] ?? ''));
    $masterFile = null;
    foreach ($entry['captions'] ?? [] as $caption) {
        if (($caption['lang'] ?? '') === $masterLang) {
            $masterFile = $caption['file'] ?? null;
            break;
        }
    }
    if ($masterLang === '' || $masterFile === null) {
        $msg = "$prefix SKIP (no master caption)";
        $logger($msg);
        $skipped++;
        writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);
        continue;
    }

    $masterVttPath = $captionsDir . '/' . $masterFile;
    if (!is_file($masterVttPath)) {
        $msg = "$prefix SKIP (master VTT missing on disk: $masterVttPath)";
        $logger($msg);
        $skipped++;
        writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);
        continue;
    }

    $existingLangs = array_column($entry['captions'] ?? [], 'lang');
    $targets = [];
    foreach ($studioConfig->getSubtitleLanguages() as $lang) {
        $id = (string) ($lang['id'] ?? '');
        if ($id !== '' && $id !== $masterLang && !in_array($id, $existingLangs, true)) {
            $targets[] = $id;
        }
    }

    if ($targets === []) {
        $msg = "$prefix SKIP (nothing missing)";
        $logger($msg);
        $skipped++;
        writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);
        continue;
    }

    $jobDir = $dataDir . '/caption-translation/' . $vimeoId;
    if (!is_dir($jobDir . '/current') && !mkdir($jobDir . '/current', 0775, true) && !is_dir($jobDir . '/current')) {
        $msg = "$prefix SKIP (could not create job dir)";
        $logger($msg);
        $skipped++;
        writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);
        continue;
    }

    $msg = "$prefix translating " . implode(',', $targets) . " from $masterLang";
    $logger($msg);
    writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);

    $jobManager = new JobManager($jobDir);
    $state      = new TranslationJobState($jobManager);
    $state->initiate($targets, $masterLang);

    $translator = new GeminiTranslator(apiKey: $apiKey);
    $runner = new TranslationRunner(
        jobManager: $jobManager,
        state: $state,
        srtParser: $srtParser,
        translator: $translator,
        logger: $rawLog,
    );

    $runner->run($masterVttPath, $masterLang, $targets);

    $finalState  = $state->read();
    $newCaptions = [];
    $savedLangs  = [];
    $errorLangs  = [];
    foreach ($finalState['languages'] ?? [] as $lang => $langEntry) {
        $langStr = (string) $lang;
        if (($langEntry['status'] ?? '') === 'error') {
            $errorLangs[] = $langStr;
            continue;
        }
        if (($langEntry['status'] ?? '') !== 'done') {
            continue;
        }

        $srcPath      = $jobDir . '/current/draft_' . $langStr . '.srt';
        $destFilename = $captionFilename->forVideo((string) ($entry['title'] ?? $vimeoId), $langStr);
        $destPath     = $captionsDir . '/' . $destFilename;

        if (!is_file($srcPath) || !copy($srcPath, $destPath)) {
            $errorLangs[] = $langStr;
            continue;
        }

        $newCaptions[] = [
            'lang'  => $langStr,
            'label' => $langLabels[$langStr] ?? $langStr,
            'file'  => $destFilename,
        ];
        $savedLangs[] = $langStr;
    }

    if ($newCaptions !== []) {
        try {
            (new CaptionPublication($catalogEditor, $vimeoClient, $captionsDir))
                ->publish($vimeoId, $newCaptions);
        } catch (\Throwable $e) {
            $errorLangs = array_merge($errorLangs, $savedLangs);
            $savedLangs = [];
            $logger("$prefix PUBLISH FAILED: " . $e->getMessage());
        }
    }

    $state->markSaved($savedLangs, $errorLangs);
    $translated += count($savedLangs);

    $msg = "$prefix saved=" . implode(',', $savedLangs) . ' errors=' . implode(',', $errorLangs);
    $logger($msg);
    writeStatus('running', $processed, $translated, $skipped, $total, $statusFile, $msg);
}

$doneMsg = "Fet. $processed/$total vídeos processats, $translated subtítols traduïts, $skipped vídeos omesos.";
writeStatus('done', $processed, $translated, $skipped, $total, $statusFile, $doneMsg);
$logger($doneMsg);
