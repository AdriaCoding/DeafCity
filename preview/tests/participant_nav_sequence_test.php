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
$label = vpc_format_participant_nav_label(
    $participantPl[0]['participant'],
    $participantPl[0]['participant_sequence']
);
pns_assert(preg_match('/^Hamida \d+$/', $label) === 1, 'SSR-style label is Name N');

echo "\nAll participant_nav_sequence tests passed.\n";
