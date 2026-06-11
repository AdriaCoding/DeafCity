<?php

namespace Studio;

class BulkZipBuilder
{
    private readonly SubtitleOutputBasename $basename;

    public function __construct(
        private readonly StudioConfig $studioConfig,
        private readonly VttToSrtConverter $converter = new VttToSrtConverter(),
        ?SubtitleOutputBasename $basename = null,
    ) {
        $this->basename = $basename ?? new SubtitleOutputBasename($studioConfig);
    }

    /**
     * @param list<array{originalFilename: string, language: string, enVttPath: string, srcVttPath: string}> $entries
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
                if ($entry['language'] !== 'en') {
                    $zip->addFromString(
                        $this->basename->transcriptionDownloadFilename(
                            $entry['originalFilename'],
                            $entry['language'],
                            $entry['language'],
                            'srt',
                        ),
                        $this->converter->convert($entry['srcVttPath']),
                    );
                }
                $zip->addFromString(
                    $this->basename->transcriptionDownloadFilename(
                        $entry['originalFilename'],
                        $entry['language'],
                        'en',
                        'srt',
                    ),
                    $this->converter->convert($entry['enVttPath']),
                );
            } catch (\RuntimeException $e) {
                $zip->close();
                throw new \RuntimeException('No s\'ha pogut convertir el fitxer VTT: ' . $e->getMessage());
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
}
