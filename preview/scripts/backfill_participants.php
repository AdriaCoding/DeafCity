<?php
/**
 * Backfill script — add "participant" field to every catalog entry.
 *
 * Run once from the repo root:
 *   php preview/scripts/backfill_participants.php [--write]
 *
 * Without --write, prints a preview report only (dry run).
 * With --write, updates data/catalog.json in place.
 *
 * Two title formats are handled:
 *
 *   1. Bulk format:   SIGN_City_Name_N [#TAG …]
 *      Participant is the 3rd underscore-separated field (index 2).
 *      Example: "LIBRAS_São Paulo_Edinho_1 #HEARING CROWD" → "Edinho"
 *      Note: city names may contain spaces (e.g. "Ciudad de México") —
 *      underscores are the only field delimiter, so split on "_" is safe.
 *
 *   2. Studio format: #TAG by Name
 *      Participant is the text after the literal " by " (case-insensitive).
 *      Example: "#SHEEP by Hamida" → "Hamida"
 *
 * Casing rule: if the extracted name is entirely lowercase, ucfirst() is
 * applied (e.g. "sony" → "Sony").  Mixed-case names such as "AnaLaura" and
 * accented names like "Mònica" are kept as-is; surname-style names like
 * "Riutort" / "Pegolino" are already title-case and remain unchanged.
 *
 * NEW VIDEOS: when publishing a new video, set the "participant" field
 * directly in catalog.json at publication time — do not rely on title
 * parsing.  The two rules above are the reference for the title contracts.
 */

$catalogPath = __DIR__ . '/../../data/catalog.json';

if (!file_exists($catalogPath)) {
    fwrite(STDERR, "ERROR: catalog not found at {$catalogPath}\n");
    exit(1);
}

$catalog = json_decode(file_get_contents($catalogPath), true);
if (!$catalog || !isset($catalog['videos'])) {
    fwrite(STDERR, "ERROR: failed to parse catalog JSON\n");
    exit(1);
}

/**
 * Extract the participant name from a video title.
 * Returns the name string, or null if neither format matched.
 */
function extract_participant(string $title): ?string
{
    // Studio format: "#TAG by Name"
    // Match " by " followed by the participant name to end of string.
    if (preg_match('/\bby\s+(.+)$/i', $title, $m)) {
        $name = trim($m[1]);
        // Strip any trailing tag/punctuation (e.g. " #TAG" after the name)
        $name = preg_replace('/\s+#\S+.*$/', '', $name);
        return apply_casing(trim($name));
    }

    // Bulk format: SIGN_City_Name_N [#TAG …]
    // Strip optional trailing tag(s) first, then split on underscores.
    $clean = preg_replace('/\s+#.*$/', '', $title);
    $parts = explode('_', $clean);
    if (count($parts) >= 3) {
        // Index 2 is always the participant; trim stray whitespace.
        return apply_casing(trim($parts[2]));
    }

    return null;
}

/**
 * Apply display casing: promote fully-lowercase names with ucfirst().
 * Mixed-case and already-capitalised names (including accented chars) are
 * returned unchanged to avoid mangling things like "Mònica" or "AnaLaura".
 */
function apply_casing(string $name): string
{
    if (mb_strtolower($name, 'UTF-8') === $name) {
        // Entirely lowercase — capitalise first letter only.
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
             . mb_substr($name, 1, null, 'UTF-8');
    }
    return $name;
}

// ── Preview / dry-run report ──────────────────────────────────────────────────

$dryRun = !in_array('--write', $argv, true);

echo str_repeat('-', 76) . "\n";
echo sprintf("%-32s %-20s %s\n", 'ID', 'Participant', 'Title');
echo str_repeat('-', 76) . "\n";

$errors  = [];
$count   = 0;
foreach ($catalog['videos'] as &$video) {
    $participant = extract_participant($video['title']);
    if ($participant === null || $participant === '') {
        $errors[] = "Could not extract participant from: {$video['title']}";
        $participant = '';
    }
    $video['participant'] = $participant;
    echo sprintf("%-32s %-20s %s\n", $video['id'], $participant, $video['title']);
    $count++;
}
unset($video);

echo str_repeat('-', 76) . "\n";
echo "Total: {$count} entries\n";

if ($errors) {
    echo "\nWARNINGS:\n";
    foreach ($errors as $e) {
        echo "  ! {$e}\n";
    }
}

// ── Write ─────────────────────────────────────────────────────────────────────

if ($dryRun) {
    echo "\n[DRY RUN] catalog.json NOT written. Pass --write to apply changes.\n";
} else {
    $json = json_encode(
        $catalog,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    file_put_contents($catalogPath, $json . "\n");
    echo "\n[WRITTEN] catalog.json updated with participant field on all {$count} entries.\n";
}
