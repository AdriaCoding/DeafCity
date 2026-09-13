<?php

namespace Studio;

class DeafcityMapStore
{
    /** @var list<array{id: string, city: string, sign_language_code: string, label: string, coordinates: array{0: float, 1: float}}> */
    private array $locations;

    public function __construct(private readonly string $storePath)
    {
        $this->locations = $this->readFile();
    }

    /**
     * @return list<array{id: string, city: string, sign_language_code: string, label: string, coordinates: array{0: float, 1: float}}>
     */
    public function all(): array
    {
        return $this->locations;
    }

    /**
     * @param array{id: string, city: string, sign_language_code: string, label: string, coordinates: array{0: float, 1: float}} $location
     */
    public function add(array $location): void
    {
        $fp = $this->openLocked();
        $locations = $this->decodeLocked($fp);
        foreach ($locations as $existing) {
            if (($existing['id'] ?? '') === $location['id']) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new \RuntimeException('Aquesta localització ja existeix al mapa.');
            }
        }
        array_unshift($locations, $location);
        $this->writeLocked($fp, $locations);
        $this->locations = $locations;
    }

    public function remove(string $id): void
    {
        $fp = $this->openLocked();
        $locations = $this->decodeLocked($fp);
        $filtered = array_values(array_filter(
            $locations,
            static fn(array $item): bool => ($item['id'] ?? '') !== $id,
        ));
        $this->writeLocked($fp, $filtered);
        $this->locations = $filtered;
    }

    /** @return resource */
    private function openLocked()
    {
        $fp = fopen($this->storePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open deafcity map store for writing.');
        }
        flock($fp, LOCK_EX);

        return $fp;
    }

    /**
     * @param resource $fp
     * @return list<array<string, mixed>>
     */
    private function decodeLocked($fp): array
    {
        $raw = stream_get_contents($fp);
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException('Invalid deafcity map JSON.');
        }

        return array_values($data);
    }

    /**
     * @param resource $fp
     * @param list<array<string, mixed>> $locations
     */
    private function writeLocked($fp, array $locations): void
    {
        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($locations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * @return list<array{id: string, city: string, sign_language_code: string, label: string, coordinates: array{0: float, 1: float}}>
     */
    private function readFile(): array
    {
        if (!is_readable($this->storePath)) {
            return [];
        }

        $json = file_get_contents($this->storePath);
        if ($json === false) {
            throw new \RuntimeException('Could not read deafcity map store.');
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid deafcity map JSON.');
        }

        return array_values($decoded);
    }
}
