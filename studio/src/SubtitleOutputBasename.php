<?php

namespace Studio;

class SubtitleOutputBasename
{
    public function __construct(private readonly StudioConfig $studioConfig) {}

    /**
     * Strip a trailing _{lang} suffix from the audio stem when it matches the source language.
     */
    public function baseStem(string $originalFilename, string $sourceLanguageId): string
    {
        $stem = $originalFilename;
        $lowerStem = strtolower($stem);

        $suffixes = [];
        $id = strtolower($sourceLanguageId);
        if ($id !== '') {
            $suffixes[] = $id;
        }
        $vimeo = strtolower($this->studioConfig->vimeoCodeFor($sourceLanguageId));
        if ($vimeo !== '' && $vimeo !== $id) {
            $suffixes[] = $vimeo;
        }

        usort($suffixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($suffixes as $suffix) {
            if (strlen($suffix) < 2) {
                continue;
            }
            $trailing = '_' . $suffix;
            if (str_ends_with($lowerStem, $trailing)) {
                return substr($stem, 0, -strlen($trailing));
            }
        }

        return $stem;
    }

    public function transcriptionDownloadFilename(
        string $originalFilename,
        string $sourceLanguageId,
        string $requestedLang,
        string $ext,
    ): string {
        $subtitleLanguageId = $requestedLang !== '' ? $requestedLang : $sourceLanguageId;

        return $this->outputFilename($originalFilename, $sourceLanguageId, $subtitleLanguageId, $ext);
    }

    public function srtFilename(string $originalFilename, string $sourceLanguageId, string $subtitleLanguageId): string
    {
        return $this->outputFilename($originalFilename, $sourceLanguageId, $subtitleLanguageId, 'srt');
    }

    public function outputFilename(
        string $originalFilename,
        string $sourceLanguageId,
        string $subtitleLanguageId,
        string $ext,
    ): string {
        $base = $this->baseStem($originalFilename, $sourceLanguageId);
        $code = strtoupper($this->studioConfig->vimeoCodeFor($subtitleLanguageId));

        return $base . '_' . $code . '.' . $ext;
    }
}
