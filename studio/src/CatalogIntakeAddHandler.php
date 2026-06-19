<?php

namespace Studio;

class CatalogIntakeAddHandler
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
        private StudioConfig $studioConfig,
        private string $captionsDir,
    ) {}

    /**
     * @param list<string> $tags
     * @param list<array{lang: string, tmpPath: string, originalName: string}> $captionUploads
     * @return array{ok: bool, video?: array<string, mixed>, edition_label?: string, captionError?: string, captionWarnings?: list<string>, error?: string}
     */
    public function handle(
        string $vimeoId,
        string $signLanguage,
        string $edition,
        string $title,
        string $typology,
        array $tags,
        array $captionUploads,
    ): array {
        $result = (new CatalogVideoAddHandler($this->vimeoClient, $this->catalogEditor))
            ->handle($vimeoId, $signLanguage, $edition, $title, $typology, $tags);

        if (!$result['ok']) {
            return $result;
        }

        $editionLabel = $edition;
        foreach ($this->studioConfig->getEditions() as $ed) {
            if (($ed['id'] ?? '') === $edition) {
                $editionLabel = $ed['label'] ?? $edition;
                break;
            }
        }

        $response = [
            'ok' => true,
            'video' => $result['video'],
            'edition_label' => $editionLabel,
        ];

        if ($captionUploads === []) {
            return $response;
        }

        $captionResult = (new CaptionUploadHandler(
            $this->vimeoClient,
            $this->catalogEditor,
            $this->studioConfig,
            $this->captionsDir,
        ))->handle($vimeoId, $captionUploads, syncToVimeo: false);

        if (!$captionResult['ok']) {
            $response['captionError'] = $captionResult['error'] ?? 'Error en pujar els subtítols.';
            return $response;
        }

        if (($captionResult['vimeoWarnings'] ?? []) !== []) {
            $response['captionWarnings'] = $captionResult['vimeoWarnings'];
        }

        if (isset($captionResult['captions'])) {
            $response['video']['captions'] = $captionResult['captions'];
            if (isset($captionResult['masterCaptionLang'])) {
                $response['video']['master_caption_lang'] = $captionResult['masterCaptionLang'];
            }
        }

        return $response;
    }
}
