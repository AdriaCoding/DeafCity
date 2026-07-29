<?php

namespace Studio;

class SubtitleOutputBasename
{
    /**
     * Strip a trailing _{lang} suffix from the audio stem when it matches the source language id.
     */
    public function baseStem(string $originalFilename, string $sourceLanguageId): string
    {
        $stem = $originalFilename;
        $lowerStem = strtolower($stem);

        $suffix = strtolower($sourceLanguageId);
        if (strlen($suffix) < 2) {
            return $stem;
        }

        $trailing = '_' . $suffix;
        if (str_ends_with($lowerStem, $trailing)) {
            return substr($stem, 0, -strlen($trailing));
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
        $code = strtoupper($subtitleLanguageId);

        return $base . '_' . $code . '.' . $ext;
    }
}
