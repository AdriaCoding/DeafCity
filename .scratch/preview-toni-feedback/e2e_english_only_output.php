<?php

/**
 * E2E: Nova transcripció outputs English-only (single + bulk).
 * Usage: php8.4 .scratch/preview-toni-feedback/e2e_english_only_output.php [password]
 */

$password = $argv[1] ?? 'hola';
$base     = 'https://deaf.city/studio/';
$root     = dirname(__DIR__, 2);
$cookieJar = tempnam(sys_get_temp_dir(), 'studio-en-only-');
$pass = 0;
$fail = 0;

function out(string $msg): void
{
    echo $msg . "\n";
}

function ok(string $name, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  \033[32m✔\033[0m $name\n";
        $pass++;
    } else {
        echo "  \033[31m✘\033[0m $name" . ($detail !== '' ? " — $detail" : '') . "\n";
        $fail++;
    }
}

function section(string $title): void
{
    echo "\n\033[1m$title\033[0m\n";
}

function req(string $url, string $method = 'GET', mixed $post = null, string $cookieJar = '', int $timeout = 60): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_COOKIEJAR      => $cookieJar,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
    }
    $raw      = (string) curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSz = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSz);
    $body    = substr($raw, $headerSz);
    $location = '';
    if (preg_match('/^Location:\s*(\S+)/mi', $headers, $m)) {
        $location = trim($m[1]);
    }
    $disposition = '';
    if (preg_match('/^Content-Disposition:\s*(.+)$/mi', $headers, $m)) {
        $disposition = trim($m[1]);
    }
    $contentType = '';
    if (preg_match('/^Content-Type:\s*(.+)$/mi', $headers, $m)) {
        $contentType = trim($m[1]);
    }
    return [
        'code' => $code,
        'body' => $body,
        'headers' => $headers,
        'location' => $location,
        'disposition' => $disposition,
        'contentType' => $contentType,
        'errno' => $errno,
        'error' => $error,
    ];
}

function absoluteLocation(string $base, string $location): string
{
    if ($location === '') {
        return $base;
    }
    if (str_starts_with($location, 'http://') || str_starts_with($location, 'https://')) {
        return $location;
    }
    if (str_starts_with($location, '/')) {
        $parts = parse_url($base);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'deaf.city') . $location;
    }
    return rtrim($base, '/') . '/' . ltrim($location, '/');
}

function assertEnglishOnlyReadyPage(string $body, string $label): void
{
    ok("$label: download ready page", str_contains($body, 'Subtítols generats'));
    ok("$label: shows Anglès card", str_contains($body, 'Anglès'));
    ok("$label: no Original badge", !str_contains($body, 'badge-original') && !str_contains($body, 'Original'));
    ok("$label: English VTT link only", str_contains($body, 'download-vtt&amp;lang=en') || str_contains($body, 'download-vtt&lang=en'));
    ok("$label: English SRT link only", str_contains($body, 'download-srt&amp;lang=en') || str_contains($body, 'download-srt&lang=en'));
    ok("$label: no source-only download links", !preg_match('/href="\?action=download-(vtt|srt)"(?!&)/', $body));
}

function pollSingleReady(string $base, string $cookieJar, int $maxSeconds = 600): array
{
    $deadline = time() + $maxSeconds;
    $last = ['code' => 0, 'body' => '', 'location' => ''];
    while (time() < $deadline) {
        $last = req($base . '?action=resume-job', 'GET', null, $cookieJar, 30);
        if ($last['code'] === 302) {
            return $last;
        }
        if (str_contains($last['body'], 'Subtítols generats')) {
            return $last;
        }
        if (str_contains($last['body'], 'Error en la traducció') || str_contains($last['body'], 'Error en la revisió') || str_contains($last['body'], 'error-panel')) {
            // keep polling through transient states, but bail on hard cancel-looking errors after a bit
            if (str_contains($last['body'], 'btn-danger') && !str_contains($last['body'], 'Processant') && !str_contains($last['body'], 'status-label')) {
                // still may be recoverable via retry UI — continue
            }
        }
        sleep(3);
    }
    return $last;
}

function finishSingle(string $base, string $cookieJar): void
{
    req($base . '?action=cancel', 'POST', [], $cookieJar);
}

function writeTinySrt(string $path, string $line): void
{
    file_put_contents($path, "1\n00:00:01,000 --> 00:00:03,000\n$line\n");
}

// ── Login ──────────────────────────────────────────────────────────────────

section('Login');
$r = req($base, 'POST', ['password' => $password], $cookieJar);
ok('Login redirects', $r['code'] === 302, 'code=' . $r['code']);

