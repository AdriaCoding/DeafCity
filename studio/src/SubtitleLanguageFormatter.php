<?php

namespace Studio;

class SubtitleLanguageFormatter
{
    /**
     * @return array{id: string, label: string}|null
     */
    public function format(string $code, string $name): ?array
    {
        $code = trim($code);
        $name = trim($name);

        if ($code === '' || $name === '') {
            return null;
        }

        $id = $this->slugifyCode($code);
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'label' => $name,
        ];
    }

    public function slugifyCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $code);
        if ($ascii === false) {
            $ascii = $code;
        }

        $lower = strtolower($ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return trim($slug, '-');
    }
}
