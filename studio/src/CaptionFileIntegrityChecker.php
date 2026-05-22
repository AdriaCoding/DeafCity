<?php

namespace Studio;

class CaptionFileIntegrityChecker
{
    /**
     * Validates a cue array and returns a flat list of error messages.
     * Returns an empty array when the cue list is clean.
     */
    public function check(array $cues): array
    {
        $errors = [];

        foreach ($cues as $i => $cue) {
            $n = $i + 1;
            if ($cue['start'] >= $cue['end']) {
                $errors[] = "Cue $n: start time must be less than end time.";
            }
            if ($cue['start'] < 0.0) {
                $errors[] = "Cue $n: start time must not be negative.";
            }
        }

        // Check overlaps between all pairs
        $count = count($cues);
        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $cues[$i];
                $b = $cues[$j];
                if ($a['end'] > $b['start']) {
                    $ni = $i + 1;
                    $nj = $j + 1;
                    $errors[] = "Cues $ni and $nj overlap.";
                }
            }
        }

        return $errors;
    }
}