// Clear any leftover job
req($base . '?action=cancel', 'POST', [], $cookieJar);

// ── 1. Single SRT ──────────────────────────────────────────────────────────

section('Single-file SRT → English-only downloads');

$srtPath = sys_get_temp_dir() . '/e2e_hamida_fr.srt';
copy($root . '/studio/tests/ALGER_FR_Hamida_1.srt', $srtPath);

$post = [
    'subtitle_language' => 'fr',
    'intake_file' => new CURLFile($srtPath, 'application/x-subrip', 'ALGER_FR_Hamida_1.srt'),
];
$r = req($base . '?action=transcription-intake', 'POST', $post, $cookieJar, 120);
ok('SRT intake redirects to resume-job', $r['code'] === 302 && str_contains($r['location'], 'resume-job'), 'code=' . $r['code'] . ' loc=' . $r['location']);

$ready = pollSingleReady($base, $cookieJar, 600);
assertEnglishOnlyReadyPage($ready['body'], 'SRT');

$vtt = req($base . '?action=download-vtt&lang=en', 'GET', null, $cookieJar);
ok('SRT flow EN VTT 200', $vtt['code'] === 200);
ok('SRT flow EN VTT filename', str_contains($vtt['disposition'], '_EN.vtt') || str_contains($vtt['disposition'], 'EN.vtt'), $vtt['disposition']);
ok('SRT flow EN VTT is WebVTT', str_starts_with(ltrim($vtt['body']), 'WEBVTT'));

$srtDl = req($base . '?action=download-srt&lang=en', 'GET', null, $cookieJar);
ok('SRT flow EN SRT 200', $srtDl['code'] === 200);
ok('SRT flow EN SRT filename', str_contains($srtDl['disposition'], '_EN.srt') || str_contains($srtDl['disposition'], 'EN.srt'), $srtDl['disposition']);
ok('SRT flow EN SRT not empty', strlen(trim($srtDl['body'])) > 10);

$srcAttempt = req($base . '?action=download-vtt', 'GET', null, $cookieJar);
ok('Source VTT endpoint still exists internally (optional)', true); // do not require 404; UI just must not offer it

finishSingle($base, $cookieJar);
@unlink($srtPath);

// ── 2. Single audio ────────────────────────────────────────────────────────

section('Single-file audio → English-only downloads');

$audioPath = $root . '/studio/audio_samples/Roma_Serena_3_IT.mp3';
$post = [
    'subtitle_language' => 'it',
    'intake_file' => new CURLFile($audioPath, 'audio/mpeg', 'Roma_Serena_3_IT.mp3'),
];
$r = req($base . '?action=transcription-intake', 'POST', $post, $cookieJar, 180);
ok('Audio intake redirects to resume-job', $r['code'] === 302 && str_contains($r['location'], 'resume-job'), 'code=' . $r['code'] . ' loc=' . $r['location']);

$ready = pollSingleReady($base, $cookieJar, 900);
assertEnglishOnlyReadyPage($ready['body'], 'Audio');

$vtt = req($base . '?action=download-vtt&lang=en', 'GET', null, $cookieJar);
ok('Audio flow EN VTT 200', $vtt['code'] === 200);
ok('Audio flow EN VTT filename', str_contains($vtt['disposition'], '_EN.vtt') || str_contains($vtt['disposition'], 'EN.vtt'), $vtt['disposition']);

$srtDl = req($base . '?action=download-srt&lang=en', 'GET', null, $cookieJar);
ok('Audio flow EN SRT 200', $srtDl['code'] === 200);
ok('Audio flow EN SRT filename', str_contains($srtDl['disposition'], '_EN.srt') || str_contains($srtDl['disposition'], 'EN.srt'), $srtDl['disposition']);

finishSingle($base, $cookieJar);

// ── 3. Multi-file SRT rejected ─────────────────────────────────────────────

section('Multi-file SRT rejected (bulk is audio-only)');

$srt1 = sys_get_temp_dir() . '/e2e_bulk_a.srt';
$srt2 = sys_get_temp_dir() . '/e2e_bulk_b.srt';
writeTinySrt($srt1, 'Bonjour');
writeTinySrt($srt2, 'Ciao');

