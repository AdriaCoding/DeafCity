<?php

namespace Studio;

class SignLanguageFormatter
{
    /**
     * @return array{id: string, label: string}|null
     */
    public function format(string $code, string $qualifier): ?array
    {
        $code = trim($code);
        $qualifier = trim($qualifier);

        if ($code === '' || $qualifier === '') {
            return null;
        }

        $id = $this->slugifyCode($code);
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'label' => $code . ' ' . $qualifier . ' Sign Language',
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
