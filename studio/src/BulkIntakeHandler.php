<?php

namespace Studio;

class BulkIntakeHandler
{
    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly JobManager $jobManager,
        private readonly BulkIntakeQueue $bulkQueue,
        private readonly BackgroundJobLauncher $launcher,
        private readonly string $dataDir,
    ) {}

    /**
     * @return array{errors: array<string, string>, values: array<string, string>, created?: bool}
     */
    public function handlePost(array $post, array $files): array
    {
        $errors = [];
        $values = ['subtitle_language' => ''];

        if ($this->jobManager->exists()) {
            $errors['_form'] = 'Ja hi ha una feina en curs.';
            return ['errors' => $errors, 'values' => $values];
        }

        if ($this->bulkQueue->exists()) {
            $errors['_form'] = 'Ja hi ha una transcripció en massa en curs.';
            return ['errors' => $errors, 'values' => $values];
        }

        $upload = $files['intake_file'] ?? null;
        if (!$upload || !is_array($upload['name'] ?? null)) {
            $errors['intake_file'] = 'Pugeu almenys dos fitxers d\'àudio.';
            return ['errors' => $errors, 'values' => $values];
        }

        $count = count($upload['name']);
        if ($count < 2) {
            $errors['intake_file'] = 'Pugeu almenys dos fitxers d\'àudio.';
            return ['errors' => $errors, 'values' => $values];
        }

        $languages = $post['bulk_languages'] ?? [];
        if (!is_array($languages)) {
            $errors['_form'] = 'Falten les llengües per als fitxers.';
            return ['errors' => $errors, 'values' => $values];
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $lang = trim((string) ($languages[$i] ?? ''));
            if ($lang === '' || !$this->isValidLanguage($lang)) {
                $errors['_form'] = 'Seleccioneu una llengua vàlida per a cada fitxer.';
                return ['errors' => $errors, 'values' => $values];
            }

            $fileError = (int) ($upload['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError === UPLOAD_ERR_NO_FILE) {
                $errors['intake_file'] = 'Pugeu un fitxer d\'àudio per a cada fila.';
                return ['errors' => $errors, 'values' => $values];
            }
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors['intake_file'] = 'No s\'ha pogut pujar un dels fitxers.';
                return ['errors' => $errors, 'values' => $values];
            }

            $originalName = (string) ($upload['name'][$i] ?? 'audio');
            $items[] = [
                'index' => $i,
                'language' => $lang,
                'originalName' => $originalName,
                'tmpPath' => (string) ($upload['tmp_name'][$i] ?? ''),
            ];
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $tmpDir = $this->bulkQueue->bulkTmpDir();
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            $errors['_form'] = 'No s\'ha pogut preparar l\'emmagatzematge temporal.';
            return ['errors' => $errors, 'values' => $values];
        }

        $queueItems = [];
        foreach ($items as $item) {
            $id = $this->generateId();
            $ext = strtolower(pathinfo($item['originalName'], PATHINFO_EXTENSION));
            $dest = $tmpDir . '/' . $id . ($ext !== '' ? ".$ext" : '');

            if (!move_uploaded_file($item['tmpPath'], $dest)) {
                if (!rename($item['tmpPath'], $dest)) {
                    $errors['_form'] = 'No s\'ha pogut desar un dels fitxers d\'àudio.';
                    return ['errors' => $errors, 'values' => $values];
                }
            }

            $queueItems[] = [
                'id' => $id,
                'originalFilename' => pathinfo($item['originalName'], PATHINFO_FILENAME),
                'language' => $item['language'],
                'tmpAudioPath' => $dest,
            ];
        }

        try {
            $this->bulkQueue->create($queueItems);
        } catch (\RuntimeException $e) {
            $errors['_form'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        $this->launcher->launchBulkQueue($this->dataDir);

        return ['errors' => [], 'values' => $values, 'created' => true];
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function isValidLanguage(string $id): bool
    {
        foreach ($this->studioConfig->getSubtitleLanguages() as $lang) {
            if (($lang['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }
}
