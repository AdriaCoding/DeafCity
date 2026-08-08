<?php

namespace Studio\Actions;

use Studio\BulkIntakeHandler;
use Studio\Container;
use Studio\ShortenBulkZipBuilder;
use Studio\ShortenIntakeHandler;
use Studio\StudioHeader;
use Studio\SubtitleOutputBasename;
use Studio\TranscriptionPipelineStatus;
use Studio\TranslationJobState;
use Studio\VttToSrtConverter;

class ShortenAction
{
    public function __construct(private Container $c) {}

    public function intake(): never
    {
        $c = $this->c;
        $jobManager = $c->shortenJobManager();
        $bulkQueue = $c->shortenBulkIntakeQueue();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($bulkQueue->exists()) {
                header('Location: ?action=shorten-bulk-progress');
                exit;
            }
            if ($jobManager->exists()) {
                header('Location: ?action=resume-shorten-job');
                exit;
            }
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            $errors = [];
            $values = ['subtitle_language' => ''];
            extract($c->headerContext(StudioHeader::NAV_SHORTEN));
            require $this->view('shorten-intake.php');
            exit;
        }

        if ($bulkQueue->exists()) {
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            $errors = ['_form' => 'Ja hi ha un processament en massa en curs.'];
            $values = ['subtitle_language' => ''];
            extract($c->headerContext(StudioHeader::NAV_SHORTEN));
            require $this->view('shorten-intake.php');
            exit;
        }

        if ($this->isBulkUpload($_FILES)) {
            $handler = new BulkIntakeHandler(
                studioConfig: $c->studioConfig,
                jobManager: $jobManager,
                bulkQueue: $bulkQueue,
                launcher: $c->launcher,
                dataDir: $c->dataDir,
                allowAudio: false,
            );
            $result = $handler->handlePost($_POST, $_FILES);
            if (!empty($result['created'])) {
                header('Location: ?action=shorten-bulk-progress');
                exit;
            }
            $errors = $result['errors'];
            $values = $result['values'];
            $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
            extract($c->headerContext(StudioHeader::NAV_SHORTEN));
            require $this->view('shorten-intake.php');
            exit;
        }

        $files = $this->normalizeSingleUpload($_FILES);
        $handler = new ShortenIntakeHandler(
            studioConfig: $c->studioConfig,
            jobManager: $jobManager,
            launcher: $c->launcher,
            translationState: new TranslationJobState($jobManager),
        );
        $result = $handler->handlePost($_POST, $files);
        if (!empty($result['created'])) {
            header('Location: ?action=resume-shorten-job');
            exit;
        }
        $errors = $result['errors'];
        $values = $result['values'];
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        extract($c->headerContext(StudioHeader::NAV_SHORTEN));
        require $this->view('shorten-intake.php');
        exit;
    }

    public function resume(): never
    {
        $c = $this->c;
        $jobManager = $c->shortenJobManager();
        extract($c->headerContext(StudioHeader::NAV_SHORTEN));

        if (!$jobManager->exists()) {
            header('Location: ?action=shorten-intake');
            exit;
        }

        $job = $jobManager->read();
        if (($job['job_type'] ?? '') !== 'shorten') {
            header('Location: ?action=shorten-intake');
            exit;
        }

        $pipelineStatus = (new TranscriptionPipelineStatus($jobManager, alwaysSkipTranslation: true))->getState();
        $originalFilename = $job['original_filename'] ?? 'subtítols';
        $sourceLanguageLabel = $this->languageLabel((string) ($job['subtitle_language'] ?? ''));
        require $this->view('shorten-loading.php');
        exit;
    }

    public function cancel(): never
    {
        $this->c->shortenJobManager()->cancel();
        header('Location: ?action=shorten-intake');
        exit;
    }

    public function downloadSrt(): never
    {
        $jobManager = $this->c->shortenJobManager();
        if (!$jobManager->exists()) {
            http_response_code(404);
            exit;
        }
        $vttPath = $jobManager->draftVttPath();
        if (!is_file($vttPath)) {
            http_response_code(404);
            exit;
        }
        $job = $jobManager->read();
        header('Content-Type: application/x-subrip; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->buildSrtFilename($job) . '"');
        echo (new VttToSrtConverter())->convert($vttPath);
        exit;
    }

    public function bulkProgress(): never
    {
        $queue = $this->c->shortenBulkIntakeQueue();
        if (!$queue->exists()) {
            header('Location: ?action=shorten-intake');
            exit;
        }

        $snapshot = $queue->statusSnapshot();
        extract($this->c->headerContext(StudioHeader::NAV_SHORTEN));
        require $this->view('shorten-bulk-progress.php');
        exit;
    }

    public function bulkStatus(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        $queue = $this->c->shortenBulkIntakeQueue();
        if (!$queue->exists()) {
            echo json_encode(['items' => [], 'completed' => true]);
            exit;
        }
        echo json_encode($queue->statusSnapshot(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function bulkDownload(): never
    {
        $queue = $this->c->shortenBulkIntakeQueue();
        if (!$queue->exists()) {
            header('Location: ?action=shorten-intake');
            exit;
        }

        $snapshot = $queue->statusSnapshot();
        if (!$snapshot['completed']) {
            header('Location: ?action=shorten-bulk-progress');
            exit;
        }

        $entries = $queue->doneEntries();
        if ($entries === []) {
            $queue->destroy();
            header('Location: ?action=shorten-intake');
            exit;
        }

        $zip = (new ShortenBulkZipBuilder())->build($entries);
        $queue->destroy();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="polir-subtitols.zip"');
        header('Content-Length: ' . strlen($zip));
        echo $zip;
        exit;
    }

    private function isBulkUpload(array $files): bool
    {
        $upload = $files['intake_file'] ?? null;
        if (!$upload || !is_array($upload['name'] ?? null)) {
            return false;
        }

        return count($upload['name']) >= 2;
    }

    private function normalizeSingleUpload(array $files): array
    {
        $upload = $files['intake_file'] ?? null;
        if (!$upload || !is_array($upload['name'] ?? null)) {
            return $files;
        }

        if (count($upload['name']) === 1) {
            $files['intake_file'] = [
                'name' => $upload['name'][0],
                'type' => $upload['type'][0] ?? '',
                'tmp_name' => $upload['tmp_name'][0] ?? '',
                'error' => $upload['error'][0] ?? UPLOAD_ERR_NO_FILE,
                'size' => $upload['size'][0] ?? 0,
            ];
        }

        return $files;
    }

    /** @param array<string, mixed> $job */
    private function buildSrtFilename(array $job): string
    {
        $lang = (string) ($job['subtitle_language'] ?? '');
        return (new SubtitleOutputBasename())->srtFilename(
            $job['original_filename'] ?? 'subtitles',
            $lang,
            $lang,
        );
    }

    private function languageLabel(string $id): string
    {
        foreach ($this->c->studioConfig->getSubtitleLanguages() as $lang) {
            if (($lang['id'] ?? '') === $id) {
                return (string) ($lang['label'] ?? $id);
            }
        }
        return $id;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
