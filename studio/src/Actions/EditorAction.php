<?php

namespace Studio\Actions;

use Studio\CaptionFileIntegrityChecker;
use Studio\Container;
use Studio\SubtitleEditorHandler;
use Studio\TranslationJobState;
use Studio\VttParser;
use Studio\VttToSrtConverter;

class EditorAction
{
    public function __construct(private Container $c) {}

    public function handle(): never
    {
        $c = $this->c;
        if (!$c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ini_set('display_errors', '0');
            header('Content-Type: application/json');
            ob_start();
            try {
                $job = $c->jobManager->read();
                $lang = trim((string) ($_GET['lang'] ?? ''));
                $handler = new SubtitleEditorHandler(new VttParser(), new CaptionFileIntegrityChecker(), $c->jobManager);
                $result = $handler->handleRawJson((string) file_get_contents('php://input'), ['lang' => $lang !== '' ? $lang : null]);
                if ($result['ok'] && $lang !== '') {
                    (new TranslationJobState($c->jobManager))->markLanguageReviewed($lang);
                }
                if ($result['ok'] && !empty($result['translate'])) {
                    $c->translationCoordinator()->spawn($job['subtitle_language'] ?? 'es');
                }
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'errors' => ['Error del servidor: ' . $e->getMessage()]];
            }
            ob_end_clean();
            http_response_code($result['ok'] ? 200 : 422);
            echo json_encode($result);
            exit;
        }

        $job = $c->jobManager->read();
        $lang = trim((string) ($_GET['lang'] ?? ''));
        $vttPath = $lang !== '' ? $c->jobManager->draftVttPathForLang($lang) : $c->jobManager->draftVttPath();

        if (!is_file($vttPath)) {
            if (($job['intake_mode'] ?? '') === 'generate' && !$c->jobManager->hasDraftVtt()) {
                header('Location: ' . $c->baseUrl);
                exit;
            }
            header('Location: ?action=translation');
            exit;
        }

        $parsed = (new VttParser())->parse($vttPath);
        $cues = $parsed['cues'];
        $vimeoId = $job['vimeo_id'] ?? '';
        $editorMode = $lang !== '' ? 'translation' : 'master';
        $langLabel = $this->langLabel($lang);
        require $this->view('subtitle-editor.php');
        exit;
    }

    public function translationReview(): never
    {
        $c = $this->c;
        if (!$c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ini_set('display_errors', '0');
            header('Content-Type: application/json');
            ob_start();
            try {
                $lang = trim((string) ($_GET['lang'] ?? ''));
                if ($lang === '') {
                    $result = ['ok' => false, 'errors' => ['Idioma no especificat.']];
                } else {
                    $handler = new SubtitleEditorHandler(new VttParser(), new CaptionFileIntegrityChecker(), $c->jobManager);
                    $result = $handler->handleRawJson((string) file_get_contents('php://input'), ['lang' => $lang]);
                    if ($result['ok']) {
                        (new TranslationJobState($c->jobManager))->markLanguageReviewed($lang);
                    }
                }
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'errors' => ['Error del servidor: ' . $e->getMessage()]];
            }
            ob_end_clean();
            http_response_code($result['ok'] ? 200 : 422);
            echo json_encode($result);
            exit;
        }

        $lang = trim((string) ($_GET['lang'] ?? ''));
        if ($lang === '') {
            header('Location: ?action=translation');
            exit;
        }
        $translatedVttPath = $c->jobManager->draftVttPathForLang($lang);
        if (!is_file($translatedVttPath)) {
            header('Location: ?action=translation');
            exit;
        }
        $job = $c->jobManager->read();
        $vimeoId = $job['vimeo_id'] ?? '';
        $vttParser = new VttParser();
        $translatedCues = $vttParser->parse($translatedVttPath)['cues'];
        $masterCues = [];
        $masterVttPath = $c->jobManager->draftVttPath();
        if (is_file($masterVttPath)) {
            $masterCues = $vttParser->parse($masterVttPath)['cues'];
        }
        $langLabel = $this->langLabel($lang);
        require $this->view('translation-review.php');
        exit;
    }

    public function downloadVtt(): never
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
        header('Content-Type: text/vtt; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->buildDownloadFilename($job, $lang, 'vtt') . '"');
        readfile($vttPath);
        exit;
    }

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
        header('Content-Disposition: attachment; filename="' . $this->buildDownloadFilename($job, $lang, 'srt') . '"');
        echo (new VttToSrtConverter())->convert($vttPath);
        exit;
    }

    private function buildDownloadFilename(array $job, string $lang, string $ext): string
    {
        $isTranscription = ($job['job_type'] ?? '') === 'transcription';
        $base = $isTranscription
            ? ($job['original_filename'] ?? 'transcription')
            : ($job['vimeo_id'] ?? 'draft');

        if ($isTranscription) {
            $code = $lang !== '' ? $lang : ($job['subtitle_language'] ?? '');
            return $base . ($code !== '' ? '_' . $code : '') . '.' . $ext;
        }

        return $base . ($lang !== '' ? '_' . $lang : '') . '.' . $ext;
    }

    private function langLabel(string $lang): string
    {
        if ($lang === '') {
            return '';
        }
        foreach ($this->c->studioConfig->getSubtitleLanguages() as $language) {
            if (($language['id'] ?? '') === $lang) {
                return $language['label'] ?? $lang;
            }
        }
        return $lang;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
