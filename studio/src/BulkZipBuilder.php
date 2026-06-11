<?php

namespace Studio;

class BulkZipBuilder
{
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
            $name = $entry['originalFilename'] . '_EN.vtt';
            $content = file_get_contents($entry['vttPath']);
            if ($content === false) {
                $zip->close();
                throw new \RuntimeException('No s\'ha pogut llegir el fitxer VTT.');
            }
            $zip->addFromString($name, $content);
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
