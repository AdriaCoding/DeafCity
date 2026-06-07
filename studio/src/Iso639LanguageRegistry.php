<?php

namespace Studio;

class Iso639LanguageRegistry
{
    /** @var array<string, string> */
    private array $labelsByCode;

    public function __construct(private readonly string $jsonPath)
    {
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            throw new \RuntimeException('Could not read ISO 639 language registry.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid ISO 639 language registry JSON.');
        }

        $this->labelsByCode = [];
        foreach ($data['languages'] ?? [] as $entry) {
            $code = (string) ($entry['code'] ?? '');
            $label = (string) ($entry['label'] ?? '');
            if ($code !== '' && $label !== '') {
                $this->labelsByCode[$code] = $label;
            }
        }
    }

    public function isValidCode(string $code): bool
    {
        return isset($this->labelsByCode[$code]);
    }

    public function labelFor(string $code): ?string
    {
        return $this->labelsByCode[$code] ?? null;
    }
}
