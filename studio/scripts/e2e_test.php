<?php

/**
 * E2E tests for the Studio app.
 * Usage: php studio/scripts/e2e_test.php
 * Does not read config.php — password is passed as an argument or defaulted.
 */

$password = $argv[1] ?? 'hola';
$base     = 'https://deaf.city/studio/';

$cookieJar = tempnam(sys_get_temp_dir(), 'studio-e2e-');
$pass = 0;
$fail = 0;

// ── helpers ────────────────────────────────────────────────────────────────

function req(string $url, string $method = 'GET', array $post = [], string $cookieJar = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_COOKIEJAR      => $cookieJar,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw      = (string) curl_exec($ch);
    $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSz = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSz);
    $body    = substr($raw, $headerSz);
    // extract Location header if present
    $location = '';
    if (preg_match('/^Location:\s*(\S+)/mi', $headers, $m)) {
        $location = trim($m[1]);
    }
    return ['code' => $code, 'body' => $body, 'location' => $location];
}

function ok(string $name, bool $cond): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  \033[32m✔\033[0m $name\n";
        $pass++;
    } else {
        echo "  \033[31m✘\033[0m $name\n";
        $fail++;
    }
}

function section(string $title): void
{
    echo "\n\033[1m$title\033[0m\n";
}

// ── Auth gate ──────────────────────────────────────────────────────────────

section('Auth gate');

$r = req($base, 'GET', [], $cookieJar);
ok('Unauthenticated GET returns 200 (blocker)',        $r['code'] === 200);
ok('Blocker shows password input',                     str_contains($r['body'], 'type="password"'));
ok('Blocker does not show studio shell',               !str_contains($r['body'], 'action=logout'));

// ── Wrong password ─────────────────────────────────────────────────────────

section('Wrong password');

$r = req($base, 'POST', ['password' => 'wrong'], $cookieJar);
ok('Wrong password returns 200 (stay on blocker)',     $r['code'] === 200);
ok('Still shows password input',                       str_contains($r['body'], 'type="password"'));
ok('Does not set auth session (no logout link)',        !str_contains($r['body'], 'action=logout'));

// ── Correct login ──────────────────────────────────────────────────────────

section('Correct login');

$r = req($base, 'POST', ['password' => $password], $cookieJar);
ok('Correct password redirects (302)',                 $r['code'] === 302);
ok('Redirect target is studio root',                   str_contains($r['location'], '/studio'));

// Follow redirect to shell
$r = req($base, 'GET', [], $cookieJar);
ok('Authenticated GET returns 200',                    $r['code'] === 200);
ok('Shell shows logout link',                          str_contains($r['body'], 'action=logout'));
ok('Shell shows current job title',                    str_contains($r['body'], 'LSM_Ciudad de México_Luis_2'));
ok('Shell shows current step label (Etiquetatge)',     str_contains($r['body'], 'Etiquetatge'));
ok('Shell shows Resume button',                        str_contains($r['body'], 'action=tagging'));

// ── Tagging step — GET ─────────────────────────────────────────────────────

section('Tagging — GET');

$r = req($base . '?action=tagging', 'GET', [], $cookieJar);
ok('GET tagging returns 200',                          $r['code'] === 200);
ok('Shows Etiquetatge heading',                        str_contains($r['body'], 'Etiquetatge'));
ok('Shows tag checkbox list container',                str_contains($r['body'], 'tag-list'));
ok('Shows new-tag input',                              str_contains($r['body'], 'new-tag-input'));
ok('Shows save button',                                str_contains($r['body'], 'Desa i continua'));

// ── Tagging step — POST validation error ──────────────────────────────────

section('Tagging — validation (zero tags)');

$r = req($base . '?action=tagging', 'POST', [], $cookieJar);
ok('POST with no tags returns 200 (stays on form)',    $r['code'] === 200);
ok('Shows validation error in Catalan',                str_contains($r['body'], 'almenys una etiqueta'));
ok('Job step still tagging (no redirect)',             !str_contains($r['body'], 'action=logout') || str_contains($r['body'], 'Etiquetatge'));

// ── Tagging step — POST success ────────────────────────────────────────────

section('Tagging — save tags');

$r = req($base . '?action=tagging', 'POST', ['tags' => ['humor', 'ciudad']], $cookieJar);
ok('POST with valid tags redirects (302)',              $r['code'] === 302);

// Follow redirect to shell
$r = req($base, 'GET', [], $cookieJar);
ok('Shell now shows Publicació as step',               str_contains($r['body'], 'Publicació'));
ok('Shell Resume link points to publication',          str_contains($r['body'], 'action=publication'));

// ── Publication step — GET ─────────────────────────────────────────────────

section('Publication — GET');

$r = req($base . '?action=publication', 'GET', [], $cookieJar);
ok('GET publication returns 200',                      $r['code'] === 200);
ok('Shows Publicació heading',                         str_contains($r['body'], 'Publicació'));
ok('Shows video title in summary',                     str_contains($r['body'], 'LSM_Ciudad de México_Luis_2'));
ok('Shows sign language in summary',                   str_contains($r['body'], 'LSM Mexican Sign Language'));
ok('Shows tags in summary (humor)',                    str_contains($r['body'], 'humor'));
ok('Shows Publicar button',                            str_contains($r['body'], 'Publicar'));

// ── Logout ────────────────────────────────────────────────────────────────

section('Logout');

$r = req($base . '?action=logout', 'GET', [], $cookieJar);
ok('Logout redirects (302)',                           $r['code'] === 302);

$r = req($base, 'GET', [], $cookieJar);
ok('After logout GET returns 200 (blocker)',           $r['code'] === 200);
ok('Blocker password input shown again',               str_contains($r['body'], 'type="password"'));
ok('No shell content after logout',                    !str_contains($r['body'], 'LSM_Ciudad'));

// ── Summary ────────────────────────────────────────────────────────────────

@unlink($cookieJar);

echo "\n\033[1m─────────────────────────────────\033[0m\n";
$total = $pass + $fail;
if ($fail === 0) {
    echo "\033[32mAll $total tests passed.\033[0m\n";
} else {
    echo "\033[31m$fail/$total tests FAILED.\033[0m\n";
    exit(1);
}
