<?php

namespace Studio\Actions;

use Studio\CatalogTagPool;
use Studio\Container;
use Studio\EditionAddHandler;
use Studio\SignLanguageAddHandler;
use Studio\VideoEditHandler;

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

    public function handle(string $action): never
    {
        match ($action) {
            'continguts'                          => $this->continguts(),
            'continguts-save-video'               => $this->saveVideo(),
            'continguts-save-edition-label'       => $this->saveLabel('edition'),
            'continguts-save-sign-language-label' => $this->saveLabel('sign_language'),
            'continguts-delete-edition'           => $this->deleteItem('edition'),
            'continguts-delete-sign-language'     => $this->deleteItem('sign_language'),
            default                               => (function () { http_response_code(404); exit; })(),
        };
    }

    private function continguts(): never
    {
        $c = $this->c;
        if ($c->jobManager->exists()) {
            header('Location: ' . $c->baseUrl);
            exit;
        }
        $catalogFilePath = $c->dataDir . '/catalog.json';
        $catalogData = is_file($catalogFilePath)
            ? (json_decode((string) file_get_contents($catalogFilePath), true) ?? ['videos' => []])
            : ['videos' => []];
        $catalogVideos = $catalogData['videos'] ?? [];
        $catalogTags = (new CatalogTagPool($catalogFilePath))->getTagsSortedAlphabetically();
        $editions = $c->studioConfig->getEditions();
        $signLanguages = $c->studioConfig->getSignLanguages();
        $catalogEditor = $c->catalogEditor();
        $referencedEditionIds = $catalogEditor->getReferencedEditionIds();
        $referencedSignLanguageIds = $catalogEditor->getReferencedSignLanguageIds();
        require $this->view('continguts.php');
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
        echo json_encode(
            (new VideoEditHandler($this->c->vimeoClient(), $this->c->catalogEditor()))->handle($videoId, $title, $tags),
            JSON_UNESCAPED_UNICODE,
        );
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
            if ($type === 'edition') {
                $this->c->studioConfig->updateEditionLabel($id, $label);
            } else {
                $this->c->studioConfig->updateSignLanguageLabel($id, $label);
            }
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
            if ($type === 'edition') {
                $this->c->studioConfig->removeEdition($id, $this->c->catalogEditor());
            } else {
                $this->c->studioConfig->removeSignLanguage($id, $this->c->catalogEditor());
            }
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
