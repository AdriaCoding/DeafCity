<?php

namespace Studio;

/**
 * Turns an uploaded caption file into validated content in the stored format.
 *
 * Five intake paths independently implemented the same idiom — detect the
 * uploaded format, convert it when it is not the one Studio stores, validate
 * the result, save — each with its own private tempfile-based content
 * validator. This is that idiom, once.
 *
 * Uploads stay dual-format: both WebVTT and SubRip are accepted. Only the
 * stored side is normalised, so flipping which format that is means changing
 * this class rather than five call sites.
 */
class CaptionIntakeNormalizer
{
    public function __construct(
        private readonly IntakeSourceDetector $sourceDetector = new IntakeSourceDetector(),
        private readonly SrtToVttConverter $srtConverter = new SrtToVttConverter(),
        private readonly WebVttValidator $vttValidator = new WebVttValidator(),
    ) {
    }

    /**
     * @return string validated caption content in the stored format
     *
     * @throws \InvalidArgumentException when the upload is not valid captions
     * @throws \RuntimeException when the file cannot be read or validated
     */
    public function normalize(string $tmpPath, string $originalName): string
    {
        if ($this->sourceDetector->isSubRip($tmpPath, $originalName)) {
            $content = $this->srtConverter->convert($tmpPath);
            $this->validateContent($content, pathinfo($originalName, PATHINFO_FILENAME) . '.vtt');

            return $content;
        }

        $this->vttValidator->validate($tmpPath, $originalName);

        $content = file_get_contents($tmpPath);
        if ($content === false) {
            throw new \RuntimeException('No s\'ha pogut llegir el fitxer de subtítols pujat.');
        }

        return $content;
    }

    /**
     * The validator works on paths, so converted content has to reach disk
     * before it can be checked. The label carries the extension the validator
     * expects, since the temp file's own name does not.
     */
    private function validateContent(string $content, string $label): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'studio-intake-caption-');
        if ($tmpPath === false) {
            throw new \RuntimeException('No s\'ha pogut validar el fitxer de subtítols.');
        }

        try {
            if (file_put_contents($tmpPath, $content) === false) {
                throw new \RuntimeException('No s\'ha pogut validar el fitxer de subtítols.');
            }
            $this->vttValidator->validate($tmpPath, $label);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }
}
