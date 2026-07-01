<?php

namespace Studio;

class TranscriptionIntakeFileKind
{
    /** @var list<string> */
    private const AUDIO_EXTENSIONS = [
        'mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'flac', 'webm', 'wma', 'mp4', 'opus',
    ];

    /** @return 'audio'|'subtitle'|null */
    public static function fromFilename(string $filename): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'vtt' || $ext === 'srt') {
            return 'subtitle';
        }
        if ($ext !== '' && in_array($ext, self::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }

        return null;
    }
}
