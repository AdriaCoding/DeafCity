<?php

namespace Studio;

class DataDirZipBuilder
{
    public function build(string $rootDir): string
    {
        $realRoot = realpath($rootDir);
        if ($realRoot === false || !is_dir($realRoot)) {
            throw new \RuntimeException('Directori de dades no trobat.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'datazip');
        if ($zipPath === false) {
            throw new \RuntimeException('No s\'ha pogut crear l\'arxiu ZIP.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No s\'ha pogut obrir l\'arxiu ZIP.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $realItemPath = realpath($item->getPathname());
            if ($realItemPath === false || !str_starts_with($realItemPath, $realRoot . DIRECTORY_SEPARATOR)) {
                // Defense in depth: never let a symlink or anything else
                // resolve outside the directory we were asked to zip.
                continue;
            }

            $relativePath = substr($realItemPath, strlen($realRoot) + 1);

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($realItemPath, $relativePath);
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
