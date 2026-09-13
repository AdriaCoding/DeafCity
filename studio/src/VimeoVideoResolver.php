<?php

namespace Studio;

class VimeoVideoResolver
{
    public function __construct(
        private VimeoIdParser $parser,
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
    ) {}

    /** @return array{ok: bool, vimeo_id?: string, title?: string, thumbnail_url?: ?string, thumbnail_base?: ?string, tags?: list<string>, error?: string} */
    public function resolve(string $input): array
    {
        try {
            $vimeoId = $this->parser->parse($input);
        } catch (InvalidVimeoIdException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($this->catalogEditor->findVideoByVimeoId($vimeoId) !== null) {
            return ['ok' => false, 'error' => 'Aquest vídeo ja és al catàleg.'];
        }

        try {
            $title = $this->vimeoClient->getVideo($vimeoId);
        } catch (VimeoNotFoundException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'No s\'han pogut obtenir les dades del vídeo de Vimeo.'];
        }

        $thumbnailUrl = null;
        $thumbnailBase = null;
        try {
            $meta = $this->vimeoClient->getThumbnailMeta($vimeoId);
            $url = $meta['thumbnail_url'] ?? null;
            $base = $meta['thumbnail_base'] ?? null;
            $thumbnailUrl = is_string($url) && $url !== '' ? $url : null;
            $thumbnailBase = is_string($base) && $base !== '' ? $base : null;
        } catch (\Throwable) {
            // non-fatal
        }

        $tags = [];
        try {
            $tags = $this->vimeoClient->getTagNames($vimeoId);
        } catch (\Throwable) {
            // non-fatal
        }

        return [
            'ok' => true,
            'vimeo_id' => $vimeoId,
            'title' => $title,
            'thumbnail_url' => $thumbnailUrl,
            'thumbnail_base' => $thumbnailBase,
            'tags' => $tags,
        ];
    }
}
