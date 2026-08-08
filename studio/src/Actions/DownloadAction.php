<?php

namespace Studio\Actions;

use Studio\Container;
use Studio\SubtitleOutputBasename;
use Studio\VttToSrtConverter;

class DownloadAction
{
    public function __construct(private Container $c) {}

    public function downloadSrt(): never
    {
        $c = $this->c;
        if (!$c->jobManager->exists()) {
            http_response_code(404);
            exit;
        }
        $lang = trim((string) ($_GET['lang'] ?? ''));
        $vttPath = $lang !== '' ? $c->jobManager->draftVttPathForLang($lang) : $c->jobManager->draftVttPath();
        if (!is_file($vttPath)) {
            http_response_code(404);
            exit;
        }
        $job = $c->jobManager->read();
        header('Content-Type: application/x-subrip; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->buildSrtFilename($job, $lang) . '"');
        echo (new VttToSrtConverter())->convert($vttPath);
        exit;
    }

    /** @param array<string, mixed> $job */
    private function buildSrtFilename(array $job, string $lang): string
    {
        return (new SubtitleOutputBasename())->transcriptionDownloadFilename(
            $job['original_filename'] ?? 'transcription',
            $job['subtitle_language'] ?? '',
            $lang,
            'srt',
        );
    }
}
