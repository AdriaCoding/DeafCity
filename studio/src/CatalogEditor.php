<?php

namespace Studio;

class CatalogEditor
{
    public function __construct(private readonly string $catalogFilePath) {}

    public function updateVideo(
        string $videoId,
        string $title,
        array $tags,
        ?string $typology = null,
        ?string $signLanguage = null,
        ?string $edition = null,
    ): void {
        $this->withLockedCatalog(function (array &$catalog) use ($videoId, $title, $tags, $typology, $signLanguage, $edition): void {
            $found = false;
            foreach ($catalog['videos'] as &$entry) {
                if (($entry['vimeo_id'] ?? '') === $videoId) {
                    $entry['title'] = $title;
                    $entry['tags'] = $tags;
                    if ($typology !== null && $typology !== '') {
                        $entry['typology'] = $typology;
                    } else {
                        unset($entry['typology']);
                    }
                    if ($signLanguage !== null && $signLanguage !== '') {
                        $entry['sign_language'] = $signLanguage;
                        // The entry id embeds the sign language; keep it consistent.
                        $entry['id'] = $signLanguage . '_' . $videoId;
                    }
                    if ($edition !== null && $edition !== '') {
                        $entry['edition'] = $edition;
                    }
                    $found = true;
                    break;
                }
            }
            unset($entry);

            if (!$found) {
                throw new \RuntimeException("Video $videoId not found in catalog.");
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function addVideo(
        string $vimeoId,
        string $title,
        string $signLanguage,
        string $edition,
        ?string $thumbnailUrl = null,
        array $tags = [],
        ?string $typology = null,
        ?string $participant = null,
        ?string $embedUrl = null,
    ): array {
        // Fast unlocked pre-check for the common case; the authoritative check
        // happens inside the lock below so two concurrent adds of the same
        // vimeo_id can't both pass before either has written.
        if ($this->findVideoByVimeoId($vimeoId) !== null) {
            throw new \RuntimeException('Aquest vídeo ja és al catàleg.');
        }

        $entry = [
            'id' => $signLanguage . '_' . $vimeoId,
            'vimeo_id' => $vimeoId,
            'title' => $title,
            'sign_language' => $signLanguage,
            'edition' => $edition,
            'tags' => array_values($tags),
            'captions' => [],
        ];
        if ($typology !== null && $typology !== '') {
            $entry['typology'] = $typology;
        }
        if ($thumbnailUrl !== null && $thumbnailUrl !== '') {
            $entry['thumbnail_url'] = $thumbnailUrl;
        }
        if ($participant !== null && trim($participant) !== '') {
            $entry['participant'] = trim($participant);
        }
        if ($embedUrl !== null && trim($embedUrl) !== '') {
            $entry['embed_url'] = trim($embedUrl);
        }

        return $this->withLockedCatalog(function (array &$catalog) use ($vimeoId, $entry): array {
            foreach ($catalog['videos'] as $existing) {
                if (($existing['vimeo_id'] ?? '') === $vimeoId) {
                    throw new \RuntimeException('Aquest vídeo ja és al catàleg.');
                }
            }
            $catalog['videos'][] = $entry;
            return $entry;
        }, allowMissing: true);
    }

    /**
     * @param list<array{lang: string, label: string, file: string}> $newCaptions
     */
    public function upsertCaptions(string $videoId, array $newCaptions): void
    {
        $this->withLockedCatalog(function (array &$catalog) use ($videoId, $newCaptions): void {
            $found = false;
            foreach ($catalog['videos'] as &$entry) {
                if (($entry['vimeo_id'] ?? '') !== $videoId) {
                    continue;
                }

                $byLang = [];
                foreach ($entry['captions'] ?? [] as $caption) {
                    $lang = $caption['lang'] ?? '';
                    if ($lang !== '') {
                        $byLang[$lang] = $caption;
                    }
                }
                $hadCaptions = $byLang !== [];
                foreach ($newCaptions as $caption) {
                    $byLang[$caption['lang']] = $caption;
                }
                $entry['captions'] = array_values($byLang);
                if (!$hadCaptions && $entry['captions'] !== [] && !isset($entry['master_caption_lang'])) {
                    $entry['master_caption_lang'] = $entry['captions'][0]['lang'];
                }
                $found = true;
                break;
            }
            unset($entry);

            if (!$found) {
                throw new \RuntimeException("Video $videoId not found in catalog.");
            }
        });
    }

    public function deleteCaption(string $vimeoId, string $lang): void
    {
        $this->withLockedCatalog(function (array &$catalog) use ($vimeoId, $lang): void {
            $found = false;
            $langFound = false;
            $wasMaster = false;
            foreach ($catalog['videos'] as &$entry) {
                if (($entry['vimeo_id'] ?? '') !== $vimeoId) {
                    continue;
                }

                $found = true;
                $captions = $entry['captions'] ?? [];
                $remaining = [];
                foreach ($captions as $caption) {
                    if (($caption['lang'] ?? '') === $lang) {
                        $langFound = true;
                        if (($entry['master_caption_lang'] ?? '') === $lang) {
                            $wasMaster = true;
                        }
                        continue;
                    }
                    $remaining[] = $caption;
                }

                if (!$langFound) {
                    throw new \InvalidArgumentException("Caption lang '$lang' not found for video $vimeoId.");
                }

                $entry['captions'] = $remaining;
                if ($remaining === []) {
                    unset($entry['master_caption_lang']);
                } elseif ($wasMaster) {
                    $entry['master_caption_lang'] = $remaining[0]['lang'];
                }
                break;
            }
            unset($entry);

            if (!$found) {
                throw new \RuntimeException("Video $vimeoId not found in catalog.");
            }
        });
    }

    public function setMasterCaptionLang(string $videoId, string $lang): void
    {
        $this->withLockedCatalog(function (array &$catalog) use ($videoId, $lang): void {
            $found = false;
            foreach ($catalog['videos'] as &$entry) {
                if (($entry['vimeo_id'] ?? '') !== $videoId) {
                    continue;
                }
                $langs = array_column($entry['captions'] ?? [], 'lang');
                if (!in_array($lang, $langs, true)) {
                    throw new \InvalidArgumentException("Caption lang '$lang' not found for video $videoId.");
                }
                $entry['master_caption_lang'] = $lang;
                $found = true;
                break;
            }
            unset($entry);

            if (!$found) {
                throw new \RuntimeException("Video $videoId not found in catalog.");
            }
        });
    }

    /** @return list<array<string, mixed>> */
    public function getAllVideos(): array
    {
        if (!is_file($this->catalogFilePath)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->catalogFilePath), true);
        if (!is_array($data)) {
            return [];
        }

        return $data['videos'] ?? [];
    }

    /** @return ?array<string, mixed> */
    public function findVideoByVimeoId(string $vimeoId): ?array
    {
        foreach ($this->getAllVideos() as $video) {
            if (($video['vimeo_id'] ?? '') === $vimeoId) {
                return $video;
            }
        }
        return null;
    }

    /** @return string[] */
    public function getReferencedEditionIds(): array
    {
        return $this->collectField('edition');
    }

    /** @return string[] */
    public function getReferencedSignLanguageIds(): array
    {
        return $this->collectField('sign_language');
    }

    /** @return string[] */
    public function getReferencedTypologyIds(): array
    {
        return $this->collectField('typology');
    }

    /** @return string[] */
    public function getReferencedSubtitleLanguageIds(): array
    {
        $seen = [];
        foreach ($this->getAllVideos() as $video) {
            foreach ($video['captions'] ?? [] as $caption) {
                $lang = $caption['lang'] ?? '';
                if ($lang !== '') {
                    $seen[$lang] = true;
                }
            }
        }
        return array_keys($seen);
    }

    /** @return string[] sorted alphabetically */
    public function getAllTags(): array
    {
        $seen = [];
        foreach ($this->getAllVideos() as $video) {
            foreach ($video['tags'] ?? [] as $tag) {
                $seen[$tag] = true;
            }
        }
        $tags = array_keys($seen);
        sort($tags);
        return $tags;
    }

    public function setVideoInvisible(string $videoId, bool $invisible): void
    {
        $this->withLockedCatalog(function (array &$catalog) use ($videoId, $invisible): void {
            $found = false;
            foreach ($catalog['videos'] as &$entry) {
                if (($entry['vimeo_id'] ?? '') !== $videoId) {
                    continue;
                }
                if ($invisible) {
                    $entry['invisible'] = true;
                } else {
                    unset($entry['invisible']);
                }
                $found = true;
                break;
            }
            unset($entry);

            if (!$found) {
                throw new \RuntimeException("Video $videoId not found in catalog.");
            }
        });
    }

    /** @param array<string, mixed> $entry */
    public function isVideoVisible(array $entry): bool
    {
        return ($entry['invisible'] ?? false) !== true;
    }

    public function updateThumbnailUrl(string $videoId, string $thumbnailUrl): void
    {
        $this->withLockedCatalog(function (array &$catalog) use ($videoId, $thumbnailUrl): void {
            $found = false;
            foreach ($catalog['videos'] as &$entry) {
                if (($entry['vimeo_id'] ?? '') === $videoId) {
                    $entry['thumbnail_url'] = $thumbnailUrl;
                    $found = true;
                    break;
                }
            }
            unset($entry);

            if (!$found) {
                throw new \RuntimeException("Video $videoId not found in catalog.");
            }
        });
    }

    /**
     * Upsert a Video from sheet sync. Never modifies captions or invisible.
     * Clears typology when $typology is null. Updates participant when non-empty;
     * leaves existing participant unchanged when incoming is null/empty.
     * Tags are fully replaced by $tags.
     *
     * @param list<string> $tags
     * @return 'added'|'updated'
     */
    public function upsertFromSheet(
        string $vimeoId,
        string $title,
        string $signLanguage,
        string $edition,
        array $tags,
        ?string $typology,
        ?string $participant,
        ?string $thumbnailUrl = null,
        ?string $embedUrl = null,
    ): string {
        return $this->withLockedCatalog(function (array &$catalog) use (
            $vimeoId,
            $title,
            $signLanguage,
            $edition,
            $tags,
            $typology,
            $participant,
            $thumbnailUrl,
            $embedUrl,
        ): string {
            $index = null;
            foreach ($catalog['videos'] as $i => $entry) {
                if (($entry['vimeo_id'] ?? '') === $vimeoId) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                $entry = [
                    'id' => $signLanguage . '_' . $vimeoId,
                    'vimeo_id' => $vimeoId,
                    'title' => $title,
                    'sign_language' => $signLanguage,
                    'edition' => $edition,
                    'tags' => array_values($tags),
                    'captions' => [],
                ];
                if ($typology !== null && $typology !== '') {
                    $entry['typology'] = $typology;
                }
                if ($participant !== null && trim($participant) !== '') {
                    $entry['participant'] = trim($participant);
                }
                if ($thumbnailUrl !== null && $thumbnailUrl !== '') {
                    $entry['thumbnail_url'] = $thumbnailUrl;
                }
                if ($embedUrl !== null && trim($embedUrl) !== '') {
                    $entry['embed_url'] = trim($embedUrl);
                }
                $catalog['videos'][] = $entry;
                return 'added';
            }

            $entry = $catalog['videos'][$index];
            $entry['title'] = $title;
            $entry['sign_language'] = $signLanguage;
            $entry['edition'] = $edition;
            $entry['id'] = $signLanguage . '_' . $vimeoId;
            $entry['tags'] = array_values($tags);
            if ($typology !== null && $typology !== '') {
                $entry['typology'] = $typology;
            } else {
                unset($entry['typology']);
            }
            if ($participant !== null && trim($participant) !== '') {
                $entry['participant'] = trim($participant);
            }
            // Empty/null participant: leave existing value (do not clear).
            if (
                ($thumbnailUrl !== null && $thumbnailUrl !== '')
                && (!isset($entry['thumbnail_url']) || $entry['thumbnail_url'] === '')
            ) {
                $entry['thumbnail_url'] = $thumbnailUrl;
            }
            if (
                ($embedUrl !== null && trim($embedUrl) !== '')
                && (!isset($entry['embed_url']) || $entry['embed_url'] === '')
            ) {
                $entry['embed_url'] = trim($embedUrl);
            }
            // captions + invisible intentionally untouched
            $catalog['videos'][$index] = $entry;
            return 'updated';
        }, allowMissing: true);
    }

    /**
     * Remove Catalog Videos whose vimeo_id is not in $keepVimeoIds.
     *
     * @param list<string> $keepVimeoIds
     */
    public function removeVideosNotIn(array $keepVimeoIds): int
    {
        $keep = array_fill_keys(array_map('strval', $keepVimeoIds), true);

        return $this->withLockedCatalog(function (array &$catalog) use ($keep): int {
            $before = count($catalog['videos']);
            $catalog['videos'] = array_values(array_filter(
                $catalog['videos'],
                static fn(array $entry): bool => isset($keep[(string) ($entry['vimeo_id'] ?? '')]),
            ));
            return $before - count($catalog['videos']);
        });
    }

    /** @return string[] */
    private function collectField(string $field): array
    {
        $seen = [];
        foreach ($this->getAllVideos() as $video) {
            $val = $video[$field] ?? '';
            if ($val !== '') {
                $seen[$val] = true;
            }
        }
        return array_keys($seen);
    }

    /**
     * Open, lock, read, and decode the Catalog; run $mutate against it by
     * reference; write the result back; unlock and close. Centralizes the
     * lock lifecycle so every mutation reads and writes under the same
     * critical section — the property that closes TOCTOU races between
     * concurrent mutations (e.g. two concurrent adds of the same Video).
     *
     * @template T
     * @param callable(array<string, mixed> &$catalog): T $mutate
     * @return T
     */
    private function withLockedCatalog(callable $mutate, bool $allowMissing = false): mixed
    {
        $fp = fopen($this->catalogFilePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open catalog for writing.');
        }

        try {
            flock($fp, LOCK_EX);

            $raw = stream_get_contents($fp);
            $catalog = json_decode($raw ?: '', true);
            if (!is_array($catalog) || !isset($catalog['videos']) || !is_array($catalog['videos'])) {
                if (!$allowMissing) {
                    throw new \RuntimeException('Invalid catalog JSON.');
                }
                $catalog = ['videos' => []];
            }

            $result = $mutate($catalog);

            ftruncate($fp, 0);
            fseek($fp, 0);
            fwrite($fp, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
