<?php

namespace Studio;

class VideoEditHandler
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
    ) {}

    /** @return array{ok: bool, vimeoWarning: ?string} */
    public function handle(string $videoId, string $title, array $tags): array
    {
        $vimeoWarning = null;

        try {
            $this->vimeoClient->updateTitle($videoId, $title);
            $this->vimeoClient->setTags($videoId, $tags);
        } catch (\Throwable $e) {
            $vimeoWarning = $e->getMessage();
        }

        try {
            $this->catalogEditor->updateVideo($videoId, $title, $tags);
        } catch (\Throwable $e) {
            return ['ok' => false, 'vimeoWarning' => $vimeoWarning];
        }

        return ['ok' => true, 'vimeoWarning' => $vimeoWarning];
    }
}
