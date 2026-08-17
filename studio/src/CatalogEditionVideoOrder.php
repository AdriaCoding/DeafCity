<?php

namespace Studio;

class CatalogEditionVideoOrder
{
    /** @param list<array<string, mixed>> $videos
     *  @return list<array<string, mixed>>
     */
    public static function sortVideos(array $videos): array
    {
        usort($videos, function (array $a, array $b): int {
            $participantCmp = strcasecmp(
                trim((string) ($a['participant'] ?? '')),
                trim((string) ($b['participant'] ?? '')),
            );
            if ($participantCmp !== 0) {
                return $participantCmp;
            }

            return (self::videoNumber($a) ?? PHP_INT_MAX) <=> (self::videoNumber($b) ?? PHP_INT_MAX);
        });

        return $videos;
    }

    /** @param array<string, mixed> $video */
    public static function videoNumber(array $video): ?int
    {
        if (preg_match('/_(\d+)(?:_[A-Za-z0-9]+)?$/', (string) ($video['title'] ?? ''), $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
