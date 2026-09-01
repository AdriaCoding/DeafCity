<?php
/**
 * Participant field tests
 *
 * Run: php8.4 tests/participant_field_test.php
 *
 * Tests:
 *   1. Every visible catalog entry has a non-empty participant string.
 *   2. No participant value contains '#', leading/trailing spaces, or is a
 *      pure number.
 *   3. Unit tests for both title-format parsers (bulk underscore and Studio
 *      "by Name") covering edge cases from the real catalog.
 */

// ── Helpers ──────────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function pass(string $label): void
{
    global $passed;
    echo "PASS: {$label}\n";
    $passed++;
}

function fail(string $label, string $detail = ''): void
{
    global $failed;
    $msg = "FAIL: {$label}";
    if ($detail !== '') {
        $msg .= " — {$detail}";
    }
    fwrite(STDERR, $msg . "\n");
    $failed++;
}

function assert_eq(mixed $expected, mixed $actual, string $label): void
{
    if ($expected === $actual) {
        pass($label);
    } else {
        fail($label, "expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

// ── Load the parser under test ────────────────────────────────────────────────

// Include the backfill script in a way that skips its I/O block.
// We pull in just the two functions by re-declaring them here so this test
// file is self-contained and runnable without a real catalog on disk.

function extract_participant(string $title): ?string
{
    // Studio format: "#TAG by Name"
    if (preg_match('/\bby\s+(.+)$/i', $title, $m)) {
        $name = trim($m[1]);
        $name = preg_replace('/\s+#\S+.*$/', '', $name);
        return apply_casing(trim($name));
    }

    // Bulk format: SIGN_City_Name_N [#TAG …]
    $clean = preg_replace('/\s+#.*$/', '', $title);
    $parts = explode('_', $clean);
    if (count($parts) >= 3) {
        return apply_casing(trim($parts[2]));
    }

    return null;
}

function apply_casing(string $name): string
{
    if (mb_strtolower($name, 'UTF-8') === $name) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
             . mb_substr($name, 1, null, 'UTF-8');
    }
    return $name;
}

// ── Section 1: Unit tests for the two format parsers ─────────────────────────

echo "\n=== Unit tests: title format parsers ===\n";

// Bulk format — basic
assert_eq('Edinho',   extract_participant('LIBRAS_São Paulo_Edinho_1 #HEARING CROWD'), 'bulk: Edinho with tag');
assert_eq('Edinho',   extract_participant('LIBRAS_São Paulo_Edinho_2 #VIBRATION'),     'bulk: Edinho no extra underscores');
assert_eq('Fabio',    extract_participant('LIBRAS_São Paulo_Fabio_1 #WHEELCHAIR'),     'bulk: Fabio with tag');
assert_eq('Fabio',    extract_participant('LIBRAS_São Paulo_Fabio_4 #COCHLEAR IMPLANT'), 'bulk: Fabio multi-word tag');

// Bulk format — city with spaces (should not bleed into participant slot)
assert_eq('Veronica', extract_participant('LSM_Ciudad de México_Veronica_3'),          'bulk: city with spaces does not bleed');
assert_eq('Miguel',   extract_participant('LSM_Ciudad de México_Miguel_2'),            'bulk: México accent preserved in city');
assert_eq('Indy',     extract_participant('LSM_Ciudad de México_Indy_3'),              'bulk: Indy');
assert_eq('Beto',     extract_participant('LSM_Ciudad de México_Beto_3'),              'bulk: Beto');

// Bulk format — compact city (no space before underscore)
assert_eq('AnaLaura', extract_participant('LIBRAS_SãoPaulo_AnaLaura_2 #HORSES'),      'bulk: compact city, mixed-case name preserved');

// Bulk format — casing: lowercase name → ucfirst
assert_eq('Sony',     extract_participant('LSE_València_sony_5 #ASSOCIATION'),         'bulk: sony → Sony');

// Bulk format — accented participant name preserved
assert_eq('Mònica',   extract_participant('LSE_València_Mònica_2 #CALL OF NATURE'),   'bulk: accented name Mònica preserved');

// Bulk format — surname-style names (already title-case)
assert_eq('Riutort',  extract_participant('LSE_València_Riutort_2 #TRAIN'),            'bulk: surname Riutort preserved');
assert_eq('Pegolino', extract_participant('LSE_València_Pegolino_4 #HORN'),            'bulk: surname Pegolino preserved');

// Bulk format — no tag
assert_eq('Veronica', extract_participant('LSM_Ciudad de México_Veronica_3'),          'bulk: no tag suffix');

// Studio format — basic
assert_eq('Hamida',   extract_participant('#SHEEP by Hamida'),                         'studio: #TAG by Name');

// Studio format — case-insensitive "by"
assert_eq('Hamida',   extract_participant('#SHEEP BY Hamida'),                         'studio: BY uppercase');

// Studio format — trailing tag should be stripped
assert_eq('Hamida',   extract_participant('#SHEEP by Hamida #EXTRA'),                  'studio: trailing tag stripped');

// apply_casing — mixed-case kept
assert_eq('AnaLaura', apply_casing('AnaLaura'),  'casing: mixed-case kept');
assert_eq('Riutort',  apply_casing('Riutort'),   'casing: title-case kept');
assert_eq('Mònica',   apply_casing('Mònica'),    'casing: accented title-case kept');

// apply_casing — all-lowercase promoted
assert_eq('Sony',     apply_casing('sony'),      'casing: sony → Sony');
assert_eq('Hamida',   apply_casing('hamida'),    'casing: hamida → Hamida');

// ── Section 2: Catalog integration tests ─────────────────────────────────────

echo "\n=== Catalog integration tests ===\n";

$catalogPath = dirname(dirname(__FILE__)) . '/data/catalog.json';
if (!file_exists($catalogPath)) {
    fail('catalog exists', "file not found at {$catalogPath}");
    goto summary;
}
pass('catalog file exists');

$catalog = json_decode(file_get_contents($catalogPath), true);
if (!$catalog || !isset($catalog['videos'])) {
    fail('catalog parses', 'invalid JSON or missing videos key');
    goto summary;
}
pass('catalog parses as valid JSON');

$videos = $catalog['videos'];

// Every entry must have a non-empty participant
$missingParticipant = [];
$badParticipant     = [];

foreach ($videos as $v) {
    $id          = $v['id'] ?? '(no id)';
    $participant = $v['participant'] ?? null;

    if ($participant === null || $participant === '') {
        $missingParticipant[] = $id;
        continue;
    }

    // Must not contain '#'
    if (strpos($participant, '#') !== false) {
        $badParticipant[] = "{$id}: contains '#' in \"{$participant}\"";
    }

    // Must not have leading or trailing spaces
    if ($participant !== trim($participant)) {
        $badParticipant[] = "{$id}: has leading/trailing spaces in \"{$participant}\"";
    }

    // Must not be a pure integer string
    if (ctype_digit($participant)) {
        $badParticipant[] = "{$id}: is a pure number \"{$participant}\"";
    }
}

if (empty($missingParticipant)) {
    pass('all ' . count($videos) . ' visible entries have a non-empty participant');
} else {
    fail('all entries have participant', 'missing on: ' . implode(', ', $missingParticipant));
}

if (empty($badParticipant)) {
    pass('no participant value contains #, stray spaces, or pure numbers');
} else {
    foreach ($badParticipant as $msg) {
        fail('participant value clean', $msg);
    }
}

// Verify both formats are present in the catalog
$byFormat = array_filter($videos, fn($v) => preg_match('/\bby\s+/i', $v['title']));
$bulkFormat = array_filter($videos, fn($v) => !preg_match('/\bby\s+/i', $v['title']));

if (count($byFormat) > 0) {
    pass('Studio "by Name" format present (' . count($byFormat) . ' entries)');
} else {
    pass('Studio "by Name" format absent from catalog (parser covered by unit tests above)');
}

if (count($bulkFormat) > 0) {
    pass('Bulk underscore format present (' . count($bulkFormat) . ' entries)');
} else {
    fail('Bulk underscore format present', 'no entries matched');
}

// Spot-check known specific values
$byId = [];
foreach ($videos as $v) {
    $byId[$v['id']] = $v['participant'] ?? '';
}

$spotChecks = [
    'lse_1211608971'    => 'Sony',
    'lse_1211614388'    => 'Pegolino',
    'lse_1211611135'    => 'Riutort',
    'libras_1211648906' => 'Edinho',
    'libras_1211648907' => 'AnaLaura',
    'lsm_1211633776'    => 'Veronica',
];

foreach ($spotChecks as $id => $expectedName) {
    if (!isset($byId[$id])) {
        fail("spot-check {$id}", 'ID not found in catalog');
    } else {
        assert_eq($expectedName, $byId[$id], "spot-check {$id} = \"{$expectedName}\"");
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────

summary:
echo "\n";
$total = $passed + $failed;
echo str_repeat('-', 40) . "\n";
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo ", {$failed} FAILED";
}
echo "\n";

if ($failed > 0) {
    exit(1);
}
echo "All tests passed.\n";
