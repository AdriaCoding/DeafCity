<?php

namespace Studio;

class JobManager
{
    private string $currentDir;

    public function __construct(string $jobsBaseDir)
    {
        $this->currentDir = rtrim($jobsBaseDir, '/') . '/current';
    }

    public function exists(): bool
    {
        return is_dir($this->currentDir);
    }

    public function createWithContent(array $fields, string $vttContent): void
    {
        if ($this->exists()) {
            throw new \RuntimeException('Ja hi ha una feina en curs.');
        }

        if (!mkdir($this->currentDir, 0775, true) && !is_dir($this->currentDir)) {
            throw new \RuntimeException('No s\'ha pogut crear el directori de la feina.');
        }

        $this->writeJson($fields);

        if (file_put_contents($this->draftVttPath(), $vttContent) === false) {
            $this->cancel();
            throw new \RuntimeException('No s\'ha pogut desar el fitxer de subtítols generat.');
        }
    }

    public function create(array $fields, UploadedFile $vtt): void
    {
        if ($this->exists()) {
            throw new \RuntimeException('Ja hi ha una feina en curs.');
        }

        if (!mkdir($this->currentDir, 0775, true) && !is_dir($this->currentDir)) {
            throw new \RuntimeException('No s\'ha pogut crear el directori de la feina.');
        }

        $this->writeJson($fields);

        $destination = $this->currentDir . '/draft.vtt';
        if (!move_uploaded_file($vtt->tmpPath, $destination)) {
            if (!rename($vtt->tmpPath, $destination)) {
                $this->cancel();
                throw new \RuntimeException('No s\'ha pogut desar el fitxer de subtítols pujat.');
            }
        }
    }

    public function createWithAudio(array $fields, UploadedFile $audio): void
    {
        if ($this->exists()) {
            throw new \RuntimeException('Ja hi ha una feina en curs.');
        }

        if (!mkdir($this->currentDir, 0775, true) && !is_dir($this->currentDir)) {
            throw new \RuntimeException('No s\'ha pogut crear el directori de la feina.');
        }

        $ext = strtolower(pathinfo($audio->originalName, PATHINFO_EXTENSION));
        $filename = $ext !== '' ? "interpreter_audio.$ext" : 'interpreter_audio';
        $fields['interpreter_audio'] = $filename;

        $this->writeJson($fields);

        $destination = $this->currentDir . '/' . $filename;
        if (!move_uploaded_file($audio->tmpPath, $destination)) {
            if (!rename($audio->tmpPath, $destination)) {
                $this->cancel();
                throw new \RuntimeException('No s\'ha pogut desar el fitxer d\'àudio pujat.');
            }
        }

        file_put_contents($this->transcriptionStatusPath(), json_encode(['status' => 'pending']));
    }

    public function hasDraftVtt(): bool
    {
        return is_file($this->draftVttPath());
    }

    public function transcriptionStatusPath(): string
    {
        return $this->currentDir . '/transcription.json';
    }

    public function interpreterAudioPath(): string
    {
        $job = $this->read();
        return $this->currentDir . '/' . ($job['interpreter_audio'] ?? 'interpreter_audio');
    }

    public function read(): array
    {
        $path = $this->jobJsonPath();
        if (!is_file($path)) {
            throw new \RuntimeException('Falten les metadades de la feina.');
        }

        $json = file_get_contents($path);
        $data = json_decode($json ?: '', true);
        if (!is_array($data)) {
            throw new \RuntimeException('Les metadades de la feina no són vàlides.');
        }

        return $data;
    }

    public function update(array $fields): void
    {
        $job = $this->read();
        $this->writeJson(array_merge($job, $fields));
    }

    public function draftVttPath(): string
    {
        return $this->currentDir . '/draft.vtt';
    }

    public function writeDraftVtt(string $content): void
    {
        file_put_contents($this->draftVttPath(), $content);
    }

    public function draftVttPathForLang(string $lang): string
    {
        return $this->currentDir . '/draft_' . $lang . '.vtt';
    }

    public function writeDraftVttForLang(string $lang, string $content): void
    {
        file_put_contents($this->draftVttPathForLang($lang), $content);
    }

    public function translationStatePath(): string
    {
        return $this->currentDir . '/translation.json';
    }

    public function readTranslationState(): ?string
    {
        $path = $this->translationStatePath();
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function writeTranslationState(string $content): void
    {
        file_put_contents($this->translationStatePath(), $content);
    }

    public function readTranscriptionStatus(): ?string
    {
        $path = $this->transcriptionStatusPath();
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function cancel(): void
    {
        if (!$this->exists()) {
            return;
        }

        $this->removeDir($this->currentDir);
    }

    private function writeJson(array $fields): void
    {
        $encoded = json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('No s\'han pogut codificar les metadades de la feina.');
        }

        file_put_contents($this->jobJsonPath(), $encoded . "\n");
    }

    private function jobJsonPath(): string
    {
        return $this->currentDir . '/job.json';
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
