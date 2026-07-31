<?php

namespace Studio;

class CatalogTagPool
{
    public function __construct(private string $catalogFilePath) {}

    /** @return string[] */
    public function getTagsSortedAlphabetically(): array
    {
        return (new CatalogEditor($this->catalogFilePath))->getAllTags();
    }
}
