<?php

namespace Studio;

/**
 * Strip an NLE programme-clock hour from cue times so they match video t=0.
 *
 * DaVinci / Premiere timelines often start at 01:00:00:00. Vimeo playback
 * (and this project's Videos) start at 0. Only whole hours are removed, and
 * only when the whole Caption file fits inside a window shorter than one
 * hour — a file that genuinely lasts an hour or more is left untouched.
 */
class CaptionTimecodeAligner
{
    private const HOUR_SECONDS = 3600.0;

    /**
     * @param list<array{start: float, end: float, text?: string, opaque?: string, id?: string}> $cues
     */
    public function offsetSeconds(array $cues): float
    {
        if ($cues === []) {
            return 0.0;
        }

        $minStart = $cues[0]['start'];
        $maxEnd = $cues[0]['end'];
        foreach ($cues as $cue) {
            if ($cue['start'] < $minStart) {
                $minStart = $cue['start'];
            }
            if ($cue['end'] > $maxEnd) {
                $maxEnd = $cue['end'];
            }
        }

        if ($minStart < self::HOUR_SECONDS) {
            return 0.0;
        }
        if (($maxEnd - $minStart) >= self::HOUR_SECONDS) {
            return 0.0;
        }

        return floor($minStart / self::HOUR_SECONDS) * self::HOUR_SECONDS;
    }

    /**
     * @param list<array{start: float, end: float, text?: string, opaque?: string, id?: string}> $cues
     * @return list<array{start: float, end: float, text?: string, opaque?: string, id?: string}>
     */
    public function align(array $cues): array
    {
        $offset = $this->offsetSeconds($cues);
        if ($offset <= 0.0) {
            return $cues;
        }

        $aligned = [];
        foreach ($cues as $cue) {
            $cue['start'] = $cue['start'] - $offset;
            $cue['end'] = $cue['end'] - $offset;
            $aligned[] = $cue;
        }

        return $aligned;
    }
}
