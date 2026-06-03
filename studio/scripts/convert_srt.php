#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Studio\SrtToVttConverter;
use Studio\WebVttValidator;

$opts = getopt('', ['srt_path:', 'vtt_output:', 'status_file:']);
$srtPath = $opts['srt_path'] ?? '';
$vttOutput = $opts['vtt_output'] ?? '';
$statusFile = $opts['status_file'] ?? '';

if ($srtPath === '' || $vttOutput === '' || $statusFile === '') {
    fwrite(STDERR, "Usage: convert_srt.php --srt_path PATH --vtt_output PATH --status_file PATH\n");
    exit(1);
}

function writeStatus(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
}

writeStatus($statusFile, ['status' => 'running']);

try {
    $vtt = (new SrtToVttConverter())->convert($srtPath);
    $tmp = $vttOutput . '.tmp';
    if (file_put_contents($tmp, $vtt) === false) {
        throw new RuntimeException('No s\'ha pogut escriure el fitxer de subtítols.');
    }
    (new WebVttValidator())->validate($tmp, 'draft.vtt');
    if (!rename($tmp, $vttOutput)) {
        unlink($tmp);
        throw new RuntimeException('No s\'ha pogut desar el fitxer de subtítols.');
    }
    writeStatus($statusFile, ['status' => 'done']);
    exit(0);
} catch (Throwable $e) {
    if (is_file($vttOutput . '.tmp')) {
        unlink($vttOutput . '.tmp');
    }
    $message = $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : 'Error en la conversió de subtítols.';
    writeStatus($statusFile, ['status' => 'error', 'message' => $message]);
    exit(1);
}
