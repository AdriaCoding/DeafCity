<?php

namespace Studio;

class VideoVisibilityHandler
{
    public function __construct(private CatalogEditor $catalogEditor) {}

    /** @return array{ok: bool, error?: string} */
    public function handle(string $vimeoId, bool $invisible): array
    {
        if (trim($vimeoId) === '') {
            return ['ok' => false, 'error' => 'Falta l\'identificador del vídeo.'];
        }

        try {
            $this->catalogEditor->setVideoInvisible($vimeoId, $invisible);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true];
    }
}
