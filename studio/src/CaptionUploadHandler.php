<?php

namespace Studio;

class CaptionUploadHandler
{
    private CaptionPublication $publication;

    public function __construct(
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
        private StudioConfig $studioConfig,
        private string $captionsDirPath,
        private WebVttValidator $vttValidator = new WebVttValidator(),
        private SrtToVttConverter $srtConverter = new SrtToVttConverter(),
        private IntakeSourceDetector $sourceDetector = new IntakeSourceDetector(),
    ) {
        $this->publication = new CaptionPublication(
            $catalogEditor,
            $vimeoClient,
            $captionsDirPath,
        );
    }

    /**
     * @param list<array{lang: string, tmpPath: string, originalName: string}> $uploads
     * @return array{ok: bool, error?: string, vimeoWarnings: string[], captions?: list<array{lang: string, label: string, file: string}>}
     */
    public function handle(string $vimeoId, array $uploads, bool $syncToVimeo = true): array
    {
        if ($uploads === []) {
            return ['ok' => true, 'vimeoWarnings' => []];
        }

        $labelMap = [];
        foreach ($this->studioConfig->getSubtitleLanguages() as $lang) {
            $labelMap[$lang['id']] = $lang['label'];
        }

        $catalogLabels = [];
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        if ($video !== null) {
            foreach ($video['captions'] ?? [] as $caption) {
                $captionLang = $caption['lang'] ?? '';
                if ($captionLang !== '') {
                    $catalogLabels[$captionLang] = $caption['label'] ?? $captionLang;
                }
            }
        }

        $newCaptions = [];
        foreach ($uploads as $upload) {
            $lang = $upload['lang'];
            if ($lang === '') {
                return ['ok' => false, 'error' => 'Seleccioneu una llengua vàlida per a cada fitxer de subtítols.', 'vimeoWarnings' => []];
            }

            $label = $labelMap[$lang] ?? $catalogLabels[$lang] ?? null;
            if ($label === null) {
                return ['ok' => false, 'error' => 'Seleccioneu una llengua vàlida per a cada fitxer de subtítols.', 'vimeoWarnings' => []];
            }

            try {
                $this->validateCaptionFile($upload['tmpPath'], $upload['originalName']);
            } catch (\InvalidArgumentException $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'vimeoWarnings' => []];
            }

            $filename = "$vimeoId.$lang.vtt";
            $destPath = $this->captionsDirPath . '/' . $filename;

            try {
                if (is_file($destPath)) {
                    unlink($destPath);
                }
                if ($this->sourceDetector->isSubRip($upload['tmpPath'], $upload['originalName'])) {
                    $vttContent = $this->srtConverter->convert($upload['tmpPath']);
                    if (file_put_contents($destPath, $vttContent) === false) {
                        throw new \RuntimeException('No s\'ha pogut desar el fitxer de subtítols.');
                    }
                } else {
                    if (!copy($upload['tmpPath'], $destPath)) {
                        throw new \RuntimeException('No s\'ha pogut desar el fitxer de subtítols.');
                    }
                }
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage(), 'vimeoWarnings' => []];
            }

            $newCaptions[] = [
                'lang' => $lang,
                'label' => $label,
                'file' => $filename,
            ];
        }

        try {
            $result = $this->publication->publish($vimeoId, $newCaptions, $syncToVimeo);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'vimeoWarnings' => []];
        }

        return [
            'ok' => true,
            'vimeoWarnings' => $result['vimeoWarnings'],
            'captions' => $result['captions'],
            'masterCaptionLang' => $result['masterCaptionLang'],
        ];
    }

    private function validateCaptionFile(string $tmpPath, string $originalName): void
    {
        if ($this->sourceDetector->isSubRip($tmpPath, $originalName)) {
            return;
        }

        $this->vttValidator->validate($tmpPath, $originalName);
    }

}
