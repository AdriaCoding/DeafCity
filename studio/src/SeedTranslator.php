<?php

namespace Studio;

class SeedTranslator
{
    public function __construct(
        private readonly LocalizationStore $store,
        private readonly object $translator,
    ) {}

    /**
     * Machine-translate empty cells for a language (optionally scoped to a section).
     *
     * @return array{ok: bool, filled: int, errors?: list<string>}
     */
    public function seed(string $lang, ?string $section = null): array
    {
        if ($lang === 'en') {
            return ['ok' => false, 'filled' => 0, 'errors' => ['English is the source language and cannot be seeded.']];
        }

        $texts = [];
        $keys = [];
        foreach ($this->store->all() as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ($section !== null && ($entry['section'] ?? '') !== $section) {
                continue;
            }

            $existing = trim((string) (($entry['translations'][$lang] ?? '') ?: ''));
            if ($existing !== '') {
                continue;
            }

            $source = trim((string) ($entry['translations']['en'] ?? ''));
            if ($source === '') {
                continue;
            }

            $keys[] = $key;
            $texts[] = $source;
        }

        if ($texts === []) {
            return ['ok' => true, 'filled' => 0];
        }

        try {
            $translated = $this->translator->translate($texts, 'en', $lang);
        } catch (\Throwable $e) {
            return ['ok' => false, 'filled' => 0, 'errors' => [$e->getMessage()]];
        }

        if (count($translated) !== count($texts)) {
            return ['ok' => false, 'filled' => 0, 'errors' => ['Translation count mismatch.']];
        }

        $map = [];
        foreach ($keys as $i => $key) {
            $map[$key] = $translated[$i];
        }

        $filled = $this->store->fillEmptyCells($lang, $map, $section);

        return ['ok' => true, 'filled' => $filled];
    }
}
