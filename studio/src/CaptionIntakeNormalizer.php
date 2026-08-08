<?php

namespace Studio;

/**
 * Turns an uploaded caption file into validated content in a stored format.
 *
 * Five intake paths independently implemented the same idiom — detect the
 * uploaded format, convert it when it is not the one being stored, validate
 * the result, save — each with its own private tempfile-based content
 * validator. This is that idiom, once.
 *
 * Uploads stay dual-format: both WebVTT and SubRip are accepted, and detection
 * is by content rather than by extension.
 *
 * The target format is explicit because there are currently two stores with
 * different formats: published captions under data/captions/ are SubRip, while
 * the job pipeline's drafts are still WebVTT until that side is migrated. When
 * the drafts follow, every caller collapses onto the default and the parameter
 * can go.
 */
class CaptionIntakeNormalizer
{
    public const FORMAT_SRT = 'srt';
    public const FORMAT_VTT = 'vtt';

    public function __construct(
        private readonly IntakeSourceDetector $sourceDetector = new IntakeSourceDetector(),
        private readonly VttToSrtConverter $vttToSrt = new VttToSrtConverter(),
        private readonly SrtToVttConverter $srtToVtt = new SrtToVttConverter(),
        private readonly WebVttValidator $vttValidator = new WebVttValidator(),
        private readonly SrtValidator $srtValidator = new SrtValidator(),
    ) {
    }

    /**
     * @param self::FORMAT_* $targetFormat
     * @return string validated caption content in $targetFormat
     *
     * @throws \InvalidArgumentException when the upload is not valid captions
     * @throws \RuntimeException when the file cannot be read or validated
     */
    public function normalize(
        string $tmpPath,
        string $originalName,
        string $targetFormat = self::FORMAT_SRT,
    ): string {
        $stem = pathinfo($originalName, PATHINFO_FILENAME);
        $uploadIsVtt = $this->sourceDetector->isWebVtt($tmpPath, $originalName);

        if ($uploadIsVtt) {
            $this->vttValidator->validate($tmpPath, $originalName);
        } else {
            /*
             * Validated against a .srt label rather than the uploaded name, so
             * a SubRip file arriving as .txt is still accepted — content, not
             * extension, has always decided this branch.
             */
            $this->srtValidator->validate($tmpPath, $stem . '.srt');
        }

        if ($targetFormat === self::FORMAT_VTT) {
            $content = $uploadIsVtt ? $this->read($tmpPath) : $this->srtToVtt->convert($tmpPath);
            $this->validateConverted($content, $stem . '.vtt', $this->vttValidator);

            return $content;
        }

        $content = $uploadIsVtt ? $this->vttToSrt->convert($tmpPath) : $this->read($tmpPath);
        $this->validateConverted($content, $stem . '.srt', $this->srtValidator);

        return $content;
    }

    private function read(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('No s\'ha pogut llegir el fitxer de subtítols pujat.');
        }

        return $content;
    }

    /**
     * The validators work on paths, so converted content has to reach disk
     * before it can be checked. The label carries the extension they expect,
     * since the temp file's own name does not.
     */
    private function validateConverted(
        string $content,
        string $label,
        WebVttValidator|SrtValidator $validator,
    ): void {
        $tmpPath = tempnam(sys_get_temp_dir(), 'studio-intake-caption-');
        if ($tmpPath === false) {
            throw new \RuntimeException('No s\'ha pogut validar el fitxer de subtítols.');
        }

        try {
            if (file_put_contents($tmpPath, $content) === false) {
                throw new \RuntimeException('No s\'ha pogut validar el fitxer de subtítols.');
            }
            $validator->validate($tmpPath, $label);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }
}
