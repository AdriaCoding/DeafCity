<?php

namespace Studio\Actions;

use Studio\CatalogTagPool;
use Studio\Container;
use Studio\PublicationHandler;
use Studio\TaggingHandler;
use Studio\TranslationJobState;

class PublicationAction
{
    public function __construct(private Container $c) {}

    public function tagging(): never
    {
        $c = $this->c;
        if (!$c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }
        $job = $c->jobManager->read();
        $catalogTags = (new CatalogTagPool($c->dataDir . '/catalog.json'))->getTagsSortedAlphabetically();
        $errors = [];
        $jobTags = $job['tags'] ?? [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = (new TaggingHandler($c->jobManager))->handle($_POST);
            if ($result['ok']) {
                header('Location: ' . $c->baseUrl);
                exit;
            }
            $jobTags = is_array($_POST['tags'] ?? null) ? $_POST['tags'] : [];
            $errors = $result['errors'];
        }
        require $this->view('tagging.php');
        exit;
    }

    public function advanceToTagging(): never
    {
        header('Content-Type: application/json');
        if (!$this->c->jobManager->exists()) {
            http_response_code(422);
            echo json_encode(['ok' => false]);
            exit;
        }
        $this->c->jobManager->update(['step' => 'tagging']);
        echo json_encode(['ok' => true]);
        exit;
    }

    public function handle(): never
    {
        $c = $this->c;
        if (!$c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }
        $job = $c->jobManager->read();
        $vimeoWarnings = [];
        $publicationError = null;

        $signLanguageLabel = $job['sign_language'];
        foreach ($c->studioConfig->getSignLanguages() as $sl) {
            if ($sl['id'] === $job['sign_language']) {
                $signLanguageLabel = $sl['label'];
                break;
            }
        }
        $editionLabel = $job['edition'];
        foreach ($c->studioConfig->getEditions() as $ed) {
            if ($ed['id'] === $job['edition']) {
                $editionLabel = $ed['label'];
                break;
            }
        }
        $langLabelMap = [];
        foreach ($c->studioConfig->getSubtitleLanguages() as $l) {
            $langLabelMap[$l['id']] = $l['label'];
        }

        $summaryTitle = $job['video_title'] ?? '';
        $summarySignLanguage = $signLanguageLabel;
        $summaryEdition = $editionLabel;
        $summaryTags = implode(', ', $job['tags'] ?? []);
        $captionLangs = [$langLabelMap[$job['subtitle_language'] ?? ''] ?? ($job['subtitle_language'] ?? '')];
        foreach ($c->studioConfig->getSubtitleLanguages() as $l) {
            $lang = $l['id'];
            if ($lang === ($job['subtitle_language'] ?? '')) {
                continue;
            }
            if (is_file($c->jobManager->draftVttPathForLang($lang))) {
                $captionLangs[] = $langLabelMap[$lang] ?? $lang;
            }
        }
        $summaryCaptions = implode(', ', $captionLangs);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = (new PublicationHandler(
                    $c->vimeoClient(),
                    $c->jobManager,
                    $c->studioConfig,
                    $c->dataDir . '/captions',
                    $c->dataDir . '/catalog.json',
                ))->handle();
                if (empty($result['vimeoWarnings'])) {
                    header('Location: ' . $c->baseUrl);
                    exit;
                }
                $vimeoWarnings = $result['vimeoWarnings'];
            } catch (\Throwable $e) {
                $publicationError = 'No s\'ha pogut completar la publicació. ' . $e->getMessage();
            }
        }
        require $this->view('publication.php');
        exit;
    }

    private function view(string $name): string
    {
        return dirname(__DIR__, 2) . '/views/' . $name;
    }
}
