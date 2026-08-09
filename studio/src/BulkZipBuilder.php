<?php

namespace Studio;

class BulkZipBuilder
{
    private readonly SubtitleOutputBasename $basename;

    public function __construct(?SubtitleOutputBasename $basename = null)
    {
        $this->basename = $basename ?? new SubtitleOutputBasename();
    }

    /**
     * @param list<array{originalFilename: string, language: string, enSrtPath: string, srcSrtPath: string}> $entries
     */
    public function build(array $entries): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'bulkzip');
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
                    $this->basename->transcriptionDownloadFilename(
                        $entry['originalFilename'],
                        $entry['language'],
                        'en',
                        'srt',
                    ),
                    $this->readOrFail($entry['enSrtPath']),
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
