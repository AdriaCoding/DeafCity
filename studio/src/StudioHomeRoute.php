<?php

namespace Studio;

final class StudioHomeRoute
{
    public const VIEW_CONTINGUTS = 'continguts';

    public static function resolveDefaultView(bool $hasActiveJob, bool $isTranscriptionJob): string
    {
        return self::VIEW_CONTINGUTS;
    }
}
