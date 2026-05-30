<?php

/**
 * Env-gated live smoke test for the Groq transcription integration.
 *
 * Performs ONE real Groq transcription of a sample audio file to confirm the
 * key and endpoint work. Does not print credentials. Gated behind an env flag
 * so it never runs in the automated suite or by accident.
 *
 * Usage:
 *   GROQ_SMOKE=1 php studio/scripts/test_groq_transcribe.php [path/to/audio]
 *
 * Defaults to a committed benchmark sample if no path is given.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Studio\AudioPreprocessor;
use Studio\GroqTranscriber;
use Studio\GroqTranscriptionException;

if (getenv('GROQ_SMOKE') !== '1') {
    fwrite(STDERR, "Refusing to run: set GROQ_SMOKE=1 to perform a live Groq transcription.\n");
    exit(2);
}

$apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
if ($apiKey === '') {
    fwrite(STDERR, "GROQ_API_KEY is blank in config.php — nothing to smoke-test.\n");
    exit(2);
}

$baseUrl = defined('GROQ_BASE_URL') ? GROQ_BASE_URL : 'https://api.groq.com/openai/v1';
$model = defined('GROQ_TRANSCRIBE_MODEL') ? GROQ_TRANSCRIBE_MODEL : 'whisper-large-v3-turbo';
$timeout = defined('GROQ_TIMEOUT_SECONDS') ? GROQ_TIMEOUT_SECONDS : 20;

$audio = $argv[1] ?? (__DIR__ . '/../audio_samples/BCN_Raquel_3.mp3');
$language = $argv[2] ?? 'ca';

if (!is_file($audio)) {
    fwrite(STDERR, "Audio file not found: $audio\n");
    exit(1);
}

$tmpDir = sys_get_temp_dir();
echo "Groq base URL: $baseUrl\n";
echo "Model: $model\n";
echo "Sample: $audio (lang=$language)\n\n";

echo 'Transcoding to 16 kHz mono FLAC … ';
$flac = null;
try {
    $flac = (new AudioPreprocessor())->toGroqUpload($audio, $tmpDir);
    echo "OK (" . round(filesize($flac) / 1024) . " KB)\n";
} catch (\Throwable $e) {
    echo "FAILED\n   " . $e->getMessage() . "\n";
    exit(1);
}

echo 'Calling Groq … ';
$start = microtime(true);
try {
    $cues = (new GroqTranscriber($apiKey, $baseUrl, $timeout))
        ->transcribe($flac, $model, $language);
    $wall = microtime(true) - $start;
    echo "OK (" . round($wall, 2) . "s, " . count($cues) . " cues)\n\n";
    foreach (array_slice($cues, 0, 5) as $cue) {
        printf("  [%6.2f → %6.2f] %s\n", $cue['start'], $cue['end'], $cue['text']);
    }
    if (count($cues) > 5) {
        echo "  …\n";
    }
} catch (GroqTranscriptionException $e) {
    echo "FAILED (category=" . $e->category() . ")\n   " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if ($flac !== null && is_file($flac)) {
        unlink($flac);
    }
}

echo "\nGroq transcription smoke test passed.\n";
exit(0);
