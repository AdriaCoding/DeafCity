<?php

namespace Studio;

class IntakeSourceDetector
{
    /** @var list<string> */
    private const AUDIO_EXTENSIONS = [
        'mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'flac', 'webm', 'wma', 'mp4', 'opus',
    ];

    /**
     * @return 'upload'|'generate'
     */
    public function detect(string $filePath, string $originalName): string
    {
        if ($this->looksLikeWebVtt($filePath)) {
            return 'upload';
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'vtt' || $ext === 'srt') {
            return 'upload';
        }

        if ($ext !== '' && in_array($ext, self::AUDIO_EXTENSIONS, true)) {
            return 'generate';
        }

        if ($this->looksLikeAudio($filePath)) {
            return 'generate';
        }

        throw new \InvalidArgumentException(
            'Pugeu un fitxer WebVTT, SubRip (.srt) o un fitxer d\'àudio de l\'intèrpret (MP3, WAV, M4A…).'
        );
    }

    private function looksLikeWebVtt(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return false;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                return (bool) preg_match('/^WEBVTT([ \t]|$)/', $trimmed);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    private function looksLikeAudio(string $filePath): bool
    {
        $bytes = file_get_contents($filePath, false, null, 0, 12);
        if ($bytes === false || $bytes === '') {
            return false;
        }

        if (str_starts_with($bytes, "ID3") || str_starts_with($bytes, "\xFF\xFB")
            || str_starts_with($bytes, "\xFF\xF3") || str_starts_with($bytes, "\xFF\xF2")) {
            return true;
        }

        if (str_starts_with($bytes, 'RIFF') && strlen($bytes) >= 12 && substr($bytes, 8, 4) === 'WAVE') {
            return true;
        }

        if (str_starts_with($bytes, 'fLaC') || str_starts_with($bytes, 'OggS')) {
            return true;
        }

        if (strlen($bytes) >= 8 && substr($bytes, 4, 4) === 'ftyp') {
            return true;
        }

        return false;
    }
}
