<?php

namespace Studio;

class VttToSrtConverter
{
    public function __construct(
        private readonly VttParser $vttParser = new VttParser(),
    ) {
    }

    public function convert(string $vttFilePath): string
    {
        $parsed = $this->vttParser->parse($vttFilePath);

        return $this->writeCues($parsed['cues']);
    }

    /**
     * @param list<array{start: float, end: float, text: string, opaque: string, id: string}> $cues
     */
    public function writeCues(array $cues): string
    {
        $blocks = [];
        foreach ($cues as $i => $cue) {
            $timing = $this->formatTime($cue['start']) . ' --> ' . $this->formatTime($cue['end']);
            $blocks[] = ($i + 1) . "\n" . $timing . "\n" . $cue['text'];
        }

        return implode("\n\n", $blocks) . "\n";
    }

    private function formatTime(float $seconds): string
    {
        $h  = (int) ($seconds / 3600);
        $m  = (int) (fmod($seconds, 3600) / 60);
        $s  = (int) fmod($seconds, 60);
        $ms = (int) round(fmod($seconds, 1) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
    }
}
