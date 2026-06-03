<?php

namespace Studio;

class SrtToVttConverter
{
    public function __construct(
        private readonly SrtParser $srtParser = new SrtParser(),
        private readonly VttParser $vttParser = new VttParser(),
    ) {
    }

    public function convert(string $srtFilePath): string
    {
        $parsed = $this->srtParser->parse($srtFilePath);

        return $this->vttParser->write([
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'cues' => $parsed['cues'],
        ]);
    }
}
