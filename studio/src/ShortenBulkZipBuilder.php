<?php

namespace Studio;

class ShortenBulkZipBuilder
{
    private readonly SubtitleOutputBasename $basename;

    public function __construct(?SubtitleOutputBasename $basename = null)
    {
        $this->basename = $basename ?? new SubtitleOutputBasename();
    }

    /**
     * @param list<array{originalFilename: string, language: string, srcSrtPath: string}> $entries
     */
    public function build(array $entries): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'shortenbulkzip');
        if ($zipPath === false) {
            throw new \RuntimeException('No s\'ha pogut crear l\'arxiu ZIP.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No s\'ha pogut obrir l\'arxiu ZIP.');
        }

        foreach ($entries as $entry) {
            try {
                $zip->addFromString(
                    $this->basename->srtFilename(
                        $entry['originalFilename'],
                        $entry['language'],
                        $entry['language'],
                    ),
                    $this->readOrFail($entry['srcSrtPath']),
                );
            } catch (\RuntimeException $e) {
                $zip->close();
                throw new \RuntimeException('No s\'ha pogut llegir el fitxer de subtítols: ' . $e->getMessage());
            }
        }

        $zip->close();
        $binary = file_get_contents($zipPath);
        unlink($zipPath);

        if ($binary === false) {
            throw new \RuntimeException('No s\'ha pogut llegir l\'arxiu ZIP.');
        }

        return $binary;
    }

    private function readOrFail(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException($path);
        }

        return $content;
    }
}
