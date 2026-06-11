<?php

namespace Studio;

final class StudioHomeRoute
{
    public const VIEW_CONTINGUTS = 'continguts';
    public const VIEW_JOB_SHELL = 'job-shell';
    public const VIEW_TRANSCRIPTION_LOADING = 'transcription-loading';

    public static function resolveDefaultView(bool $hasActiveJob, bool $isTranscriptionJob): string
    {
        if ($hasActiveJob && $isTranscriptionJob) {
            return self::VIEW_TRANSCRIPTION_LOADING;
        }
        if ($hasActiveJob) {
            return self::VIEW_JOB_SHELL;
        }

        return self::VIEW_CONTINGUTS;
    }
}
