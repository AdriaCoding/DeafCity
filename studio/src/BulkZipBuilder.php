<?php

namespace Studio;

class BulkZipBuilder
{
    public function __construct(
        private readonly VttToSrtConverter $converter = new VttToSrtConverter(),
    ) {}

    /**
     * @param list<array{originalFilename: string, vttPath: string}> $entries
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
            $langSuffix = '_' . strtoupper($entry['language']);
            try {
                if ($entry['language'] !== 'en') {
                    $zip->addFromString(
                        $entry['originalFilename'] . $langSuffix . '.srt',
                        $this->converter->convert($entry['srcVttPath']),
                    );
                }
                $zip->addFromString(
                    $entry['originalFilename'] . '_EN.srt',
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
