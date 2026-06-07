<?php

namespace Studio;

class CaptionReplaceHandler
{
    public function __construct(
        private CatalogEditor $catalogEditor,
        private CaptionUploadHandler $uploadHandler,
    ) {}

    /**
     * @param array{lang: string, tmpPath: string, originalName: string} $upload
     * @return array{ok: bool, error?: string, caption?: array{lang: string, label: string, file: string}, vimeoWarnings?: string[]}
     */
    public function handle(string $vimeoId, string $lang, array $upload): array
    {
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            return ['ok' => false, 'error' => "Video $vimeoId not found in catalog."];
        }

        $langs = array_column($video['captions'] ?? [], 'lang');
        if (!in_array($lang, $langs, true)) {
            return ['ok' => false, 'error' => "Caption lang '$lang' not found for video $vimeoId."];
        }

        $upload['lang'] = $lang;
        $result = $this->uploadHandler->handle($vimeoId, [$upload], syncToVimeo: false);
        if (!$result['ok']) {
            return $result;
        }

        $caption = null;
        foreach ($result['captions'] ?? [] as $entry) {
            if (($entry['lang'] ?? '') === $lang) {
                $caption = $entry;
                break;
            }
        }

        return [
            'ok' => true,
            'caption' => $caption,
        ];
    }
}
