<?php

namespace Studio;

class CaptionUploadHandler
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
        private StudioConfig $studioConfig,
        private string $captionsDirPath,
        private WebVttValidator $vttValidator = new WebVttValidator(),
        private SrtToVttConverter $srtConverter = new SrtToVttConverter(),
        private IntakeSourceDetector $sourceDetector = new IntakeSourceDetector(),
    ) {}

    /**
     * @param list<array{lang: string, tmpPath: string, originalName: string}> $uploads
     * @return array{ok: bool, error?: string, vimeoWarnings: string[]}
     */
    public function handle(string $vimeoId, array $uploads): array
    {
        if ($uploads === []) {
            return ['ok' => true, 'vimeoWarnings' => []];
        }

        $labelMap = [];
        foreach ($this->studioConfig->getSubtitleLanguages() as $lang) {
            $labelMap[$lang['id']] = $lang['label'];
        }

        $newCaptions = [];
        foreach ($uploads as $upload) {
            $lang = $upload['lang'];
            if ($lang === '' || !isset($labelMap[$lang])) {
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
                'label' => $labelMap[$lang],
                'file' => $filename,
            ];
        }

        try {
            $this->catalogEditor->upsertCaptions($vimeoId, $newCaptions);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'vimeoWarnings' => []];
        }

        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        $allCaptions = $video['captions'] ?? [];

        return [
            'ok' => true,
            'vimeoWarnings' => $this->syncVimeoCaptions($vimeoId, $allCaptions),
        ];
    }

    private function validateCaptionFile(string $tmpPath, string $originalName): void
    {
        if ($this->sourceDetector->isSubRip($tmpPath, $originalName)) {
            return;
        }

        $this->vttValidator->validate($tmpPath, $originalName);
    }

    /** @return string[] */
    private function syncVimeoCaptions(string $vimeoId, array $captions): array
    {
        try {
            $tracks = $this->vimeoClient->getTextTracks($vimeoId);
            foreach ($tracks as $track) {
                try {
                    $this->vimeoClient->deleteTextTrack($track['uri']);
                } catch (\Throwable) {
                    // ignore
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $warnings = [];
        foreach ($captions as $caption) {
            $path = $this->captionsDirPath . '/' . ($caption['file'] ?? '');
            if (!is_file($path)) {
                continue;
            }
            try {
                $this->vimeoClient->uploadAndActivateTextTrack(
                    $vimeoId,
                    $path,
                    $caption['lang'],
                    $caption['label'],
                );
            } catch (\Throwable) {
                $warnings[] = $caption['label'];
            }
        }

        return $warnings;
    }
}
