<?php

/**
 * Manual integration test: publication to Vimeo text tracks.
 * Usage: php studio/scripts/test_vimeo_publish.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Vimeo\Vimeo;

$vimeoId = '639494119';
$jobDir  = __DIR__ . '/../../data/jobs/current';

$sdk = new Vimeo(VIMEO_CLIENT_ID, VIMEO_CLIENT_SECRET, VIMEO_ACCESS_TOKEN);

// ── 1. GET existing tracks ─────────────────────────────────────────────────
echo "1. GET texttracks … ";
$r = $sdk->request('/videos/' . $vimeoId . '/texttracks', [], 'GET');
echo "HTTP {$r['status']}, count=" . count($r['body']['data'] ?? []) . "\n";

// ── 2. POST to request an upload slot ─────────────────────────────────────
echo "2. POST texttracks (create slot) … ";
$r2 = $sdk->request('/videos/' . $vimeoId . '/texttracks', [
    'type'     => 'captions',
    'language' => 'es',
    'name'     => 'Spanish',
], 'POST');
echo "HTTP {$r2['status']}\n";
if ($r2['status'] !== 201) {
    $err = $r2['body']['error'] ?? $r2['body']['developer_message'] ?? json_encode($r2['body']);
    echo "   Error: $err\n";
    exit(1);
}

// ── 3. PUT the file to the upload link ─────────────────────────────────────
$uploadLink = $r2['body']['link'] ?? null;
$trackUri   = $r2['body']['uri'] ?? null;
echo "3. Upload link obtained. PUT draft.vtt … ";
$filePath = $jobDir . '/draft.vtt';
$fp = fopen($filePath, 'r');
$curl = curl_init($uploadLink);
curl_setopt_array($curl, [
    CURLOPT_TIMEOUT       => 60,
    CURLOPT_UPLOAD        => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_READDATA      => $fp,
    CURLOPT_RETURNTRANSFER => true,
]);
$response  = curl_exec($curl);
$httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
fclose($fp);
echo "HTTP $httpCode\n";
if ($httpCode !== 200) {
    echo "   Upload failed\n";
    exit(1);
}

// ── 4. PATCH to activate ───────────────────────────────────────────────────
echo "4. PATCH to activate … ";
$r4 = $sdk->request($trackUri, ['active' => true, 'language' => 'es', 'name' => 'Spanish'], 'PATCH');
echo "HTTP {$r4['status']}\n";
if ($r4['status'] < 200 || $r4['status'] >= 300) {
    echo "   Activation failed\n";
    exit(1);
}

echo "\nAll steps succeeded.\n";
