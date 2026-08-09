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

        $this->appendConfigEntry('subtitle_languages', $id, $label);
    }

    public function addInputLanguage(string $id, string $label, string $baseLanguage): void
    {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            throw new \InvalidArgumentException('Invalid input language id.');
        }

        $baseLanguageExists = false;
        foreach ($this->list('subtitle_languages') as $entry) {
            if (($entry['id'] ?? '') === $baseLanguage) {
                $baseLanguageExists = true;
                break;
            }
        }
        if (!$baseLanguageExists) {
            throw new \InvalidArgumentException('Unknown base language.');
        }

        $this->appendConfigEntry('input_languages', $id, $label, ['base_language' => $baseLanguage]);
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

    public function removeInputLanguage(string $id): void
    {
        $this->removeConfigEntry('input_languages', $id);
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

    public function getInputLanguages(): array
    {
        return $this->listSortedByLabel('input_languages');
    }

    /**
     * Maps a dialect id (e.g. 'es-mx') back to its base language (e.g. 'es').
     * A subtitle-language id is its own base language; an unknown id is
     * returned unchanged.
     */
    public function getBaseLanguageFor(string $id): string
    {
        foreach ($this->list('subtitle_languages') as $entry) {
            if (($entry['id'] ?? '') === $id) {
                return $id;
            }
        }

        foreach ($this->list('input_languages') as $entry) {
            if (($entry['id'] ?? '') === $id) {
                return (string) ($entry['base_language'] ?? $id);
            }
        }

        return $id;
    }

    /** Human-readable label for a subtitle- or input-language id, falling back to the raw id. */
    public function languageLabelFor(string $id): string
    {
        foreach ($this->list('input_languages') as $entry) {
            if (($entry['id'] ?? '') === $id) {
                return (string) ($entry['label'] ?? $id);
            }
        }

        foreach ($this->list('subtitle_languages') as $entry) {
            if (($entry['id'] ?? '') === $id) {
                return (string) ($entry['label'] ?? $id);
            }
        }

        return $id;
    }

    public function isValidInputLanguage(string $id): bool
    {
        foreach ($this->getCombinedInputLanguageOptions() as $lang) {
            if (($lang['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * The intake dropdown's option list: every subtitle (base) language plus
     * every dialect variant, sorted by label.
     */
    public function getCombinedInputLanguageOptions(): array
    {
        $options = [];
        foreach ($this->getSubtitleLanguages() as $entry) {
            $options[] = [
                'id' => $entry['id'] ?? '',
                'label' => $entry['label'] ?? '',
                'base_language' => $entry['id'] ?? '',
            ];
        }
        foreach ($this->getInputLanguages() as $entry) {
            $options[] = [
                'id' => $entry['id'] ?? '',
                'label' => $entry['label'] ?? '',
                'base_language' => $entry['base_language'] ?? '',
            ];
        }

        usort(
            $options,
            fn(array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']),
        );

        return $options;
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

    private function prependConfigEntry(string $listKey, string $id, string $label): void
    {
        $this->withLockedConfig(function (array &$data) use ($listKey, $id, $label): void {
            $entries = $data[$listKey] ?? [];
            foreach ($entries as $entry) {
                if (($entry['id'] ?? '') === $id) {
                    throw new \RuntimeException('Config entry already exists.');
                }
            }

            array_unshift($entries, ['id' => $id, 'label' => $label]);
            $data[$listKey] = $entries;
        });
    }

    /** @param array<string, mixed> $extra */
    private function appendConfigEntry(string $listKey, string $id, string $label, array $extra = []): void
    {
        $this->withLockedConfig(function (array &$data) use ($listKey, $id, $label, $extra): void {
            $entries = $data[$listKey] ?? [];
            foreach ($entries as $entry) {
                if (($entry['id'] ?? '') === $id) {
                    throw new \RuntimeException('Config entry already exists.');
                }
            }

            $entries[] = ['id' => $id, 'label' => $label] + $extra;
            $data[$listKey] = $entries;
        });
    }

    private function updateConfigEntryLabel(string $listKey, string $id, string $label): void
    {
        $this->withLockedConfig(function (array &$data) use ($listKey, $id, $label): void {
            $found = false;
            foreach ($data[$listKey] ?? [] as $i => $entry) {
                if (($entry['id'] ?? '') === $id) {
                    $data[$listKey][$i]['label'] = $label;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new \RuntimeException("Config entry '$id' not found in '$listKey'.");
            }
        });
    }

    private function removeConfigEntry(string $listKey, string $id): void
    {
        $this->withLockedConfig(function (array &$data) use ($listKey, $id): void {
            $entries = $data[$listKey] ?? [];
            $data[$listKey] = array_values(array_filter($entries, fn($e) => ($e['id'] ?? '') !== $id));
        });
    }

    /**
     * Serialize writers through a dedicated lock file, then commit via
     * write-temp-then-rename so a crash mid-write can never leave
     * studio-config.json truncated or partially written — readers always
     * see either the pre- or post-mutation content, never a corrupt file.
     *
     * A separate lock file (rather than locking configPath itself) is
     * required for this to be correct: locking the path being replaced by
     * rename() would let a writer that opened its handle just before the
     * rename go on to read/lock the now-unlinked old inode instead of the
     * file current writers actually see.
     *
     * @param callable(array<string, mixed> &$data): void $mutate
     */
    private function withLockedConfig(callable $mutate): void
    {
        $lockFp = fopen($this->configPath . '.lock', 'c');
        if ($lockFp === false) {
            throw new \RuntimeException('Could not open studio config lock.');
        }

        try {
            flock($lockFp, LOCK_EX);

            $raw = file_get_contents($this->configPath);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                throw new \RuntimeException('Invalid studio config JSON.');
            }

            $mutate($data);

            $tmpPath = $this->configPath . '.tmp-' . bin2hex(random_bytes(6));
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            if (file_put_contents($tmpPath, $encoded) === false) {
                throw new \RuntimeException('Could not write studio config.');
            }
            if (!rename($tmpPath, $this->configPath)) {
                @unlink($tmpPath);
                throw new \RuntimeException('Could not save studio config.');
            }

            $this->data = $data;
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }
}
