<?php

namespace Studio;

class WebVttValidator
{
    public function validate(string $filePath, string $originalName): void
    {
        if (!str_ends_with(strtolower($originalName), '.vtt')) {
            throw new \InvalidArgumentException('El fitxer de subtítols ha de ser un fitxer WebVTT (.vtt).');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException('No s\'ha pogut llegir el fitxer de subtítols pujat.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                if (!preg_match('/^WEBVTT([ \t]|$)/', $trimmed)) {
                    throw new \InvalidArgumentException(
                        'El fitxer de subtítols ha de començar amb una capçalera WEBVTT.'
                    );
                }
                return;
            }

            throw new \InvalidArgumentException(
                'El fitxer de subtítols ha de començar amb una capçalera WEBVTT.'
            );
        } finally {
            fclose($handle);
        }
    }
}
