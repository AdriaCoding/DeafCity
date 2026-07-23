<?php
/**
 * Participant nav sequence helpers (title parse + label format).
 *
 * Run: php preview/tests/participant_nav_sequence_test.php
 */

require dirname(dirname(__FILE__)) . '/lib/videos_catalog.php';

function pns_assert($condition, $label)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

pns_assert(vpc_participant_sequence_from_title('2026_ALGER_Hamida_1_4K') === '1', 'parse 4K title');
pns_assert(vpc_participant_sequence_from_title('2026_ROMA_Serena_3_HD') === '3', 'parse HD title');
pns_assert(vpc_participant_sequence_from_title('2026_BARCELONA_Carlota_01_HD') === '1', 'strip leading zeros');
pns_assert(vpc_participant_sequence_from_title('untitled') === '', 'missing sequence');
pns_assert(vpc_format_participant_nav_label('Hamida', '2') === 'Hamida 2', 'format with sequence');
pns_assert(vpc_format_participant_nav_label('Hamida', '') === 'Hamida', 'format bare name');

$catalog = vpc_load_videos_catalog(dirname(dirname(dirname(__FILE__))) . '/data/catalog.json');
pns_assert(is_array($catalog), 'catalog loads');
$playlist = vpc_vimeo_playlist_all_from_catalog($catalog);
$found = false;
foreach ($playlist as $entry) {
    if (!empty($entry['participant']) && $entry['participant'] === 'Hamida') {
        pns_assert(isset($entry['participant_sequence']) && $entry['participant_sequence'] !== '', 'Hamida playlist item has sequence');
        $found = true;
        break;
    }
}
pns_assert($found, 'found Hamida in playlist');

$participantPl = vpc_participant_playlist_from_catalog($catalog, 'Hamida');
pns_assert(count($participantPl) > 0, 'participant playlist non-empty');
pns_assert(
    isset($participantPl[0]['participant_sequence']) && $participantPl[0]['participant_sequence'] !== '',
    'first participant playlist item has sequence'
);
pns_assert(
    (string) $participantPl[0]['participant_sequence'] === '1',
    'participant playlist starts at sequence 1'
);
$prevSeq = 0;
foreach ($participantPl as $entry) {
    $raw = isset($entry['participant_sequence']) ? trim((string) $entry['participant_sequence']) : '';
    if ($raw === '' || !is_numeric($raw)) {
        continue;
    }
    $n = (int) $raw;
    pns_assert($n >= $prevSeq, 'participant playlist numeric sequences are non-decreasing');
    $prevSeq = $n;
}
$label = vpc_format_participant_nav_label(
    $participantPl[0]['participant'],
    $participantPl[0]['participant_sequence']
);
pns_assert(preg_match('/^Hamida \d+$/', $label) === 1, 'SSR-style label is Name N');

// Synthetic: numeric order + unnumbered last (independent of live catalog order)
$synthetic = array(
    array('video_id' => 'a', 'participant' => 'X', 'participant_sequence' => '10'),
    array('video_id' => 'b', 'participant' => 'X'),
    array('video_id' => 'c', 'participant' => 'X', 'participant_sequence' => '2'),
    array('video_id' => 'd', 'participant' => 'X', 'participant_sequence' => '1'),
);
$sorted = vpc_sort_playlist_by_participant_sequence($synthetic);
pns_assert($sorted[0]['video_id'] === 'd', 'synthetic sort: sequence 1 first');
pns_assert($sorted[1]['video_id'] === 'c', 'synthetic sort: sequence 2 second');
pns_assert($sorted[2]['video_id'] === 'a', 'synthetic sort: sequence 10 third');
pns_assert($sorted[3]['video_id'] === 'b', 'synthetic sort: unnumbered last');

echo "\nAll participant_nav_sequence tests passed.\n";
