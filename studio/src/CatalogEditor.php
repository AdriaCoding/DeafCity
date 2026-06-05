<?php

namespace Studio;

class CatalogEditor
{
    public function __construct(private readonly string $catalogFilePath) {}

    public function updateVideo(string $videoId, string $title, array $tags): void
    {
        $fp = fopen($this->catalogFilePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('Could not open catalog for writing.');
        }

        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $catalog = json_decode($raw ?: '', true);
        if (!is_array($catalog) || !isset($catalog['videos'])) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException('Invalid catalog JSON.');
        }

        $found = false;
        foreach ($catalog['videos'] as &$entry) {
            if (($entry['vimeo_id'] ?? '') === $videoId) {
                $entry['title'] = $title;
                $entry['tags'] = $tags;
                $found = true;
                break;
            }
        }
        unset($entry);

        if (!$found) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new \RuntimeException("Video $videoId not found in catalog.");
        }

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /** @return string[] */
    public function getReferencedEditionIds(): array
    {
        return $this->collectField('edition');
    }

    /** @return string[] */
    public function getReferencedSignLanguageIds(): array
    {
        return $this->collectField('sign_language');
    }

    /** @return string[] */
    private function collectField(string $field): array
    {
        if (!is_file($this->catalogFilePath)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->catalogFilePath), true);
        if (!is_array($data)) {
            return [];
        }
        $seen = [];
        foreach ($data['videos'] ?? [] as $video) {
            $val = $video[$field] ?? '';
            if ($val !== '') {
                $seen[$val] = true;
            }
        }
        return array_keys($seen);
    }
}
