<?php

namespace Studio;

class CatalogIntakeAddHandler
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private CatalogEditor $catalogEditor,
        private StudioConfig $studioConfig,
        private string $captionsDir,
        private string $captionTranslationDir,
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
        string $masterLang = '',
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
            $this->captionTranslationDir,
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

        // Persist the chosen master caption (the rule can pick a non-first language, e.g. English).
        $uploadedLangs = array_column($captionResult['captions'] ?? [], 'lang');
        if ($masterLang !== '' && in_array($masterLang, $uploadedLangs, true)) {
            try {
                $this->catalogEditor->setMasterCaptionLang($vimeoId, $masterLang);
                $response['video']['master_caption_lang'] = $masterLang;
            } catch (\Throwable) {
                // non-fatal: keep the default master
            }
        }

        return $response;
    }
}
