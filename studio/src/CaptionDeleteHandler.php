<?php

namespace Studio;

class CaptionDeleteHandler
{
    public function __construct(
        private CatalogEditor $catalogEditor,
        private string $captionsDirPath,
    ) {}

    /**
     * @return array{ok: bool, error?: string, newMaster?: string}
     */
    public function handle(string $vimeoId, string $lang): array
    {
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            return ['ok' => false, 'error' => "Video $vimeoId not found in catalog."];
        }

        $captionFile = null;
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['lang'] ?? '') === $lang) {
                $captionFile = $caption['file'] ?? null;
                break;
            }
        }

        if ($captionFile === null) {
            return ['ok' => false, 'error' => "Caption lang '$lang' not found for video $vimeoId."];
        }

        $wasMaster = ($video['master_caption_lang'] ?? '') === $lang;

        try {
            $this->catalogEditor->deleteCaption($vimeoId, $lang);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $path = $this->captionsDirPath . '/' . $captionFile;
        if (is_file($path)) {
            unlink($path);
        }

        $result = ['ok' => true];
        if ($wasMaster) {
            $updated = $this->catalogEditor->findVideoByVimeoId($vimeoId);
            $remaining = $updated['captions'] ?? [];
            if ($remaining !== []) {
                $result['newMaster'] = $updated['master_caption_lang'] ?? $remaining[0]['lang'];
            }
        }

        return $result;
    }
}
