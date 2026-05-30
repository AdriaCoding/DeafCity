<?php

namespace Studio;

/**
 * Transcodes Interpreter audio to a 16 kHz mono FLAC for upload to Groq.
 *
 * Whisper resamples to 16 kHz mono internally, so this is lossless for the
 * model while shrinking the upload ~11x and removing Groq's size cap as a
 * normal-case failure. The FLAC is written into the Job folder.
 *
 * The exec seam mirrors PHP's exec(): (string $cmd, array &$output, int &$code).
 */
class AudioPreprocessor
{
    private const FFMPEG = '/usr/bin/ffmpeg';

    /** @var callable(string, array, int): void */
    private $exec;

    /**
     * @param callable(string, array, int): void|null $exec
     */
    public function __construct(?callable $exec = null)
    {
        $this->exec = $exec ?? static function (string $cmd, array &$output, int &$code): void {
            exec($cmd, $output, $code);
        };
    }

    /**
     * @throws \RuntimeException on a non-zero ffmpeg exit
     */
    public function toGroqUpload(string $srcPath, string $jobDir): string
    {
        $flacPath = rtrim($jobDir, '/') . '/groq_upload.flac';

        $cmd = sprintf(
            '%s -y -i %s -ar 16000 -ac 1 -c:a flac %s 2>&1',
            escapeshellarg(self::FFMPEG),
            escapeshellarg($srcPath),
            escapeshellarg($flacPath),
        );

        $output = [];
        $code = 0;
        ($this->exec)($cmd, $output, $code);

        if ($code !== 0) {
            throw new \RuntimeException(
                'No s\'ha pogut convertir l\'àudio per a la transcripció.'
            );
        }

        return $flacPath;
    }
}
