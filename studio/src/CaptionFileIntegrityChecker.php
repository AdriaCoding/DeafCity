<?php

namespace Studio;

class CaptionFileIntegrityChecker
{
    /**
     * Validates a cue array and returns a list of structured errors.
     * Each entry: ['cueIndex' => int, 'message' => string]
     * Returns an empty array when the cue list is clean.
     */
    public function check(array $cues): array
    {
        $errors = [];

        foreach ($cues as $i => $cue) {
            $n = $i + 1;
            if ($cue['start'] >= $cue['end']) {
                $errors[] = ['cueIndex' => $i, 'message' => "Subtítol $n: l'hora d'inici ha de ser anterior a l'hora de fi."];
            }
            if ($cue['start'] < 0.0) {
                $errors[] = ['cueIndex' => $i, 'message' => "Subtítol $n: l'hora d'inici no pot ser negativa."];
            }
        }

        $count = count($cues);
        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($cues[$i]['end'] > $cues[$j]['start']) {
                    $ni = $i + 1;
                    $nj = $j + 1;
                    $errors[] = ['cueIndex' => $i, 'message' => "Els subtítols $ni i $nj se superposen."];
                }
            }
        }

        return $errors;
    }
}
