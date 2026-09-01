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
        private string $captionTranslationDirPath,
        private CaptionIntakeNormalizer $normalizer = new CaptionIntakeNormalizer(),
        private CaptionFilename $captionFilename = new CaptionFilename(),
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
        $title = (string) ($video['title'] ?? $vimeoId);
        if ($video !== null) {
            foreach ($video['captions'] ?? [] as $caption) {
                $captionLang = $caption['lang'] ?? '';
                if ($captionLang !== '') {
                    $catalogLabels[$captionLang] = $caption['label'] ?? $captionLang;
                }
            }
        }

        $newCaptions = [];
        /*
         * A multi-file upload is all-or-nothing. Files are written one at a
         * time but the catalog is only updated once, at the end, so a failure
         * on file 3 used to leave files 1 and 2 on disk referenced by nothing
         * — or, worse, leave a replaced caption overwritten by a batch that
         * was never accepted. $writtenFiles records enough to undo either.
         *
         * @var list<array{path: string, previousContent: string|false}> $writtenFiles
         */
        $writtenFiles = [];
        foreach ($uploads as $upload) {
            $lang = $upload['lang'];
            if ($lang === '') {
                self::rollbackWrittenFiles($writtenFiles);
                return ['ok' => false, 'error' => 'Seleccioneu una llengua vàlida per a cada fitxer de subtítols.', 'vimeoWarnings' => []];
            }

            $label = $labelMap[$lang] ?? $catalogLabels[$lang] ?? null;
            if ($label === null) {
                self::rollbackWrittenFiles($writtenFiles);
                return ['ok' => false, 'error' => 'Seleccioneu una llengua vàlida per a cada fitxer de subtítols.', 'vimeoWarnings' => []];
            }

            /*
             * Normalise before touching the destination. The previous order
             * unlinked the existing caption first and only then converted, so a
             * malformed upload destroyed the caption it was meant to replace.
             */
            try {
                $content = $this->normalizer->normalize($upload['tmpPath'], $upload['originalName']);
            } catch (\InvalidArgumentException $e) {
                self::rollbackWrittenFiles($writtenFiles);
                return ['ok' => false, 'error' => $e->getMessage(), 'vimeoWarnings' => []];
            }

            $filename = $this->captionFilename->forVideo($title, $lang);
            $destPath = $this->captionsDirPath . '/' . $filename;
            // Captured before the write, so a rollback can put back exactly
            // what was there (or remove the file when there was nothing).
            $previousContent = is_file($destPath) ? file_get_contents($destPath) : false;

            try {
                if (file_put_contents($destPath, $content) === false) {
                    throw new \RuntimeException('No s\'ha pogut desar el fitxer de subtítols.');
                }
                $writtenFiles[] = ['path' => $destPath, 'previousContent' => $previousContent];
            } catch (\Throwable $e) {
                self::rollbackWrittenFiles($writtenFiles);
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
            self::rollbackWrittenFiles($writtenFiles);
            return ['ok' => false, 'error' => $e->getMessage(), 'vimeoWarnings' => []];
        }

        // New caption content invalidates any prior auto-translate job's
        // premise (its captured master caption), so it can't be trusted
        // to silently re-merge stale translations back in later.
        (new JobManager($this->captionTranslationDirPath . '/' . $vimeoId))->cancel();

        return [
            'ok' => true,
            'vimeoWarnings' => $result['vimeoWarnings'],
            'captions' => $result['captions'],
            'masterCaptionLang' => $result['masterCaptionLang'],
        ];
    }

    /**
     * Undo the caption files this batch wrote, newest first: restore whatever
     * each destination held before, or delete it if it held nothing.
     *
     * @param list<array{path: string, previousContent: string|false}> $writtenFiles
     */
    private static function rollbackWrittenFiles(array $writtenFiles): void
    {
        foreach (array_reverse($writtenFiles) as $written) {
            if ($written['previousContent'] === false) {
                @unlink($written['path']);
                continue;
            }
            @file_put_contents($written['path'], $written['previousContent']);
        }
    }
}
