<?php

namespace Studio;

class DeafcityMapLabel
{
    public function format(string $city, string $code): ?string
    {
        $city = trim($city);
        $code = strtoupper(trim($code));

        if ($city === '' || $code === '') {
            return null;
        }

        if (!preg_match('/^[A-Z][A-Z0-9]{1,11}$/', $code)) {
            return null;
        }

        return 'DEAF.city ' . mb_strtoupper($city, 'UTF-8') . ' ' . $code;
    }
}
