<?php

namespace Studio\Actions;

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
        $result = (new SubtitleLanguageAddHandler($this->c->studioConfig))->handle(
            (string) ($_POST['subtitle_language_code'] ?? ''),
            (string) ($_POST['subtitle_language_name'] ?? ''),
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
            'continguts-save-edition-label'          => $this->saveLabel('edition'),
            'continguts-save-sign-language-label'    => $this->saveLabel('sign_language'),
            'continguts-save-subtitle-language-label' => $this->saveLabel('subtitle_language'),
            'continguts-delete-edition'              => $this->deleteItem('edition'),
            'continguts-delete-sign-language'        => $this->deleteItem('sign_language'),
            'continguts-delete-subtitle-language'    => $this->deleteItem('subtitle_language'),
            default                               => (function () { http_response_code(404); exit; })(),
        };
    }

    private function continguts(): never
    {
        $this->guardNoActiveJob();
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
        $this->guardNoActiveJob();
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

    private function guardNoActiveJob(): void
    {
        if ($this->c->jobManager->exists()) {
            header('Location: ' . $this->c->baseUrl);
            exit;
        }
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
                'subtitle_language' => $this->c->studioConfig->updateSubtitleLanguageLabel($id, $label),
                default => throw new \InvalidArgumentException('Unknown label type.'),
            };
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
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
