<?php

namespace Studio;

class PipelineSteps
{
    private const LABELS = [
        'subtitle-editor' => 'Subtitle Editor',
    ];

    public static function label(string $step): string
    {
        return self::LABELS[$step] ?? ucfirst(str_replace('-', ' ', $step));
    }

    public static function route(string $step): string
    {
        return '?action=' . urlencode($step);
    }
}
