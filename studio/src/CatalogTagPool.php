<?php

namespace Studio;

class CatalogTagPool
{
    public function __construct(private string $catalogFilePath) {}

    /** @return string[] */
    public function getTagsSortedAlphabetically(): array
    {
        if (!is_file($this->catalogFilePath)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->catalogFilePath), true);
        if (!is_array($data) || !isset($data['videos'])) {
            return [];
        }

        $seen = [];
        foreach ($data['videos'] as $video) {
            foreach ($video['tags'] ?? [] as $tag) {
                $seen[$tag] = true;
            }
        }

        $tags = array_keys($seen);
        sort($tags);
        return $tags;
    }
}
