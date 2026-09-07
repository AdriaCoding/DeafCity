<?php

namespace Studio;

/**
 * Copies finished translation drafts into server Caption files, publishes
 * them to the Catalog, and optionally mirrors them to Vimeo.
 *
 * The HTTP translate-status poll must pass $syncToVimeo = false so a
 * polling PHP-FPM worker never blocks on the Vimeo API.
 */
class CaptionTranslationFinalizer
{
    /**
     * @param array<string, string> $langLabels
     */
    public function __construct(
        private CatalogEditor $catalogEditor,
        private VimeoClient $vimeoClient,
        private string $captionsDir,
        private array $langLabels,
        private CaptionFilename $captionFilename = new CaptionFilename(),
    ) {}

    /**
     * @return array{saved: list<string>, errors: list<string>}
     */
    public function finalize(
        string $vimeoId,
        string $jobDir,
        TranslationJobState $state,
        bool $syncToVimeo = true,
    ): array {
        $data = $state->read();
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        $title = (string) ($video['title'] ?? $vimeoId);

        $savedLangs = [];
        $errorLangs = [];
        $newCaptions = [];

        foreach ($data['languages'] ?? [] as $lang => $entry) {
            $langStr = (string) $lang;
            if (($entry['status'] ?? '') === 'error') {
                $errorLangs[] = $langStr;
                continue;
            }
            if (($entry['status'] ?? '') !== 'done') {
                continue;
            }

            $srcPath = rtrim($jobDir, '/') . '/draft_' . $langStr . '.srt';
            $destFilename = $this->captionFilename->forVideo($title, $langStr);
            $destPath = $this->captionsDir . '/' . $destFilename;

            if (!is_file($srcPath) || !copy($srcPath, $destPath)) {
                $errorLangs[] = $langStr;
                continue;
            }

            $newCaptions[] = [
                'lang' => $langStr,
                'label' => $this->langLabels[$langStr] ?? $langStr,
                'file' => $destFilename,
            ];
            $savedLangs[] = $langStr;
        }

        if ($newCaptions !== []) {
            try {
                $publication = new CaptionPublication(
                    $this->catalogEditor,
                    $this->vimeoClient,
                    $this->captionsDir,
                );
                $publication->publish($vimeoId, $newCaptions, $syncToVimeo);
            } catch (\Throwable) {
                $errorLangs = array_merge($errorLangs, $savedLangs);
                $savedLangs = [];
            }
        }

        $state->markSaved($savedLangs, $errorLangs);

        return ['saved' => $savedLangs, 'errors' => $errorLangs];
    }
}
