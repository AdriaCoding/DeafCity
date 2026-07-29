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

        $this->prependConfigEntry('editions', $id, $label);
    }

    private function prependConfigEntry(string $listKey, string $id, string $label): void
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

        array_unshift($entries, ['id' => $id, 'label' => $label]);
        $data[$listKey] = $entries;

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->data = $data;
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

    public function updateEditionLabel(string $id, string $label): void
    {
        $this->updateConfigEntryLabel('editions', $id, $label);
    }

    public function updateSignLanguageLabel(string $id, string $label): void
    {
        $this->updateConfigEntryLabel('sign_languages', $id, $label);
    }

    public function updateTypologyLabel(string $id, string $label): void
    {
        $this->updateConfigEntryLabel('typologies', $id, $label);
    }

    public function removeEdition(string $id, CatalogEditor $catalogEditor): void
    {
        if (in_array($id, $catalogEditor->getReferencedEditionIds(), true)) {
            throw new \RuntimeException("Edition '$id' is still referenced by one or more catalog videos.");
        }
        $this->removeConfigEntry('editions', $id);
    }

    public function removeSignLanguage(string $id, CatalogEditor $catalogEditor): void
    {
        if (in_array($id, $catalogEditor->getReferencedSignLanguageIds(), true)) {
            throw new \RuntimeException("Sign language '$id' is still referenced by one or more catalog videos.");
        }
        $this->removeConfigEntry('sign_languages', $id);
    }

    public function removeTypology(string $id, CatalogEditor $catalogEditor): void
    {
        if (in_array($id, $catalogEditor->getReferencedTypologyIds(), true)) {
            throw new \RuntimeException("Typology '$id' is still referenced by one or more catalog videos.");
        }
        $this->removeConfigEntry('typologies', $id);
    }

    public function removeSubtitleLanguage(string $id, CatalogEditor $catalogEditor): void
    {
        if (in_array($id, $catalogEditor->getReferencedSubtitleLanguageIds(), true)) {
            throw new \RuntimeException("Subtitle language '$id' is still referenced by one or more catalog videos.");
        }
        $this->removeConfigEntry('subtitle_languages', $id);
    }

    private function updateConfigEntryLabel(string $listKey, string $id, string $label): void
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

        $found = false;
        foreach ($data[$listKey] ?? [] as $i => $entry) {
            if (($entry['id'] ?? '') === $id) {
                $data[$listKey][$i]['label'] = $label;
                $found = true;
                break;
            }
        }

        if (!$found) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException("Config entry '$id' not found in '$listKey'.");
        }

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->data = $data;
    }

    private function removeConfigEntry(string $listKey, string $id): void
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
        $filtered = array_values(array_filter($entries, fn($e) => ($e['id'] ?? '') !== $id));
        $data[$listKey] = $filtered;

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

    public function addTypology(string $id, string $label): void
    {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException('Invalid typology id.');
        }

        $this->appendConfigEntry('typologies', $id, $label);
    }

    public function addSubtitleLanguage(string $id, string $label): void
    {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException('Invalid subtitle language id.');
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

        $entries = $data['subtitle_languages'] ?? [];
        foreach ($entries as $entry) {
            if (($entry['id'] ?? '') === $id) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException('Config entry already exists.');
            }
        }

        $entries[] = [
            'id' => $id,
            'label' => $label,
        ];
        $data['subtitle_languages'] = $entries;

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->data = $data;
    }

    public function getSignLanguages(): array
    {
        $entries = $this->list('sign_languages');
        usort(
            $entries,
            function (array $a, array $b): int {
                $labelA = (string) ($a['label'] ?? '');
                $labelB = (string) ($b['label'] ?? '');
                $cmp = strcasecmp(
                    self::secondWordSortKey($labelA),
                    self::secondWordSortKey($labelB),
                );
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcasecmp($labelA, $labelB);
            },
        );

        return $entries;
    }

    public function getEditions(): array
    {
        return $this->list('editions');
    }

    public function getTypologies(): array
    {
        return $this->list('typologies');
    }

    public function getSubtitleLanguages(): array
    {
        $entries = [];
        foreach ($this->listSortedByLabel('subtitle_languages') as $entry) {
            $entries[] = $this->normalizeSubtitleLanguageEntry($entry);
        }

        return $entries;
    }

    /** @param array<string, mixed> $entry */
    private function normalizeSubtitleLanguageEntry(array $entry): array
    {
        unset($entry['translation_target'], $entry['vimeo_code']);

        return $entry;
    }

    private function list(string $key): array
    {
        return $this->data[$key] ?? [];
    }

    private function listSortedByLabel(string $key): array
    {
        $entries = $this->list($key);
        usort(
            $entries,
            fn(array $a, array $b): int => strcasecmp(
                (string) ($a['label'] ?? ''),
                (string) ($b['label'] ?? ''),
            ),
        );

        return $entries;
    }

    /** Second whitespace-separated word, or the full label if only one word. */
    private static function secondWordSortKey(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $label);
        if (!is_array($parts) || count($parts) < 2) {
            return $label;
        }

        return $parts[1];
    }
}
