<?php

namespace Studio;

class VttParser
{
    /**
     * Parse a .vtt file into a structured array:
     *   ['cues' => [...], 'header' => string, 'opaque_blocks' => [...]]
     *
     * Each cue: ['start' => float, 'end' => float, 'text' => string, 'opaque' => string]
     */
    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: $filePath");
        }

        return $this->parseString($content);
    }

    public function parseString(string $content): array
    {
        // Normalise line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        $blocks = preg_split('/\n\n+/', trim($content));

        $header = array_shift($blocks) ?? 'WEBVTT';
        $cues = [];
        $opaqueBlocks = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $cue = $this->tryParseCueBlock($block);
            if ($cue !== null) {
                $cues[] = $cue;
            } else {
                $opaqueBlocks[] = $block;
            }
        }

        return [
            'cues' => $cues,
            'header' => $header,
            'opaque_blocks' => $opaqueBlocks,
        ];
    }

    /**
     * Reconstruct a valid WebVTT string from a cue array (as returned by parse).
     */
    public function write(array $parsed): string
    {
        $header = $parsed['header'] ?? 'WEBVTT';
        $opaqueBlocks = $parsed['opaque_blocks'] ?? [];
        $cues = $parsed['cues'] ?? [];

        $parts = [$header];

        foreach ($opaqueBlocks as $block) {
            $parts[] = $block;
        }

        foreach ($cues as $cue) {
            $opaque = $cue['opaque'] !== '' ? ' ' . $cue['opaque'] : '';
            $timestamp = $this->formatTime($cue['start']) . ' --> ' . $this->formatTime($cue['end']) . $opaque;
            $parts[] = $timestamp . "\n" . $cue['text'];
        }

        return implode("\n\n", $parts) . "\n";
    }

    private function tryParseCueBlock(string $block): ?array
    {
        $lines = explode("\n", $block);

        // A cue block may start with an optional cue identifier (no '-->' in it),
        // followed by the timestamp line.
        $timingLine = null;
        $textLines = [];
        $foundTiming = false;

        foreach ($lines as $i => $line) {
            if (!$foundTiming && str_contains($line, '-->')) {
                $timingLine = $line;
                $foundTiming = true;
                $textLines = array_slice($lines, $i + 1);
                break;
            }
        }

        if ($timingLine === null) {
            return null;
        }

        // Parse "HH:MM:SS.mmm --> HH:MM:SS.mmm [opaque]"
        if (!preg_match(
            '/^(\d+:\d{2}:\d{2}\.\d+|\d{2}:\d{2}\.\d+)\s+-->\s+(\d+:\d{2}:\d{2}\.\d+|\d{2}:\d{2}\.\d+)(.*)?$/',
            $timingLine,
            $m
        )) {
            return null;
        }

        return [
            'start'  => $this->parseTime($m[1]),
            'end'    => $this->parseTime($m[2]),
            'text'   => implode("\n", $textLines),
            'opaque' => trim($m[3] ?? ''),
        ];
    }

    private function parseTime(string $ts): float
    {
        $parts = explode(':', $ts);
        if (count($parts) === 3) {
            [$h, $m, $s] = $parts;
            return (float)$h * 3600 + (float)$m * 60 + (float)$s;
        }
        [$m, $s] = $parts;
        return (float)$m * 60 + (float)$s;
    }

    private function formatTime(float $seconds): string
    {
        $h  = (int)($seconds / 3600);
        $m  = (int)(fmod($seconds, 3600) / 60);
        $s  = fmod($seconds, 60);
        return sprintf('%02d:%02d:%06.3f', $h, $m, $s);
    }
}
