<?php

namespace Studio;

final class SheetVideoRow
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly string $vimeoId,
        public readonly string $editionId,
        public readonly string $signLanguage,
        public readonly string $participant,
        public readonly array $tags,
        public readonly ?string $typologyId,
        public readonly string $sheetIdentity,
        public readonly bool $unknownTypology = false,
        public readonly ?string $rawTypology = null,
    ) {}
}
