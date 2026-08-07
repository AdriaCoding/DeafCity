<?php

namespace Studio;

/**
 * Serialises a parsed caption structure back to the format it came from.
 *
 * The counterpart to CaptionReader. Editors read a file, replace its cues, and
 * write it back; without format preservation that round trip would rewrite an
 * .srt file as WebVTT (or the reverse) the moment the two sides of the
 * migration were a milestone apart.
 *
 * Callers that need a specific output format should use SrtParser::write() or
 * VttParser::write() directly — this class is for round trips, where the answer
 * is "whatever it already was".
 */
class CaptionWriter
{
    public function __construct(
        private readonly VttParser $vttParser = new VttParser(),
        private readonly SrtParser $srtParser = new SrtParser(),
    ) {
    }

    /**
     * @param array{cues: array<int, array<string, mixed>>, header?: string, opaque_blocks?: array<int, string>, format?: string} $parsed
     */
    public function write(array $parsed): string
    {
        if (($parsed['format'] ?? 'vtt') === 'srt') {
            return $this->srtParser->write(array_values($parsed['cues']));
        }

        return $this->vttParser->write($parsed);
    }
}
