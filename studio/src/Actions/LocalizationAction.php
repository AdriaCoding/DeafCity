<?php

namespace Studio\Actions;

use Studio\Container;
use Studio\GeminiTranslator;
use Studio\LocalizationStore;
use Studio\SeedTranslator;
use Studio\StudioHeader;

class LocalizationAction
{
    public function __construct(private Container $c) {}

    public function handle(string $action): never
    {
        match ($action) {
            'localitzacions' => $this->renderEditor(),
            'localitzacions-save' => $this->saveCell(),
            'localitzacions-seed' => $this->seedLanguage(),
            default => throw new \InvalidArgumentException("Unknown localization action: $action"),
        };
    }

    private function store(): LocalizationStore
    {
        return new LocalizationStore($this->c->dataDir . '/ui-localizations.json');
    }

    private function renderEditor(): never
    {
        $store = $this->store();
        $languages = $this->c->studioConfig->getSubtitleLanguages();
        $languageIds = array_column($languages, 'id');
        $completeness = $store->computeCompleteness($languageIds);
        $grouped = $store->listBySection();

        extract($this->c->headerContext(StudioHeader::NAV_LOCALIZATIONS));
        require dirname(__DIR__, 2) . '/views/localitzacions.php';
        exit;
    }

    private function saveCell(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $key = trim((string) ($_POST['key'] ?? ''));
        $lang = trim((string) ($_POST['lang'] ?? ''));
        $value = (string) ($_POST['value'] ?? '');

        if ($key === '' || $lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => ['Clau o idioma no vàlid.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($lang === 'en') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => ['Anglès és només lectura.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $this->store()->setCell($key, $lang, $value);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => [$e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function seedLanguage(): never
    {
        header('Content-Type: application/json; charset=utf-8');

        $lang = trim((string) ($_POST['lang'] ?? ''));
        $section = trim((string) ($_POST['section'] ?? ''));
        $sectionArg = $section !== '' ? $section : null;

        if ($lang === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'errors' => ['Idioma no vàlid.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
            $translator = new GeminiTranslator($apiKey);
            $seed = new SeedTranslator($this->store(), $translator);
            $result = $seed->seed($lang, $sectionArg);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'filled' => 0,
                'errors' => [$e->getMessage()],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$result['ok']) {
            http_response_code(422);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
