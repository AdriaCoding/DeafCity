<?php

namespace Studio;

class VttToSrtConverter
{
    public function __construct(
        private readonly VttParser $vttParser = new VttParser(),
        private readonly SrtParser $srtParser = new SrtParser(),
    ) {
    }

    public function convert(string $vttFilePath): string
    {
        $parsed = $this->vttParser->parse($vttFilePath);

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
