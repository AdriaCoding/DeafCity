<?php
/**
 * Locale payload for the in-session Website language switch.
 *
 * GET /preview/api/locale.php?lang=<id>
 *
 * Returns the resolved chrome strings, text direction and localized filter option
 * labels for a Website language — the same values the server would render on a cold
 * load at ?lang=<id>. Read-only; touches no session and no Catalog state.
 */

require_once __DIR__ . '/../lib/preview_locale.php';

$requestedLang = isset($_GET['lang']) ? trim((string) $_GET['lang']) : '';
$payload = preview_build_locale_payload($requestedLang);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    // Labels change whenever a Producer edits copy in Studio, so this must not be
    // cached by intermediaries; the switch is cheap enough not to need it.
    header('Cache-Control: no-store');
}

echo json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
