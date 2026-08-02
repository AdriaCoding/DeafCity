<?php

namespace Studio;

class VimeoPushSync
{
    private const MAX_RATE_LIMIT_WAITS = 5;
    private const MAX_TRANSIENT_ATTEMPTS = 3;
    private const TRANSIENT_BASE_SLEEP_SECONDS = 1;

    private CaptionPublication $publication;

    /** @var callable(int):void */
    private $sleeper;

    /**
     * @param callable(int):void|null $sleeper Injected sleep for tests; defaults to PHP sleep().
     */
    public function __construct(
        private VimeoClient $vimeoClient,
        private StudioConfig $studioConfig,
        private CatalogEditor $catalogEditor,
        private string $captionsDirPath,
        ?callable $sleeper = null,
    ) {
        $this->publication = new CaptionPublication(
            $catalogEditor,
            $vimeoClient,
            $captionsDirPath,
        );
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            if ($seconds > 0) {
                sleep($seconds);
            }
        };
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{ok: bool, skipped?: bool, thumbnailUpdated?: bool, abort?: string}
     */
    public function syncVideo(array $entry): array
    {
        $vimeoId = (string) ($entry['vimeo_id'] ?? '');
        if ($vimeoId === '') {
            return ['ok' => false, 'skipped' => true];
        }

        $rateLimitWaits = 0;
        $transientAttempt = 0;

        while (true) {
            try {
                $this->vimeoClient->updateTitle($vimeoId, (string) ($entry['title'] ?? ''));
                break;
            } catch (VimeoNotFoundException) {
                return ['ok' => false, 'skipped' => true];
            } catch (VimeoUnauthorizedException|VimeoForbiddenException $e) {
                return ['ok' => false, 'skipped' => false, 'abort' => $e->getMessage()];
            } catch (VimeoRateLimitedException $e) {
                $rateLimitWaits++;
                if ($rateLimitWaits > self::MAX_RATE_LIMIT_WAITS) {
                    return [
                        'ok' => false,
                        'skipped' => false,
                        'abort' => 'Vimeo rate limit: exceeded max waits (' . self::MAX_RATE_LIMIT_WAITS . '). ' . $e->getMessage(),
                    ];
                }
                ($this->sleeper)($e->secondsUntilReset() + 1);
                continue;
            } catch (VimeoTransientException) {
                $transientAttempt++;
                if ($transientAttempt >= self::MAX_TRANSIENT_ATTEMPTS) {
                    return ['ok' => false, 'skipped' => true];
                }
                ($this->sleeper)(self::TRANSIENT_BASE_SLEEP_SECONDS * (2 ** ($transientAttempt - 1)));
                continue;
            } catch (\Throwable) {
                return ['ok' => false, 'skipped' => true];
            }
        }

        try {
            $this->vimeoClient->setTags($vimeoId, $entry['tags'] ?? []);
        } catch (\Throwable) {
            // non-fatal — title already pushed
        }

        $this->publication->mirror($vimeoId, $entry['captions'] ?? []);

        $thumbnailUpdated = $this->refreshThumbnail($vimeoId);

        return ['ok' => true, 'thumbnailUpdated' => $thumbnailUpdated];
    }

    /**
     * @return array{synced: int, skipped: int, total: int, abort?: string}
     */
    public function syncAll(): array
    {
        $videos = $this->catalogEditor->getAllVideos();
        $total = count($videos);
        $synced = 0;
        $skipped = 0;

        foreach ($videos as $entry) {
            $result = $this->syncVideo($entry);
            if (isset($result['abort'])) {
                return ['synced' => $synced, 'skipped' => $skipped, 'total' => $total, 'abort' => $result['abort']];
            }
            if (($result['skipped'] ?? false) === true) {
                $skipped++;
            } else {
                $synced++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'total' => $total];
    }

    private function refreshThumbnail(string $vimeoId): bool
    {
        $rateLimitWaits = 0;

        while (true) {
            try {
                $thumbnailUrl = $this->vimeoClient->getThumbnailUrl($vimeoId);
                break;
            } catch (VimeoRateLimitedException $e) {
                $rateLimitWaits++;
                if ($rateLimitWaits > self::MAX_RATE_LIMIT_WAITS) {
                    return false;
                }
                ($this->sleeper)($e->secondsUntilReset() + 1);
                continue;
            } catch (\Throwable) {
                return false;
            }
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
