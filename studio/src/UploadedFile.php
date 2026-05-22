<?php

namespace Studio;

class UploadedFile
{
    public function __construct(
        public readonly string $tmpPath,
        public readonly string $originalName,
    ) {
    }
}
