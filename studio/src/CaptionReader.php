<?php

namespace Studio;

/**
 * Reads a caption file without caring whether it is WebVTT or SubRip.
 *
 * This exists so the VTT→SRT migration can move data and code in separate
 * deploys: while data/captions/ and the job drafts hold a mix of both formats,
 * every reader still sees the same cue model. Without it, any milestone that
 * flips a file's format would break whichever reader still hardcoded a parser.
 *
 * Returns the WebVTT-shaped structure (cues + header + opaque_blocks) because
 * that is the superset — SubRip has no header or opaque blocks, so those come
 * back as the defaults a VTT writer would need.
 *
 * Detection is content-level and deliberately conservative: an explicit WEBVTT
 * header wins, an unmistakable SubRip opening wins next, and anything else falls
 * through to the lenient VTT parser, which is what these call sites did before.
 * IntakeSourceDetector remains the upload-level detector — it also has to
 * recognise audio and works from the uploaded path rather than from bytes.
 */
class CaptionReader
{
    public function __construct(
        private readonly VttParser $vttParser = new VttParser(),
        private readonly SrtParser $srtParser = new SrtParser(),
    ) {
    }

    /**
     * @return array{cues: array<int, array<string, mixed>>, header: string, opaque_blocks: array<int, string>}
     */
    public function read(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: $filePath");
        }

        return $this->readString($content);
    }

    /**
     * The returned `format` is what CaptionWriter needs to round-trip a file
     * back to disk in the format it arrived in.
     *
     * @return array{cues: array<int, array<string, mixed>>, header: string, opaque_blocks: array<int, string>, format: 'vtt'|'srt'}
     */
    public function readString(string $content): array
    {
        if (!$this->looksLikeSubRip($content)) {
            return $this->vttParser->parseString($content) + ['format' => 'vtt'];
        }

        return [
            'cues' => $this->srtParser->parseString($content)['cues'],
            'header' => 'WEBVTT',
            'opaque_blocks' => [],
            'format' => 'srt',
        ];
    }

    private function looksLikeSubRip(string $content): bool
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $content = ltrim($content);

        if (preg_match('/^WEBVTT([ \t\r\n]|$)/', $content)) {
            return false;
        }

        return (bool) preg_match('/^\d+\s*\r?\n\d{2}:\d{2}:\d{2},\d{3}\s+-->/', $content);
    }
}
