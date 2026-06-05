<?php

namespace Studio;

/**
 * Re-merges Whisper word-level timestamps into readable single-line WebVTT
 * cues. Pure and deterministic: word stream in, cue array out, no I/O.
 *
 * Mirrors scripts/cue_chunker.py exactly; both are pinned to the shared golden
 * fixture (tests/fixtures/cue_chunker_cases.json) so the Groq (PHP) and local
 * faster-whisper (Python) paths produce identical cue shapes.
 *
 * Character counts are Unicode code points (mb_strlen), so an accented glyph
 * like "à" counts as one — matching Python's len().
 */
class CueChunker
{
    private int $maxChars;
    private float $pauseThreshold;
    private float $maxDuration;

    /** @param array<string, mixed> $params */
    public function __construct(array $params = [])
    {
        $this->maxChars = (int) ($params['max_chars'] ?? 50);
        $this->pauseThreshold = (float) ($params['pause_threshold'] ?? 0.4);
        $this->maxDuration = (float) ($params['max_duration'] ?? 6.0);
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $words
     * @return list<array{start: float, end: float, text: string}>
     */
    public function chunk(array $words): array
    {
        if ($words === []) {
            return [];
        }

        return $this->splitPhase($words);
    }

    /**
     * Phase 1 — split the word stream into cues, closing the current cue when a
     * hard splitting constraint (so far: max chars) would be exceeded.
     *
     * @param list<array{start: float, end: float, text: string}> $words
     * @return list<array{start: float, end: float, text: string}>
     */
    private function splitPhase(array $words): array
    {
        $cues = [];
        /** @var list<array{start: float, end: float, text: string}> $cur */
        $cur = [];

        foreach ($words as $w) {
            // A pause is a forced break: close as-is, no retreat.
            if ($cur !== [] && $this->pauseBefore($cur, $w)) {
                $cues[] = $this->makeCue($cur);
                $cur = [$w];
                continue;
            }
            // Capacity exceeded: close at the latest good break point within the
            // accumulated words (strong punctuation), carrying the rest forward.
            if ($cur !== [] && ($this->wouldExceedChars($cur, $w) || $this->wouldExceedDuration($cur, $w))) {
                $breakIdx = $this->lastBreakIndex($cur);
                $cues[] = $this->makeCue(array_slice($cur, 0, $breakIdx + 1));
                $cur = array_slice($cur, $breakIdx + 1);
                $cur[] = $w;
                continue;
            }
            $cur[] = $w;
        }

        if ($cur !== []) {
            $cues[] = $this->makeCue($cur);
        }

        return $cues;
    }

    /**
     * Latest word ending in strong punctuation (. ! ?). Falls back to the last
     * index — a hard cut after the whole accumulation — when none is found.
     *
     * @param list<array{start: float, end: float, text: string}> $cur
     */
    private function lastBreakIndex(array $cur): int
    {
        for ($i = count($cur) - 1; $i >= 0; $i--) {
            $last = mb_substr(rtrim((string) $cur[$i]['text']), -1);
            if ($last === '.' || $last === '!' || $last === '?') {
                return $i;
            }
        }
        return count($cur) - 1;
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $cur
     * @param array{start: float, end: float, text: string} $next
     */
    private function pauseBefore(array $cur, array $next): bool
    {
        $lastEnd = (float) $cur[count($cur) - 1]['end'];
        return ((float) $next['start'] - $lastEnd) > $this->pauseThreshold;
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $cur
     * @param array{start: float, end: float, text: string} $next
     */
    private function wouldExceedChars(array $cur, array $next): bool
    {
        return mb_strlen($this->joinText([...$cur, $next])) > $this->maxChars;
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $cur
     * @param array{start: float, end: float, text: string} $next
     */
    private function wouldExceedDuration(array $cur, array $next): bool
    {
        return ((float) $next['end'] - (float) $cur[0]['start']) > $this->maxDuration;
    }

    /**
     * @param list<array{start: float, end: float, text: string}> $cur
     * @return array{start: float, end: float, text: string}
     */
    private function makeCue(array $cur): array
    {
        return [
            'start' => (float) $cur[0]['start'],
            'end' => (float) $cur[count($cur) - 1]['end'],
            'text' => $this->joinText($cur),
        ];
    }

    /** @param list<array{start: float, end: float, text: string}> $words */
    private function joinText(array $words): string
    {
        return implode(' ', array_map(static fn(array $w) => trim((string) $w['text']), $words));
    }
}
