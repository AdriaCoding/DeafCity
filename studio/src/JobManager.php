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

    public function createWithContent(array $fields, string $content): void
    {
        // Fast unlocked pre-check for the common case; createDirAtomically()
        // below is the authoritative check that closes the race between two
        // concurrent creates (see its docblock).
        if ($this->exists()) {
            throw new \RuntimeException('Ja hi ha una feina en curs.');
        }

        $this->createDirAtomically();

        $this->writeJson($fields);

        if (file_put_contents($this->draftPath(), $content) === false) {
            $this->cancel();
            throw new \RuntimeException('No s\'ha pogut desar el fitxer de subtítols generat.');
        }
    }

    public function createWithAudio(array $fields, UploadedFile $audio): void
    {
        // Fast unlocked pre-check for the common case; createDirAtomically()
        // below is the authoritative check that closes the race between two
        // concurrent creates (see its docblock).
        if ($this->exists()) {
            throw new \RuntimeException('Ja hi ha una feina en curs.');
        }

        $this->createDirAtomically();

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

    public function hasDraft(): bool
    {
        return is_file($this->draftPath());
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

    public function draftPath(): string
    {
        return $this->currentDir . '/draft.srt';
    }

    public function writeDraft(string $content): void
    {
        file_put_contents($this->draftPath(), $content);
    }

    public function draftPathForLang(string $lang): string
    {
        return $this->currentDir . '/draft_' . $lang . '.srt';
    }

    public function writeDraftForLang(string $lang, string $content): void
    {
        file_put_contents($this->draftPathForLang($lang), $content);
    }

    public function revisionStatePath(): string
    {
        return $this->currentDir . '/revision_status.json';
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

    /**
     * Create the job directory, relying on mkdir()'s atomic EEXIST failure
     * (rather than an is_dir() check-then-act) to detect a collision.
     *
     * The previous code did `if (!mkdir(...) && !is_dir(...))`, which
     * silently treats "the directory already exists" as success — so two
     * concurrent creates could both pass the exists() pre-check, both call
     * mkdir(), and both go on to write job.json / the draft into the same
     * directory, corrupting each other's job. Here, mkdir() itself is the
     * single atomic check: whichever caller's mkdir() wins creates the
     * directory; the loser's mkdir() fails with the directory already
     * present, and that failure is always surfaced as "job already in
     * progress" rather than swallowed.
     */
    private function createDirAtomically(): void
    {
        if (@mkdir($this->currentDir, 0775, true)) {
            return;
        }

        if (is_dir($this->currentDir)) {
            throw new \RuntimeException('Ja hi ha una feina en curs.');
        }

        throw new \RuntimeException('No s\'ha pogut crear el directori de la feina.');
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
