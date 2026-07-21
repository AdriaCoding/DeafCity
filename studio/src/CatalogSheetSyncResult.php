<?php

namespace Studio;

final class CatalogSheetSyncResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly int $added = 0,
        public readonly int $updated = 0,
        public readonly int $removed = 0,
        public readonly int $skipped = 0,
        public readonly array $warnings = [],
        public readonly ?string $error = null,
    ) {}
}
