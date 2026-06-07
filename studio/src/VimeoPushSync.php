<?php

namespace Studio;

class VimeoPushSync
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private StudioConfig $studioConfig,
        private CatalogEditor $catalogEditor,
        private string $captionsDirPath,
    ) {}

    /**
     * @param array<string, mixed> $entry
     * @return array{ok: bool, skipped?: bool, thumbnailBackfilled?: bool}
     */
    public function syncVideo(array $entry): array
    {
        $vimeoId = (string) ($entry['vimeo_id'] ?? '');
        if ($vimeoId === '') {
            return ['ok' => false, 'skipped' => true];
        }

        try {
            $this->vimeoClient->updateTitle($vimeoId, (string) ($entry['title'] ?? ''));
        } catch (VimeoNotFoundException) {
            return ['ok' => false, 'skipped' => true];
        } catch (\Throwable) {
            return ['ok' => false, 'skipped' => true];
        }

        try {
            $this->vimeoClient->setTags($vimeoId, $entry['tags'] ?? []);
        } catch (\Throwable) {
            // non-fatal — title already pushed
        }

        $this->pushCaptions($vimeoId, $entry['captions'] ?? []);

        $thumbnailBackfilled = $this->backfillThumbnailIfMissing($vimeoId, $entry);

        return ['ok' => true, 'thumbnailBackfilled' => $thumbnailBackfilled];
    }

    /**
     * @return array{synced: int, skipped: int, total: int}
     */
    public function syncAll(): array
    {
        $videos = $this->catalogEditor->getAllVideos();
        $total = count($videos);
        $synced = 0;
        $skipped = 0;

        foreach ($videos as $entry) {
            $result = $this->syncVideo($entry);
            if (($result['skipped'] ?? false) === true) {
                $skipped++;
            } else {
                $synced++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'total' => $total];
    }

    /** @param list<array<string, mixed>> $captions */
    private function pushCaptions(string $vimeoId, array $captions): void
    {
        try {
            $tracks = $this->vimeoClient->getTextTracks($vimeoId);
            foreach ($tracks as $track) {
                try {
                    $this->vimeoClient->deleteTextTrack($track['uri']);
                } catch (\Throwable) {
                    // ignore
                }
            }
        } catch (\Throwable) {
            // ignore
        }

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
                    $this->studioConfig->vimeoCodeFor($lang),
                    (string) ($caption['label'] ?? $lang),
                );
            } catch (\Throwable) {
                // non-fatal
            }
        }
    }

    /** @param array<string, mixed> $entry */
    private function backfillThumbnailIfMissing(string $vimeoId, array $entry): bool
    {
        $existing = trim((string) ($entry['thumbnail_url'] ?? ''));
        if ($existing !== '') {
            return false;
        }

        try {
            $thumbnailUrl = $this->vimeoClient->getThumbnailUrl($vimeoId);
        } catch (\Throwable) {
            return false;
        }

        if ($thumbnailUrl === null || $thumbnailUrl === '') {
            return false;
        }

        try {
            $this->catalogEditor->updateThumbnailUrl($vimeoId, $thumbnailUrl);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