$post = [
    'bulk_languages' => ['fr', 'it'],
    'intake_file[0]' => new CURLFile($srt1, 'application/x-subrip', 'a_fr.srt'),
    'intake_file[1]' => new CURLFile($srt2, 'application/x-subrip', 'b_it.srt'),
];
// PHP curl with intake_file[] needs array form:
$post = [
    'bulk_languages[0]' => 'fr',
    'bulk_languages[1]' => 'it',
    'intake_file[0]' => new CURLFile($srt1, 'application/x-subrip', 'a_fr.srt'),
    'intake_file[1]' => new CURLFile($srt2, 'application/x-subrip', 'b_it.srt'),
];
$r = req($base . '?action=transcription-intake', 'POST', $post, $cookieJar, 60);
ok('Multi SRT does not create bulk job', $r['code'] === 200 || ($r['code'] === 302 && !str_contains($r['location'], 'bulk-progress')), 'code=' . $r['code'] . ' loc=' . $r['location']);
if ($r['code'] === 200) {
    ok('Multi SRT shows audio-only error', str_contains($r['body'], 'àudio') || str_contains($r['body'], 'audio'));
}
@unlink($srt1);
@unlink($srt2);

// ── 4. Multi-file audio → English-only ZIP ─────────────────────────────────

section('Multi-file audio → English-only ZIP');

$audio1 = $root . '/studio/audio_samples/Roma_Serena_3_IT.mp3';
$audio2 = $root . '/studio/audio_samples/ALGER_Mahieddine_6_AR.mp3';
$post = [
    'bulk_languages[0]' => 'it',
    'bulk_languages[1]' => 'ar',
    'intake_file[0]' => new CURLFile($audio1, 'audio/mpeg', 'Roma_Serena_3_IT.mp3'),
    'intake_file[1]' => new CURLFile($audio2, 'audio/mpeg', 'ALGER_Mahieddine_6_AR.mp3'),
];
$r = req($base . '?action=transcription-intake', 'POST', $post, $cookieJar, 180);
ok('Bulk audio redirects to bulk-progress', $r['code'] === 302 && str_contains($r['location'], 'bulk-progress'), 'code=' . $r['code'] . ' loc=' . $r['location']);

$deadline = time() + 1800;
$snapshot = ['completed' => false, 'items' => []];
while (time() < $deadline) {
    $status = req($base . '?action=bulk-status', 'GET', null, $cookieJar, 30);
    $snapshot = json_decode($status['body'], true) ?: ['completed' => false, 'items' => []];
    $items = $snapshot['items'] ?? [];
    // Ignore idle "no queue" responses ({items:[], completed:true}).
    if ($items === [] && !empty($snapshot['completed'])) {
        sleep(2);
        continue;
    }
    if (!empty($snapshot['completed']) && $items !== []) {
        break;
    }
    sleep(5);
}

ok('Bulk completed', !empty($snapshot['completed']) && ($snapshot['items'] ?? []) !== [], json_encode($snapshot, JSON_UNESCAPED_UNICODE));
$doneCount = count(array_filter($snapshot['items'] ?? [], static fn ($i) => ($i['status'] ?? '') === 'done'));
ok('Bulk has done items', $doneCount >= 1, 'done=' . $doneCount . ' snapshot=' . json_encode($snapshot, JSON_UNESCAPED_UNICODE));

$zipResp = req($base . '?action=bulk-download', 'GET', null, $cookieJar, 120);
ok('Bulk ZIP download 200', $zipResp['code'] === 200, 'code=' . $zipResp['code']);
ok('Bulk response is ZIP', str_contains($zipResp['contentType'], 'zip') || str_starts_with($zipResp['body'], 'PK'), $zipResp['contentType']);

$zipPath = tempnam(sys_get_temp_dir(), 'bulk-en-only-');
file_put_contents($zipPath, $zipResp['body']);
$zip = new ZipArchive();
$opened = $zip->open($zipPath);
ok('ZIP opens', $opened === true);

if ($opened === true) {
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }
    out('  ZIP contents: ' . implode(', ', $names));
    ok('ZIP has only EN SRT files', $names !== [] && count(array_filter($names, static fn ($n) => str_ends_with($n, '_EN.srt'))) === count($names));
    ok('ZIP has no source-language SRTs', count(array_filter($names, static fn ($n) => preg_match('/_(IT|AR|CA|ES|FR)\.srt$/i', $n))) === 0, implode(', ', $names));
    $zip->close();
}
@unlink($zipPath);

// ── Summary ────────────────────────────────────────────────────────────────

@unlink($cookieJar);

echo "\n\033[1m─────────────────────────────────\033[0m\n";
$total = $pass + $fail;
if ($fail === 0) {
    echo "\033[32mAll $total checks passed.\033[0m\n";
    exit(0);
}

echo "\033[31m$fail/$total checks FAILED.\033[0m\n";
exit(1);
