<?php

namespace Studio;

class CatalogSheetSync
{
    private const MAX_TRANSIENT_ATTEMPTS = 3;
    private const MAX_RATE_LIMIT_WAITS = 5;
    private const TRANSIENT_BASE_SLEEP_SECONDS = 1;

    /** @var callable(int):void */
    private $sleeper;

    /**
     * @param callable(int):void|null $sleeper Injected sleep for tests; defaults to PHP sleep().
     */
    public function __construct(
        private readonly GoogleSheetsClient $sheets,
        private readonly VimeoClient $vimeo,
        private readonly CatalogEditor $catalog,
        private readonly SheetCatalogParser $parser = new SheetCatalogParser(),
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            if ($seconds > 0) {
                sleep($seconds);
            }
        };
    }

    /**
     * @param array{replace?: bool, dryRun?: bool} $options
     */
    public function run(array $options = []): CatalogSheetSyncResult
    {
        $replace = (bool) ($options['replace'] ?? false);
        $dryRun = (bool) ($options['dryRun'] ?? false);

        try {
            $rawRows = $this->sheets->fetchRows();
        } catch (\Throwable $e) {
            return new CatalogSheetSyncResult(
                error: $e->getMessage() !== '' ? $e->getMessage() : 'Google Sheets fetch failed.',
            );
        }

        $parsed = $this->parser->parse($rawRows);
        $warnings = $parsed['warnings'];
        $sheetRows = $parsed['rows'];
        $skipped = count($parsed['duplicateVimeoIds']);

        try {
            $this->vimeo->assertAuthenticated();
        } catch (VimeoUnauthorizedException|VimeoForbiddenException $e) {
            return new CatalogSheetSyncResult(
                skipped: $skipped,
                warnings: $warnings,
                error: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return new CatalogSheetSyncResult(
                skipped: $skipped,
                warnings: $warnings,
                error: 'Vimeo auth probe failed: ' . $e->getMessage(),
            );
        }

        // A run that mutates the Catalog and then throws partway through
        // (as opposed to the Vimeo error classes above, which are already
        // caught and reported as a clean partial result) must not leave the
        // Catalog half-updated. Snapshot it now and, if anything below
        // throws, restore this exact snapshot before letting the exception
        // propagate.
        $snapshot = $dryRun ? null : $this->catalog->snapshotVideos();

        try {
            $removed = 0;
            if ($replace && !$dryRun) {
                $keepIds = array_map(static fn(SheetVideoRow $r): string => $r->vimeoId, $sheetRows);
                $keepIds = array_values(array_unique(array_merge($keepIds, $parsed['duplicateVimeoIds'])));
                $removed = $this->catalog->removeVideosNotIn($keepIds);
            } elseif ($replace && $dryRun) {
                $keepIds = array_fill_keys(
                    array_merge(
                        array_map(static fn(SheetVideoRow $r): string => $r->vimeoId, $sheetRows),
                        $parsed['duplicateVimeoIds'],
                    ),
                    true,
                );
                foreach ($this->catalog->getAllVideos() as $existing) {
                    $id = (string) ($existing['vimeo_id'] ?? '');
                    if ($id !== '' && !isset($keepIds[$id])) {
                        $removed++;
                    }
                }
            }

            $added = 0;
            $updated = 0;
            $rateLimitWaits = 0;

            foreach ($sheetRows as $row) {
                $existing = $this->catalog->findVideoByVimeoId($row->vimeoId);
                $needsThumb = $existing === null
                    || !isset($existing['thumbnail_url'])
                    || $existing['thumbnail_url'] === '';
                $needsEmbed = $existing === null
                    || !isset($existing['embed_url'])
                    || $existing['embed_url'] === '';

                $fetch = $this->fetchWithRetries(
                    $row,
                    $needsThumb && !$dryRun,
                    $needsEmbed && !$dryRun,
                    $rateLimitWaits,
                    $warnings,
                );
                if ($fetch['abort'] !== null) {
                    return new CatalogSheetSyncResult(
                        added: $added,
                        updated: $updated,
                        removed: $removed,
                        skipped: $skipped,
                        warnings: $warnings,
                        error: $fetch['abort'],
                    );
                }
                if ($fetch['skip']) {
                    $skipped++;
                    continue;
                }

                $meta = $fetch['meta'];
                assert($meta !== null);

                if ($row->unknownTypology) {
                    $warnings[] = "Unknown typology \"{$row->rawTypology}\" for Vimeo ID {$row->vimeoId} — typology cleared";
                }

                if ($dryRun) {
                    if ($existing === null) {
                        $added++;
                    } else {
                        $updated++;
                    }
                    continue;
                }

                $action = $this->catalog->upsertFromSheet(
                    $row->vimeoId,
                    $meta['title'],
                    $row->signLanguage,
                    $row->editionId,
                    $row->tags,
                    $row->typologyId,
                    $row->participant !== '' ? $row->participant : null,
                    $meta['thumbnail_url'],
                    $meta['embed_url'],
                );
                if ($action === 'added') {
                    $added++;
                } else {
                    $updated++;
                }
            }
        } catch (\Throwable $e) {
            if ($snapshot !== null) {
                $this->catalog->restoreVideos($snapshot);
            }
            throw $e;
        }

        return new CatalogSheetSyncResult(
            added: $added,
            updated: $updated,
            removed: $removed,
            skipped: $skipped,
            warnings: $warnings,
        );
    }

    /**
     * @param list<string> $warnings
     * @return array{
     *   meta: ?array{title: string, thumbnail_url: ?string, embed_url: ?string},
     *   skip: bool,
     *   abort: ?string
     * }
     */
    private function fetchWithRetries(
        SheetVideoRow $row,
        bool $needThumbnail,
        bool $needEmbed,
        int &$rateLimitWaits,
        array &$warnings,
    ): array {
        $transientAttempt = 0;

        while (true) {
            try {
                $meta = $this->vimeo->fetchVideoForCatalogSync($row->vimeoId, $needThumbnail, $needEmbed);
                return ['meta' => $meta, 'skip' => false, 'abort' => null];
            } catch (VimeoNotFoundException $e) {
                $warnings[] = "Vimeo ID {$row->vimeoId} not found ({$row->sheetIdentity}): " . $e->getMessage();
                return ['meta' => null, 'skip' => true, 'abort' => null];
            } catch (VimeoUnauthorizedException|VimeoForbiddenException $e) {
                return ['meta' => null, 'skip' => false, 'abort' => $e->getMessage()];
            } catch (VimeoRateLimitedException $e) {
                $rateLimitWaits++;
                if ($rateLimitWaits > self::MAX_RATE_LIMIT_WAITS) {
                    return [
                        'meta' => null,
                        'skip' => false,
                        'abort' => 'Vimeo rate limit: exceeded max waits (' . self::MAX_RATE_LIMIT_WAITS . '). ' . $e->getMessage(),
                    ];
                }
                $wait = $e->secondsUntilReset() + 1; // small fixed jitter
                ($this->sleeper)($wait);
                continue;
            } catch (VimeoTransientException $e) {
                $transientAttempt++;
                if ($transientAttempt >= self::MAX_TRANSIENT_ATTEMPTS) {
                    $warnings[] = "Vimeo ID {$row->vimeoId} skipped after transient failures ({$row->sheetIdentity}): " . $e->getMessage();
                    return ['meta' => null, 'skip' => true, 'abort' => null];
                }
                ($this->sleeper)(self::TRANSIENT_BASE_SLEEP_SECONDS * (2 ** ($transientAttempt - 1)));
                continue;
            } catch (VimeoRequestException $e) {
                $warnings[] = "Vimeo ID {$row->vimeoId} skipped (request error) ({$row->sheetIdentity}): " . $e->getMessage();
                return ['meta' => null, 'skip' => true, 'abort' => null];
            } catch (\Throwable $e) {
                $warnings[] = "Vimeo ID {$row->vimeoId} skipped (unexpected error) ({$row->sheetIdentity}): " . $e->getMessage();
                return ['meta' => null, 'skip' => true, 'abort' => null];
            }
        }
    }
}
