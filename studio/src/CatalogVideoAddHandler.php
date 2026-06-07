<?php

namespace Studio;

class CatalogVideoAddHandler
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
    ) {}

    /** @return array{ok: bool, video?: array<string, mixed>, error?: string} */
    public function handle(string $vimeoId, string $signLanguage, string $edition, string $title): array
    {
        $vimeoId = trim($vimeoId);
        $signLanguage = trim($signLanguage);
        $edition = trim($edition);
        $title = trim($title);

        if ($vimeoId === '' || $signLanguage === '' || $edition === '') {
            return ['ok' => false, 'error' => 'Falten camps obligatoris.'];
        }

        if ($this->catalogEditor->findVideoByVimeoId($vimeoId) !== null) {
            return ['ok' => false, 'error' => 'Aquest vídeo ja és al catàleg.'];
        }

        try {
            $vimeoTitle = $this->vimeoClient->getVideo($vimeoId);
        } catch (VimeoNotFoundException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'No s\'han pogut obtenir les dades del vídeo de Vimeo.'];
        }

        $resolvedTitle = $title !== '' ? $title : $vimeoTitle;

        $thumbnailUrl = null;
        try {
            $thumbnailUrl = $this->vimeoClient->getThumbnailUrl($vimeoId);
        } catch (\Throwable) {
            // non-fatal
        }

        $tags = [];
        try {
            $tags = $this->vimeoClient->getTagNames($vimeoId);
        } catch (\Throwable) {
            // non-fatal
        }

        try {
            $video = $this->catalogEditor->addVideo(
                $vimeoId,
                $resolvedTitle,
                $signLanguage,
                $edition,
                $thumbnailUrl,
                $tags,
            );
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'video' => $video];
    }
}
