<?php

namespace Studio;

class LocalizationStore
{
    /** @var list<string> */
    public const CHROME_SECTIONS = ['player', 'about', 'participants'];

    /** @var array<string, array<string, mixed>> */
    private array $data;

    public function __construct(private readonly string $storePath)
    {
        $json = file_get_contents($storePath);
        if ($json === false) {
            throw new \RuntimeException('Could not read ui-localizations store.');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid ui-localizations JSON.');
        }

        $this->data = $decoded;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, list<array{key: string, context: string, translations: array<string, string>, seeded: array<string, bool>}>>
     */
    public function listBySection(): array
    {
        $grouped = [];
        foreach ($this->data as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $section = (string) ($entry['section'] ?? '');
            $grouped[$section][] = [
                'key' => $key,
                'context' => (string) ($entry['context'] ?? ''),
                'translations' => is_array($entry['translations'] ?? null) ? $entry['translations'] : [],
                'seeded' => is_array($entry['seeded'] ?? null) ? $entry['seeded'] : [],
            ];
        }

        foreach ($grouped as &$rows) {
            usort($rows, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));
        }
        unset($rows);

        return $grouped;
    }

    public function getCell(string $key, string $lang): string
    {
        $entry = $this->data[$key] ?? null;
        if (!is_array($entry)) {
            return '';
        }
        $translations = $entry['translations'] ?? [];

        return trim((string) ($translations[$lang] ?? ''));
    }

    public function setCell(string $key, string $lang, string $value, bool $clearSeeded = true): void
    {
        $fp = fopen($this->storePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open ui-localizations for writing.');
        }

        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException('Invalid ui-localizations JSON.');
        }

        if (!isset($data[$key]) || !is_array($data[$key])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \InvalidArgumentException("Unknown localization key: $key");
        }

        if (!isset($data[$key]['translations']) || !is_array($data[$key]['translations'])) {
            $data[$key]['translations'] = [];
        }

        $data[$key]['translations'][$lang] = $value;

        if ($clearSeeded && isset($data[$key]['seeded'][$lang])) {
            unset($data[$key]['seeded'][$lang]);
            if ($data[$key]['seeded'] === []) {
                unset($data[$key]['seeded']);
            }
        }

        $this->writeLocked($fp, $data);
        $this->data = $data;
    }

    /**
     * @param list<string> $languageIds
     * @return array<string, bool>
     */
    public function computeCompleteness(array $languageIds): array
    {
        $chromeKeys = [];
        foreach ($this->data as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $section = (string) ($entry['section'] ?? '');
            if (in_array($section, self::CHROME_SECTIONS, true)) {
                $chromeKeys[] = $key;
            }
        }

        $result = [];
        foreach ($languageIds as $langId) {
            $complete = true;
            foreach ($chromeKeys as $key) {
                $translations = $this->data[$key]['translations'] ?? [];
                $value = trim((string) ($translations[$langId] ?? ''));
                if ($value === '') {
                    $complete = false;
                    break;
                }
            }
            $result[$langId] = $complete;
        }

        return $result;
    }

    /**
     * Fill empty cells only; mark filled cells seeded.
     *
     * @param array<string, string> $translations key => translated text
     */
    public function fillEmptyCells(string $lang, array $translations, ?string $section = null): int
    {
        $fp = fopen($this->storePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open ui-localizations for writing.');
        }

        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException('Invalid ui-localizations JSON.');
        }

        $filled = 0;
        foreach ($translations as $key => $value) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }
            if ($section !== null && ($data[$key]['section'] ?? '') !== $section) {
                continue;
            }

            if (!isset($data[$key]['translations']) || !is_array($data[$key]['translations'])) {
                $data[$key]['translations'] = [];
            }

            $existing = trim((string) ($data[$key]['translations'][$lang] ?? ''));
            if ($existing !== '') {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $data[$key]['translations'][$lang] = $trimmed;
            if (!isset($data[$key]['seeded']) || !is_array($data[$key]['seeded'])) {
                $data[$key]['seeded'] = [];
            }
            $data[$key]['seeded'][$lang] = true;
            $filled++;
        }

        $this->writeLocked($fp, $data);
        $this->data = $data;

        return $filled;
    }

    /** @param resource $fp */
    private function writeLocked($fp, array $data): void
    {
        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
