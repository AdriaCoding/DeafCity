<?php

namespace Studio;

class CatalogSheetSync
{
    public function __construct(
        private readonly GoogleSheetsClient $sheets,
        private readonly VimeoClient $vimeo,
        private readonly CatalogEditor $catalog,
        private readonly SheetCatalogParser $parser = new SheetCatalogParser(),
    ) {}

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

        // Count unknown-city skips already reflected as warnings with no row.
        // Empty Vimeo IDs are silent skips in the parser.

        $removed = 0;
        if ($replace && !$dryRun) {
            $keepIds = array_map(static fn(SheetVideoRow $r): string => $r->vimeoId, $sheetRows);
            // Duplicate IDs are in the Sheet even though we skip upserting them.
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

        foreach ($sheetRows as $row) {
            try {
                $title = $this->vimeo->getVideo($row->vimeoId);
            } catch (VimeoNotFoundException $e) {
                $skipped++;
                $warnings[] = "Vimeo ID {$row->vimeoId} not found ({$row->sheetIdentity})";
                continue;
            } catch (\Throwable $e) {
                $skipped++;
                $warnings[] = "Vimeo error for {$row->vimeoId}: " . $e->getMessage();
                continue;
            }

            if ($row->unknownTypology) {
                $warnings[] = "Unknown typology \"{$row->rawTypology}\" for Vimeo ID {$row->vimeoId} — typology cleared";
            }

            $existing = $this->catalog->findVideoByVimeoId($row->vimeoId);

            if ($dryRun) {
                if ($existing === null) {
                    $added++;
                } else {
                    $updated++;
                }
                continue;
            }

            $thumbnailUrl = null;
            $embedUrl = null;
            $needsThumb = $existing === null
                || !isset($existing['thumbnail_url'])
                || $existing['thumbnail_url'] === '';
            $needsEmbed = $existing === null
                || !isset($existing['embed_url'])
                || $existing['embed_url'] === '';

            if ($needsThumb) {
                try {
                    $thumbnailUrl = $this->vimeo->getThumbnailUrl($row->vimeoId);
                } catch (\Throwable) {
                    $thumbnailUrl = null;
                }
            }
            if ($needsEmbed) {
                try {
                    $embedUrl = $this->vimeo->getPlayerEmbedUrl($row->vimeoId);
                } catch (\Throwable) {
                    $embedUrl = null;
                }
            }

            $action = $this->catalog->upsertFromSheet(
                $row->vimeoId,
                $title,
                $row->signLanguage,
                $row->editionId,
                $row->tags,
                $row->typologyId,
                $row->participant !== '' ? $row->participant : null,
                $thumbnailUrl,
                $embedUrl,
            );
            if ($action === 'added') {
                $added++;
            } else {
                $updated++;
            }
        }

        return new CatalogSheetSyncResult(
            added: $added,
            updated: $updated,
            removed: $removed,
            skipped: $skipped,
            warnings: $warnings,
        );
    }
}
