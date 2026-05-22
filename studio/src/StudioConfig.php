<?php

namespace Studio;

class StudioConfig
{
    private array $data;

    public function __construct(string $configPath)
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
