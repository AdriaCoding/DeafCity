<?php

namespace Studio;

class StudioConfig
{
    private array $data;

    public function __construct(private readonly string $configPath)
    {
        $json = file_get_contents($configPath);
        if ($json === false) {
            throw new \RuntimeException('Could not read studio config.');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid studio config JSON.');
        }

        $this->data = $decoded;
    }

    public function addEdition(string $id, string $label): void
    {
        if (!preg_match('/^\d{4}-[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException('Invalid edition id.');
        }

        $fp = fopen($this->configPath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open studio config for writing.');
        }

        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException('Invalid studio config JSON.');
        }

        $editions = $data['editions'] ?? [];
        foreach ($editions as $edition) {
            if (($edition['id'] ?? '') === $id) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException('Edition already exists.');
            }
        }

        $editions[] = ['id' => $id, 'label' => $label];
        $data['editions'] = $editions;

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->data = $data;
    }

    public function getSignLanguages(): array
    {
        return $this->list('sign_languages');
    }

    public function getEditions(): array
    {
        return $this->list('editions');
    }

    public function getSubtitleLanguages(): array
    {
        return $this->list('subtitle_languages');
    }

    private function list(string $key): array
    {
        return $this->data[$key] ?? [];
    }
}
