<?php

namespace Studio;

class PipelineSteps
{
    private const LABELS = [
        'translation' => 'Traducció',
        'tagging'     => 'Etiquetatge',
        'publication' => 'Publicació',
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
