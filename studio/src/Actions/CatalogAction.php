<?php

namespace Studio\Actions;

use Studio\CaptionDeleteHandler;
use Studio\CaptionReplaceHandler;
use Studio\CaptionUploadHandler;
use Studio\CatalogTagPool;
use Studio\CatalogVideoAddHandler;
use Studio\Container;
use Studio\EditionAddHandler;
use Studio\SignLanguageAddHandler;
use Studio\SubtitleLanguageAddHandler;
use Studio\VideoEditHandler;
use Studio\VimeoIdParser;
use Studio\VimeoVideoResolver;

class CatalogAction
{
    public function __construct(private Container $c) {}

    public function addSignLanguage(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new SignLanguageAddHandler($this->c->studioConfig))->handle(
            (string) ($_POST['sign_language_code'] ?? ''),
            (string) ($_POST['sign_language_qualifier'] ?? ''),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addEdition(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new EditionAddHandler($this->c->studioConfig))->handle(
            (string) ($_POST['edition_city'] ?? ''),
            (string) ($_POST['edition_year'] ?? ''),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addSubtitleLanguage(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new SubtitleLanguageAddHandler(
            $this->c->studioConfig,
            new \Studio\Iso639LanguageRegistry(__DIR__ . '/../../js/iso-639-3.json'),
            new \Studio\VimeoLocaleRegistry(__DIR__ . '/../../js/vimeo-texttrack-locales.json'),
        ))->handle(
            (string) ($_POST['subtitle_language_code'] ?? ''),
            (string) ($_POST['subtitle_language_name'] ?? ''),
            (string) ($_POST['subtitle_language_vimeo_code'] ?? ''),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function handle(string $action): never
    {
        match ($action) {
            'continguts'                          => $this->continguts(),
            'continguts-video'                    => $this->contingutsVideo(),
            'continguts-resolve-vimeo'            => $this->resolveVimeo(),
            'continguts-add-video'                => $this->addVideo(),
            'continguts-save-video'               => $this->saveVideo(),
            'continguts-set-master-caption'       => $this->setMasterCaption(),
            'continguts-download-caption-vtt'     => $this->downloadCaption('vtt'),
            'continguts-download-caption-srt'     => $this->downloadCaption('srt'),
            'continguts-save-edition-label'          => $this->saveLabel('edition'),
            'continguts-save-sign-language-label'    => $this->saveLabel('sign_language'),
            'continguts-delete-edition'              => $this->deleteItem('edition'),
            'continguts-delete-sign-language'        => $this->deleteItem('sign_language'),
            'continguts-delete-subtitle-language'    => $this->deleteItem('subtitle_language'),
            'continguts-delete-caption'              => $this->deleteCaption(),
            'continguts-replace-caption'             => $this->replaceCaption(),
            'continguts-caption-review'              => $this->captionReview(),
            default                               => (function () { http_response_code(404); exit; })(),
        };
    }

    private function continguts(): never
    {
        $c = $this->c;
        $catalogFilePath = $c->dataDir . '/catalog.json';
        $catalogData = is_file($catalogFilePath)
            ? (json_decode((string) file_get_contents($catalogFilePath), true) ?? ['videos' => []])
            : ['videos' => []];
        $catalogVideos = $catalogData['videos'] ?? [];
        $editions = $c->studioConfig->getEditions();
        $signLanguages = $c->studioConfig->getSignLanguages();
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        $catalogEditor = $c->catalogEditor();
        $referencedEditionIds = $catalogEditor->getReferencedEditionIds();
        $referencedSignLanguageIds = $catalogEditor->getReferencedSignLanguageIds();
        $referencedSubtitleLanguageIds = $catalogEditor->getReferencedSubtitleLanguageIds();
        require $this->view('continguts.php');
        exit;
    }

    private function contingutsVideo(): never
    {
        $c = $this->c;
        $vimeoId = trim((string) ($_GET['vimeo_id'] ?? ''));
        if ($vimeoId === '') {
            http_response_code(404);
            require $this->view('continguts-video-not-found.php');
            exit;
        }
        $video = $c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            http_response_code(404);
            require $this->view('continguts-video-not-found.php');
            exit;
        }
        $catalogFilePath = $c->dataDir . '/catalog.json';
        $catalogTags = (new CatalogTagPool($catalogFilePath))->getTagsSortedAlphabetically();
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        require $this->view('continguts-video.php');
        exit;
    }

    private function resolveVimeo(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $input = trim((string) ($_POST['vimeo_input'] ?? ''));
        $result = (new VimeoVideoResolver(
            new VimeoIdParser(),
            $this->c->vimeoClient(),
            $this->c->catalogEditor(),
        ))->resolve($input);
        if (!$result['ok']) {
            http_response_code(422);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function addVideo(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $signLanguage = trim((string) ($_POST['sign_language'] ?? ''));
        $edition = trim((string) ($_POST['edition'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));

        $result = (new CatalogVideoAddHandler(
            $this->c->vimeoClient(),
            $this->c->catalogEditor(),
        ))->handle($vimeoId, $signLanguage, $edition, $title);

        if (!$result['ok']) {
            http_response_code(422);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $editionLabel = $edition;
        foreach ($this->c->studioConfig->getEditions() as $ed) {
            if (($ed['id'] ?? '') === $edition) {
                $editionLabel = $ed['label'] ?? $edition;
                break;
            }
        }

        $result['edition_label'] = $editionLabel;
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function downloadCaption(string $format): never
    {
        $vimeoId = trim((string) ($_GET['vimeo_id'] ?? ''));
        $lang    = trim((string) ($_GET['lang'] ?? ''));
        if ($vimeoId === '' || $lang === '') {
            http_response_code(400);
            exit;
        }

        $video = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            http_response_code(404);
            exit;
        }

        $captionFile = null;
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['lang'] ?? '') === $lang) {
                $captionFile = $caption['file'] ?? null;
                break;
            }
        }

        if ($captionFile === null) {
            http_response_code(404);
            exit;
        }

        $vttPath = $this->c->dataDir . '/captions/' . $captionFile;
        if (!is_file($vttPath)) {
            http_response_code(404);
            exit;
        }

        $basename = pathinfo($captionFile, PATHINFO_FILENAME);

        if ($format === 'srt') {
            header('Content-Type: application/x-subrip; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $basename . '.srt"');
            echo (new \Studio\VttToSrtConverter())->convert($vttPath);
        } else {
            header('Content-Type: text/vtt; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $basename . '.vtt"');
            readfile($vttPath);
        }
        exit;
    }

    private function saveVideo(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $videoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $tags = is_array($_POST['tags'] ?? null) ? array_values(array_filter(array_map('trim', $_POST['tags']))) : [];
        if ($videoId === '' || $title === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Falten camps obligatoris.']);
            exit;
        }

        $captionUploads = $this->parseCaptionUploads();
        if ($captionUploads['error'] !== null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $captionUploads['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = (new VideoEditHandler($this->c->vimeoClient(), $this->c->catalogEditor()))
            ->handle($videoId, $title, $tags);

        if (!$result['ok']) {
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($captionUploads['uploads'] !== []) {
            $captionResult = (new CaptionUploadHandler(
                $this->c->vimeoClient(),
                $this->c->catalogEditor(),
                $this->c->studioConfig,
                $this->c->dataDir . '/captions',
            ))->handle($videoId, $captionUploads['uploads']);

            if (!$captionResult['ok']) {
                http_response_code(422);
                echo json_encode([
                    'ok' => false,
                    'error' => $captionResult['error'] ?? 'Error en pujar els subtítols.',
                    'vimeoWarning' => $result['vimeoWarning'],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $captionWarnings = $captionResult['vimeoWarnings'];
            if ($captionWarnings !== []) {
                $labels = implode(', ', $captionWarnings);
                $captionVimeoWarning = 'Els subtítols s\'han desat localment, però Vimeo no s\'ha actualitzat per: ' . $labels;
                $result['vimeoWarning'] = $result['vimeoWarning'] !== null
                    ? $result['vimeoWarning'] . ' ' . $captionVimeoWarning
                    : $captionVimeoWarning;
            }

            if (isset($captionResult['captions'])) {
                $result['captions'] = $captionResult['captions'];
                $result['masterCaptionLang'] = $captionResult['masterCaptionLang'] ?? '';
            }
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return array{uploads: list<array{lang: string, tmpPath: string, originalName: string}>, error: ?string} */
    private function parseCaptionUploads(): array
    {
        $files = $_FILES['caption_file'] ?? null;
        if ($files === null || !isset($files['name']) || !is_array($files['name'])) {
            return ['uploads' => [], 'error' => null];
        }

        $langs = is_array($_POST['caption_lang'] ?? null) ? $_POST['caption_lang'] : [];
        $uploads = [];

        foreach ($files['name'] as $i => $name) {
            if ($name === '' || ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (($files['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return ['uploads' => [], 'error' => 'No s\'ha pogut pujar un fitxer de subtítols.'];
            }
            $uploads[] = [
                'lang' => trim((string) ($langs[$i] ?? '')),
                'tmpPath' => (string) ($files['tmp_name'][$i] ?? ''),
                'originalName' => (string) $name,
            ];
        }

        return ['uploads' => $uploads, 'error' => null];
    }

    private function setMasterCaption(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $lang = trim((string) ($_POST['lang'] ?? ''));
        if ($vimeoId === '' || $lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Falten camps obligatoris.']);
            exit;
        }
        try {
            $this->c->catalogEditor()->setMasterCaptionLang($vimeoId, $lang);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function saveLabel(string $type): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $id = trim((string) ($_POST['id'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($id === '' || $label === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Falten camps obligatoris.']);
            exit;
        }
        try {
            match ($type) {
                'edition' => $this->c->studioConfig->updateEditionLabel($id, $label),
                'sign_language' => $this->c->studioConfig->updateSignLanguageLabel($id, $label),
                default => throw new \InvalidArgumentException('Unknown label type.'),
            };
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function deleteCaption(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $lang = trim((string) ($_POST['lang'] ?? ''));
        if ($vimeoId === '' || $lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Falten camps obligatoris.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = (new CaptionDeleteHandler(
            $this->c->catalogEditor(),
            $this->c->dataDir . '/captions',
        ))->handle($vimeoId, $lang);

        if (!$result['ok']) {
            http_response_code(422);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function replaceCaption(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $lang = trim((string) ($_POST['lang'] ?? ''));
        if ($vimeoId === '' || $lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Falten camps obligatoris.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $file = $_FILES['caption_file'] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['name'] ?? '') === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'No s\'ha pogut pujar el fitxer de subtítols.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = (new CaptionReplaceHandler(
            $this->c->catalogEditor(),
            new CaptionUploadHandler(
                $this->c->vimeoClient(),
                $this->c->catalogEditor(),
                $this->c->studioConfig,
                $this->c->dataDir . '/captions',
            ),
        ))->handle($vimeoId, $lang, [
            'lang' => $lang,
            'tmpPath' => (string) ($file['tmp_name'] ?? ''),
            'originalName' => (string) ($file['name'] ?? ''),
        ]);

        if (!$result['ok']) {
            http_response_code(422);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function captionReview(): never
    {
        $vimeoId = trim((string) ($_GET['vimeo_id'] ?? ''));
        $lang = trim((string) ($_GET['lang'] ?? ''));
        if ($vimeoId === '' || $lang === '') {
            $this->renderEmbedError('Falten paràmetres obligatoris.');
        }

        $video = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            $this->renderEmbedError('Vídeo no trobat al catàleg.');
        }

        $captionEntry = null;
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['lang'] ?? '') === $lang) {
                $captionEntry = $caption;
                break;
            }
        }
        if ($captionEntry === null) {
            $this->renderEmbedError("Subtítol '$lang' no trobat per a aquest vídeo.");
        }

        $captionsDir = $this->c->dataDir . '/captions';
        $vttPath = $captionsDir . '/' . ($captionEntry['file'] ?? '');
        if (!is_file($vttPath)) {
            $this->renderEmbedError('Fitxer de subtítols no trobat al servidor.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ini_set('display_errors', '0');
            header('Content-Type: application/json');
            ob_start();
            try {
                $decoded = json_decode((string) file_get_contents('php://input'), true);
                if (!is_array($decoded) || !isset($decoded['cues']) || !is_array($decoded['cues'])) {
                    $result = ['ok' => false, 'errors' => ['Cos de la sol·licitud no vàlid.']];
                } else {
                    $handler = new \Studio\SubtitleEditorHandler(
                        new \Studio\VttParser(),
                        new \Studio\CaptionFileIntegrityChecker(),
                        $this->c->jobManager,
                    );
                    $result = $handler->handleForFilePath($vttPath, $decoded['cues']);
                }
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'errors' => ['Error del servidor: ' . $e->getMessage()]];
            }
            ob_end_clean();
            http_response_code($result['ok'] ? 200 : 422);
            echo json_encode($result);
            exit;
        }

        $vttParser = new \Studio\VttParser();
        $translatedCues = $vttParser->parse($vttPath)['cues'];
        $masterLang = $video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? '');
        $masterCues = $translatedCues;
        if ($lang !== $masterLang) {
            $masterCues = [];
            $masterFile = null;
            foreach ($video['captions'] ?? [] as $caption) {
                if (($caption['lang'] ?? '') === $masterLang) {
                    $masterFile = $caption['file'] ?? null;
                    break;
                }
            }
            if ($masterFile !== null) {
                $masterPath = $captionsDir . '/' . $masterFile;
                if (is_file($masterPath)) {
                    $masterCues = $vttParser->parse($masterPath)['cues'];
                }
            }
        }

        $langLabel = $this->langLabel($lang);
        $postSaveRedirect = '?action=continguts-video&vimeo_id=' . rawurlencode($vimeoId);
        require $this->view('continguts-caption-editor.php');
        exit;
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

    private function renderEmbedError(string $message): never
    {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="ca"><head><meta charset="UTF-8"><title>Error</title>';
        echo '<style>body{background:#0a0a0a;color:#e05555;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:2rem;text-align:center;}</style>';
        echo '</head><body><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p></body></html>';
        exit;
    }

    private function deleteItem(string $type): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'ID no especificat.']);
            exit;
        }
        try {
            match ($type) {
                'edition' => $this->c->studioConfig->removeEdition($id, $this->c->catalogEditor()),
                'sign_language' => $this->c->studioConfig->removeSignLanguage($id, $this->c->catalogEditor()),
                'subtitle_language' => $this->c->studioConfig->removeSubtitleLanguage($id, $this->c->catalogEditor()),
                default => throw new \InvalidArgumentException('Unknown delete type.'),
            };
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
