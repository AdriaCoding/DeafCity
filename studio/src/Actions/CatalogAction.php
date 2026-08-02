<?php

namespace Studio\Actions;

use Studio\ActiveJobBanner;
use Studio\CaptionDeleteHandler;
use Studio\CaptionReplaceHandler;
use Studio\CaptionUploadHandler;
use Studio\CatalogIntakeAddHandler;
use Studio\Container;
use Studio\EditionAddHandler;
use Studio\JobManager;
use Studio\SignLanguageAddHandler;
use Studio\SubtitleLanguageAddHandler;
use Studio\StudioHeader;
use Studio\TranslationJobState;
use Studio\TypologyAddHandler;
use Studio\VideoEditHandler;
use Studio\VideoVisibilityHandler;
use Studio\VimeoIdParser;
use Studio\VimeoVideoResolver;

class CatalogAction
{
    public function __construct(private Container $c) {}

    public function addSignLanguage(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new SignLanguageAddHandler($this->c->configMutation()))->handle(
            (string) ($_POST['sign_language_code'] ?? ''),
            (string) ($_POST['sign_language_qualifier'] ?? ''),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addEdition(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new EditionAddHandler($this->c->configMutation()))->handle(
            (string) ($_POST['edition_city'] ?? ''),
            (string) ($_POST['edition_year'] ?? ''),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addTypology(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new TypologyAddHandler($this->c->configMutation()))->handle(
            (string) ($_POST['typology_label'] ?? ''),
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addSubtitleLanguage(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $result = (new SubtitleLanguageAddHandler(
            $this->c->configMutation(),
            new \Studio\Iso639LanguageRegistry(__DIR__ . '/../../js/iso-639-3.json'),
            new \Studio\VimeoLocaleRegistry(__DIR__ . '/../../js/vimeo-texttrack-locales.json'),
        ))->handle(
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
            'continguts-set-video-invisible'      => $this->setVideoInvisible(),
            'continguts-set-master-caption'       => $this->setMasterCaption(),
            'continguts-download-caption-vtt'     => $this->downloadCaption('vtt'),
            'continguts-download-caption-srt'     => $this->downloadCaption('srt'),
            'continguts-download-data-zip'        => $this->downloadDataZip(),
            'continguts-save-edition-label'          => $this->saveLabel('edition'),
            'continguts-save-sign-language-label'    => $this->saveLabel('sign_language'),
            'continguts-save-typology-label'         => $this->saveLabel('typology'),
            'continguts-delete-edition'              => $this->deleteItem('edition'),
            'continguts-delete-sign-language'        => $this->deleteItem('sign_language'),
            'continguts-delete-subtitle-language'    => $this->deleteItem('subtitle_language'),
            'continguts-delete-typology'             => $this->deleteItem('typology'),
            'continguts-delete-caption'              => $this->deleteCaption(),
            'continguts-replace-caption'             => $this->replaceCaption(),
            'continguts-caption-review'              => $this->captionReview(),
            'continguts-caption-translate-start'     => $this->captionTranslateStart(),
            'continguts-caption-translate-status'    => $this->captionTranslateStatus(),
            'continguts-caption-translate-retry'     => $this->captionTranslateRetry(),
            'continguts-sync-from-sheet'             => $this->syncFromSheet(),
            'continguts-batch-translate'             => $this->batchTranslateStart(),
            'continguts-batch-translate-status'      => $this->batchTranslateStatus(),
            default                               => (function () { http_response_code(404); exit; })(),
        };
    }

    /** @param array{syncStatus?: array|null, isSyncing?: bool}|null $syncContext */
    public function renderContinguts(?array $syncContext = null): never
    {
        foreach ($this->contingutsContext($syncContext) as $name => $value) {
            $$name = $value;
        }
        require $this->view('continguts.php');
        exit;
    }

    /** @return array<string, mixed> */
    public function contingutsContext(?array $syncContext = null): array
    {
        $c = $this->c;
        $editions = $c->studioConfig->getEditions();
        $signLanguages = $c->studioConfig->getSignLanguages();
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        $typologies = $c->studioConfig->getTypologies();
        $catalogEditor = $c->catalogEditor();
        $catalogVideos = $catalogEditor->getAllVideos();
        $referencedEditionIds = $catalogEditor->getReferencedEditionIds();
        $referencedSignLanguageIds = $catalogEditor->getReferencedSignLanguageIds();
        $referencedSubtitleLanguageIds = $catalogEditor->getReferencedSubtitleLanguageIds();
        $referencedTypologyIds = $catalogEditor->getReferencedTypologyIds();
        $catalogTags = $catalogEditor->getAllTags();
        [$syncStatus, $isSyncing] = $this->resolveSyncContext($syncContext);

        return array_merge(
            compact(
                'catalogVideos',
                'editions',
                'signLanguages',
                'subtitleLanguages',
                'typologies',
                'catalogEditor',
                'referencedEditionIds',
                'referencedSignLanguageIds',
                'referencedSubtitleLanguageIds',
                'referencedTypologyIds',
                'catalogTags',
                'syncStatus',
                'isSyncing',
            ),
            [
                'activeJobBanner' => ActiveJobBanner::resolve($c),
            ],
            StudioHeader::vars($c, StudioHeader::NAV_CATALOG, $syncStatus, $isSyncing),
        );
    }

    private function continguts(): never
    {
        header('Location: ' . $this->c->baseUrl);
        exit;
    }

    /** @param array{syncStatus?: array|null, isSyncing?: bool}|null $syncContext
     *  @return array{0: array|null, 1: bool}
     */
    private function resolveSyncContext(?array $syncContext): array
    {
        if ($syncContext !== null) {
            $syncStatus = $syncContext['syncStatus'] ?? null;
            $isSyncing = $syncContext['isSyncing'] ?? (($syncStatus['status'] ?? '') === 'running');

            return [$syncStatus, $isSyncing];
        }

        $syncStatusPath = $this->c->dataDir . '/sync-status.json';
        $raw = is_file($syncStatusPath) ? @file_get_contents($syncStatusPath) : false;
        $syncStatus = $raw ? json_decode($raw, true) : null;
        $isSyncing = ($syncStatus['status'] ?? '') === 'running';

        return [$syncStatus, $isSyncing];
    }

    private function contingutsVideo(): never
    {
        $c = $this->c;
        $vimeoId = trim((string) ($_GET['vimeo_id'] ?? ''));
        if ($vimeoId === '') {
            http_response_code(404);
            extract($this->c->headerContext(StudioHeader::NAV_CATALOG));
            require $this->view('continguts-video-not-found.php');
            exit;
        }
        $video = $c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            http_response_code(404);
            extract($this->c->headerContext(StudioHeader::NAV_CATALOG));
            require $this->view('continguts-video-not-found.php');
            exit;
        }
        $catalogTags = $c->catalogEditor()->getAllTags();
        $subtitleLanguages = $c->studioConfig->getSubtitleLanguages();
        $typologies = $c->studioConfig->getTypologies();
        $signLanguages = $c->studioConfig->getSignLanguages();
        $editions = $c->studioConfig->getEditions();
        extract($this->c->headerContext(StudioHeader::NAV_CATALOG));
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

    private function batchTranslateStart(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $statusPath = $this->c->dataDir . '/batch-translate-status.json';
        $raw = is_file($statusPath) ? @file_get_contents($statusPath) : false;
        $current = $raw ? json_decode($raw, true) : null;
        if (($current['status'] ?? '') === 'running') {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Ja hi ha una traducció per lots en curs.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        file_put_contents($statusPath, json_encode([
            'status' => 'running', 'processed' => 0, 'translated' => 0, 'skipped' => 0, 'total' => 0,
            'last_message' => 'Començant…',
        ]));
        $this->c->launcher->launchBatchTranslate($statusPath);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function batchTranslateStatus(): never
    {
        ini_set('display_errors', '0');
        header('Content-Type: application/json; charset=utf-8');
        $statusPath = $this->c->dataDir . '/batch-translate-status.json';
        echo is_file($statusPath) ? (file_get_contents($statusPath) ?: '{}') : json_encode(['status' => 'idle']);
        exit;
    }

    private function syncFromSheet(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        @set_time_limit(300);

        try {
            $result = $this->c->catalogSheetSync()->run();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Error desconegut en sincronitzar el full.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($result->error !== null) {
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'error' => $result->error,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'added' => $result->added,
            'updated' => $result->updated,
            'removed' => $result->removed,
            'skipped' => $result->skipped,
            'warnings' => $result->warnings,
            'message' => sprintf(
                'Sincronització completada: %d afegits, %d actualitzats, %d omesos, %d avisos.',
                $result->added,
                $result->updated,
                $result->skipped,
                count($result->warnings),
            ),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function addVideo(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $signLanguage = trim((string) ($_POST['sign_language'] ?? ''));
        $edition = trim((string) ($_POST['edition'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $typology = trim((string) ($_POST['typology'] ?? ''));
        $tags = is_array($_POST['tags'] ?? null) ? array_values(array_filter(array_map('trim', $_POST['tags']))) : [];
        $masterLang = trim((string) ($_POST['master_lang'] ?? ''));

        $captionUploads = $this->parseCaptionUploads();
        if ($captionUploads['error'] !== null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $captionUploads['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $result = (new CatalogIntakeAddHandler(
            $this->c->vimeoClient(),
            $this->c->catalogEditor(),
            $this->c->studioConfig,
            $this->c->dataDir . '/captions',
            $this->c->dataDir . '/caption-translation',
        ))->handle($vimeoId, $signLanguage, $edition, $title, $typology, $tags, $captionUploads['uploads'], $masterLang);

        if (!$result['ok']) {
            http_response_code(422);
        }

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

        $downloadBasename = $vimeoId . '_' . strtoupper($lang);

        if ($format === 'srt') {
            header('Content-Type: application/x-subrip; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $downloadBasename . '.srt"');
            echo (new \Studio\VttToSrtConverter())->convert($vttPath);
        } else {
            header('Content-Type: text/vtt; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $downloadBasename . '.vtt"');
            readfile($vttPath);
        }
        exit;
    }

    private function downloadDataZip(): never
    {
        $zip = (new \Studio\DataDirZipBuilder())->build($this->c->dataDir);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="deaf-city-data.zip"');
        header('Content-Length: ' . strlen($zip));
        echo $zip;
        exit;
    }

    private function setVideoInvisible(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $videoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $invisibleRaw = $_POST['invisible'] ?? '';
        $invisible = in_array($invisibleRaw, [true, 1, '1', 'true', 'on'], true);

        $result = (new VideoVisibilityHandler($this->c->catalogEditor()))
            ->handle($videoId, $invisible);

        if (!$result['ok']) {
            http_response_code($videoId === '' ? 422 : 404);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function saveVideo(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $videoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $typology = trim((string) ($_POST['typology'] ?? ''));
        $signLanguage = trim((string) ($_POST['sign_language'] ?? ''));
        $edition = trim((string) ($_POST['edition'] ?? ''));
        $tags = is_array($_POST['tags'] ?? null) ? array_values(array_filter(array_map('trim', $_POST['tags']))) : [];
        if ($videoId === '' || $title === '' || $signLanguage === '' || $edition === '') {
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
            ->handle($videoId, $title, $tags, $typology !== '' ? $typology : null, $signLanguage, $edition);

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
                $this->c->dataDir . '/caption-translation',
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
                'edition' => $this->c->configMutation()->updateEditionLabel($id, $label),
                'sign_language' => $this->c->configMutation()->updateSignLanguageLabel($id, $label),
                'typology' => $this->c->configMutation()->updateTypologyLabel($id, $label),
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
            $this->c->dataDir . '/caption-translation',
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
                $this->c->dataDir . '/caption-translation',
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
        echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">';
        echo '<link rel="stylesheet" href="css/colors.css"><style>body{background:var(--studio-bg);color:var(--studio-danger);font-family:var(--studio-font);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:2rem;text-align:center;}</style>';
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
                'edition' => $this->c->configMutation()->removeEdition($id),
                'sign_language' => $this->c->configMutation()->removeSignLanguage($id),
                'subtitle_language' => $this->c->configMutation()->removeSubtitleLanguage($id),
                'typology' => $this->c->configMutation()->removeTypology($id),
                default => throw new \InvalidArgumentException('Unknown delete type.'),
            };
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function captionTranslateStart(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));

        if ($vimeoId === '' || !preg_match('/^\d+$/', $vimeoId)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Paràmetre vimeo_id no vàlid.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $jobDir   = $this->captionTranslationJobDir($vimeoId);
        $stateFile = $jobDir . '/translation.json';
        $translationState = $this->translationJobState($vimeoId);

        if (is_file($stateFile)) {
            $existing = $translationState->read();
            if (in_array($existing['status'] ?? '', ['pending', 'running'], true)) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'Ja hi ha una traducció en curs per a aquest vídeo.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $video = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Vídeo no trobat al catàleg.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $masterLang = $video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? '');
        if ($masterLang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'No hi ha subtítol mestre definit per a aquest vídeo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $masterFile = null;
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['lang'] ?? '') === $masterLang) {
                $masterFile = $caption['file'] ?? null;
                break;
            }
        }
        if ($masterFile === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'El fitxer del subtítol mestre no s\'ha trobat al catàleg.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $masterVttPath = $this->c->dataDir . '/captions/' . $masterFile;
        if (!is_file($masterVttPath)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'El fitxer VTT mestre no existeix al servidor.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existingLangs = array_column($video['captions'] ?? [], 'lang');
        $targets = [];
        foreach ($this->c->studioConfig->getSubtitleLanguages() as $lang) {
            $id = (string) ($lang['id'] ?? '');
            if ($id !== '' && $id !== $masterLang && !in_array($id, $existingLangs, true)) {
                $targets[] = $id;
            }
        }

        if ($targets === []) {
            echo json_encode(['ok' => true, 'nothing_to_translate' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!is_dir($jobDir) && !mkdir($jobDir, 0775, true) && !is_dir($jobDir)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'No s\'ha pogut crear el directori de treball.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $translationState->initiate($targets, $masterLang);

        $this->c->launcher->launchTranslation($masterVttPath, $stateFile, $masterLang, $jobDir, $targets);

        echo json_encode(['ok' => true, 'targets' => $targets], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function captionTranslateStatus(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_GET['vimeo_id'] ?? $_POST['vimeo_id'] ?? ''));

        if ($vimeoId === '' || !preg_match('/^\d+$/', $vimeoId)) {
            echo json_encode(['status' => 'idle'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $jobDir   = $this->captionTranslationJobDir($vimeoId);
        $stateFile = $jobDir . '/translation.json';

        if (!is_file($stateFile)) {
            echo json_encode(['status' => 'idle', 'missingTargets' => $this->computeMissingTargets($vimeoId)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $translationState = $this->translationJobState($vimeoId);

        $video = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        $currentMasterLang = $video !== null
            ? ($video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? ''))
            : '';

        if ($translationState->isStaleFor($currentMasterLang)) {
            // The video's master caption changed since this job was started
            // (or it's a legacy job predating this check) — discard it
            // rather than trust/replay a result that no longer applies.
            $translationState->cancel();
            echo json_encode(['status' => 'idle', 'missingTargets' => $this->computeMissingTargets($vimeoId)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $state = $translationState->read();
        $topStatus = $state['status'] ?? 'pending';

        if ($topStatus === 'done') {
            $result = $this->finalizeCaptionTranslation($vimeoId, $jobDir, $state);
            echo json_encode([
                'status'     => 'saved',
                'savedLangs' => $result['saved'],
                'errorLangs' => $result['errors'],
                'languages'  => $state['languages'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($topStatus === 'saved') {
            echo json_encode([
                'status'     => 'saved',
                'savedLangs' => $state['savedLangs'] ?? [],
                'errorLangs' => $state['errorLangs'] ?? [],
                'languages'  => $state['languages'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'status'    => $topStatus,
            'languages' => $state['languages'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function captionTranslateRetry(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        $vimeoId = trim((string) ($_POST['vimeo_id'] ?? ''));
        $lang    = trim((string) ($_POST['lang'] ?? ''));

        if ($vimeoId === '' || !preg_match('/^\d+$/', $vimeoId) || $lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Falten paràmetres obligatoris.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $jobDir   = $this->captionTranslationJobDir($vimeoId);
        $stateFile = $jobDir . '/translation.json';

        if (!is_file($stateFile)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'No hi ha cap estat de traducció per a aquest vídeo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $translationState = $this->translationJobState($vimeoId);
        $state = $translationState->read();
        if (!array_key_exists($lang, $state['languages'] ?? [])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Idioma no trobat a l\'estat de traducció.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $video = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Vídeo no trobat.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $masterLang = $video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? '');
        $masterFile = null;
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['lang'] ?? '') === $masterLang) {
                $masterFile = $caption['file'] ?? null;
                break;
            }
        }
        if ($masterFile === null) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Fitxer VTT mestre no trobat.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $masterVttPath = $this->c->dataDir . '/captions/' . $masterFile;

        $translationState->resetLanguage($lang);

        $this->c->launcher->launchTranslation($masterVttPath, $stateFile, $masterLang, $jobDir, [$lang]);

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return array{saved: list<string>, errors: list<string>} */
    private function finalizeCaptionTranslation(string $vimeoId, string $jobDir, array $state): array
    {
        $captionsDir = $this->c->dataDir . '/captions';
        $video       = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        $title       = (string) ($video['title'] ?? $vimeoId);
        $captionFilename = new \Studio\CaptionFilename();
        $langLabels  = [];
        foreach ($this->c->studioConfig->getSubtitleLanguages() as $language) {
            $langLabels[(string) ($language['id'] ?? '')] = (string) ($language['label'] ?? '');
        }

        $savedLangs  = [];
        $errorLangs  = [];
        $newCaptions = [];

        foreach ($state['languages'] ?? [] as $lang => $entry) {
            $langStr = (string) $lang;
            if (($entry['status'] ?? '') === 'error') {
                $errorLangs[] = $langStr;
                continue;
            }
            if (($entry['status'] ?? '') !== 'done') {
                continue;
            }

            $srcPath      = $jobDir . '/draft_' . $langStr . '.vtt';
            $destFilename = $captionFilename->forVideo($title, $langStr);
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
                $publication = new \Studio\CaptionPublication(
                    $this->c->catalogEditor(),
                    $this->c->vimeoClient(),
                    $captionsDir,
                );
                $publication->publish($vimeoId, $newCaptions);
            } catch (\Throwable) {
                $errorLangs = array_merge($errorLangs, $savedLangs);
                $savedLangs = [];
            }
        }

        $this->translationJobState($vimeoId)->markSaved($savedLangs, $errorLangs);

        return ['saved' => $savedLangs, 'errors' => $errorLangs];
    }

    private function translationJobState(string $vimeoId): TranslationJobState
    {
        return new TranslationJobState(new JobManager(dirname($this->captionTranslationJobDir($vimeoId))));
    }

    /** @return list<array{id: string, label: string}> */
    private function computeMissingTargets(string $vimeoId): array
    {
        $video = $this->c->catalogEditor()->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            return [];
        }
        $masterLang    = $video['master_caption_lang'] ?? ($video['captions'][0]['lang'] ?? '');
        $existingLangs = array_column($video['captions'] ?? [], 'lang');
        $missing = [];
        foreach ($this->c->studioConfig->getSubtitleLanguages() as $lang) {
            $id = (string) ($lang['id'] ?? '');
            if ($id !== '' && $id !== $masterLang && !in_array($id, $existingLangs, true)) {
                $missing[] = ['id' => $id, 'label' => (string) ($lang['label'] ?? $id)];
            }
        }
        return $missing;
    }

    private function captionTranslationJobDir(string $vimeoId): string
    {
        return $this->c->dataDir . '/caption-translation/' . $vimeoId . '/current';
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
