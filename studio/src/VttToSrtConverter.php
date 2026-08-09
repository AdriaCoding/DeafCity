<?php

namespace Studio;

class VttToSrtConverter
{
    public function __construct(
        private readonly CaptionReader $reader = new CaptionReader(),
        private readonly SrtParser $srtParser = new SrtParser(),
    ) {
    }

    /**
     * Accepts WebVTT or SubRip and always emits SubRip. Feeding it a file that
     * is already SRT is a lossless no-op, which is what keeps the download and
     * ZIP paths working while data/ is mid-migration.
     */
    public function convert(string $captionFilePath): string
    {
        $parsed = $this->reader->read($captionFilePath);

        return $this->writeCues($parsed['cues']);
    }

    /**
     * @param list<array{start: float, end: float, text: string, opaque?: string, id?: string}> $cues
     */
    public function writeCues(array $cues): string
    {
        return $this->srtParser->write($cues);
    }
}
