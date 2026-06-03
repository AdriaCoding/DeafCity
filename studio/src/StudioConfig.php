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

        $this->appendConfigEntry('editions', $id, $label);
    }

    private function appendConfigEntry(string $listKey, string $id, string $label): void
    {
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

        $entries = $data[$listKey] ?? [];
        foreach ($entries as $entry) {
            if (($entry['id'] ?? '') === $id) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException('Config entry already exists.');
            }
        }

        $entries[] = ['id' => $id, 'label' => $label];
        $data[$listKey] = $entries;

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->data = $data;
    }

    public function addSignLanguage(string $id, string $label): void
    {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException('Invalid sign language id.');
        }

        $this->appendConfigEntry('sign_languages', $id, $label);
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
