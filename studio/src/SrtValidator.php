<?php

namespace Studio;

/**
 * SubRip counterpart to WebVttValidator.
 *
 * Stricter than its VTT sibling on purpose: WebVttValidator only checks the
 * header line, because a malformed VTT cue degrades to an opaque block rather
 * than corrupting the document. SubRip has no header to check and no opaque-block
 * escape hatch, so validation here is a full structural parse.
 */
class SrtValidator
{
    public function __construct(
        private readonly SrtParser $srtParser = new SrtParser(),
    ) {
    }

    /** @throws \InvalidArgumentException when the file is not structurally valid SubRip */
    public function validate(string $filePath, string $originalName): void
    {
        if (!str_ends_with(strtolower($originalName), '.srt')) {
            throw new \InvalidArgumentException('El fitxer de subtítols ha de ser un fitxer SubRip (.srt).');
        }

        $this->srtParser->parse($filePath);
    }
}
