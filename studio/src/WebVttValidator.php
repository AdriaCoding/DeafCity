<?php

namespace Studio;

class WebVttValidator
{
    public function validate(string $filePath, string $originalName): void
    {
        if (!str_ends_with(strtolower($originalName), '.vtt')) {
            throw new \InvalidArgumentException('Subtitle file must be a WebVTT (.vtt) file.');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException('Could not read the uploaded subtitle file.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                if (!preg_match('/^WEBVTT([ \t]|$)/', $trimmed)) {
                    throw new \InvalidArgumentException(
                        'Subtitle file must start with a WEBVTT header.'
                    );
                }
                return;
            }

            throw new \InvalidArgumentException(
                'Subtitle file must start with a WEBVTT header.'
            );
        } finally {
            fclose($handle);
        }
    }
}
