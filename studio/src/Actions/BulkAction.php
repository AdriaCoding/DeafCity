<?php

namespace Studio\Actions;

use Studio\BulkZipBuilder;
use Studio\Container;

class BulkAction
{
    public function __construct(private Container $c) {}

    public function progress(): never
    {
        $queue = $this->c->bulkIntakeQueue();
        if (!$queue->exists()) {
            header('Location: ?action=transcription-intake');
            exit;
        }

        $snapshot = $queue->statusSnapshot();
        require $this->view('bulk-progress.php');
        exit;
    }

    public function status(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json');
        $queue = $this->c->bulkIntakeQueue();
        if (!$queue->exists()) {
            echo json_encode(['items' => [], 'completed' => true]);
            exit;
        }
        echo json_encode($queue->statusSnapshot(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function download(): never
    {
        $queue = $this->c->bulkIntakeQueue();
        if (!$queue->exists()) {
            header('Location: ?action=transcription-intake');
            exit;
        }

        $snapshot = $queue->statusSnapshot();
        if (!$snapshot['completed']) {
            header('Location: ?action=bulk-progress');
            exit;
        }

        $entries = $queue->doneEntries();
        if ($entries === []) {
            $queue->destroy();
            header('Location: ?action=transcription-intake');
            exit;
        }

        $zip = (new BulkZipBuilder($this->c->studioConfig))->build($entries);
        $queue->destroy();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="transcriptions.zip"');
        header('Content-Length: ' . strlen($zip));
        echo $zip;
        exit;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
