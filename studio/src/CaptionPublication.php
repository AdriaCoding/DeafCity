<?php

namespace Studio;

/**
 * Publishes server-owned Caption files and best-effort mirrors them to Vimeo.
 *
 * The server file and Catalog are authoritative. Vimeo is deliberately last
 * so an unavailable mirror never makes a valid server Publication fail.
 */
class CaptionPublication
{
    public function __construct(
        private CatalogEditor $catalogEditor,
        private VimeoClient $vimeoClient,
        private string $captionsDirPath,
    ) {}

    /**
     * @param list<array{lang: string, label: string, file: string}> $captions
     * @return array{ok: bool, vimeoWarnings: list<string>, captions: list<array<string, mixed>>, masterCaptionLang: string}
     */
    public function publish(string $vimeoId, array $captions, bool $syncToVimeo = true): array
    {
        $this->catalogEditor->upsertCaptions($vimeoId, $captions);
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            throw new \RuntimeException("Video $vimeoId not found in catalog.");
        }

        return [
            'ok' => true,
            'vimeoWarnings' => $syncToVimeo
                ? $this->mirror($vimeoId, $video['captions'] ?? [])
                : [],
            'captions' => $video['captions'] ?? [],
            'masterCaptionLang' => $video['master_caption_lang']
                ?? (($video['captions'][0]['lang'] ?? '') ?: ''),
        ];
    }

    /**
     * Mirror an already-persisted server Caption set, used by bulk repair.
     *
     * @param list<array<string, mixed>> $captions
     * @return list<string> labels that could not be mirrored
     */
    public function mirror(string $vimeoId, array $captions): array
    {
        try {
            $tracks = $this->vimeoClient->getTextTracks($vimeoId);
            foreach ($tracks as $track) {
                try {
                    $this->vimeoClient->deleteTextTrack($track['uri']);
                } catch (\Throwable) {
                    // A stale track must not prevent the server master mirror.
                }
            }
        } catch (\Throwable) {
            // Vimeo may be unavailable; each upload below remains best effort.
        }

        $warnings = [];
        foreach ($captions as $caption) {
            $lang = (string) ($caption['lang'] ?? '');
            $file = (string) ($caption['file'] ?? '');
            if ($lang === '' || $file === '') {
                continue;
            }

            $path = $this->captionsDirPath . '/' . $file;
            if (!is_file($path)) {
                continue;
            }

            try {
                $this->vimeoClient->uploadAndActivateTextTrack(
                    $vimeoId,
                    $path,
                    $lang,
                    (string) ($caption['label'] ?? $lang),
                );
            } catch (\Throwable) {
                $warnings[] = (string) ($caption['label'] ?? $lang);
            }
        }

        return $warnings;
    }
}
