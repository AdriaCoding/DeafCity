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

    public function create(array $fields, UploadedFile $vtt): void
    {
        if ($this->exists()) {
            throw new \RuntimeException('A job is already in progress.');
        }

        if (!mkdir($this->currentDir, 0775, true) && !is_dir($this->currentDir)) {
            throw new \RuntimeException('Could not create job directory.');
        }

        $this->writeJson($fields);

        $destination = $this->currentDir . '/draft.vtt';
        if (!move_uploaded_file($vtt->tmpPath, $destination)) {
            if (!rename($vtt->tmpPath, $destination)) {
                $this->cancel();
                throw new \RuntimeException('Could not save the uploaded subtitle file.');
            }
        }
    }

    public function read(): array
    {
        $path = $this->jobJsonPath();
        if (!is_file($path)) {
            throw new \RuntimeException('Job metadata is missing.');
        }

        $json = file_get_contents($path);
        $data = json_decode($json ?: '', true);
        if (!is_array($data)) {
            throw new \RuntimeException('Job metadata is invalid.');
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
            throw new \RuntimeException('Could not encode job metadata.');
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
