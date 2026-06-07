<?php

namespace Studio;

class VimeoLocaleRegistry
{
    /** @var string[] */
    private array $codes;

    public function __construct(private readonly string $jsonPath)
    {
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            throw new \RuntimeException('Could not read Vimeo locale registry.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid Vimeo locale registry JSON.');
        }

        $this->codes = [];
        foreach ($data['locales'] ?? [] as $entry) {
            $code = (string) ($entry['code'] ?? '');
            if ($code !== '') {
                $this->codes[] = $code;
            }
        }
    }

    public function isValidCode(string $code): bool
    {
        return in_array($code, $this->codes, true);
    }

    /** @return string[] */
    public function allCodes(): array
    {
        return $this->codes;
    }
}
