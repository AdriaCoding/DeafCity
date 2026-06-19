<?php

namespace Studio;

final class ActiveJobBanner
{
    /** @return array{title: string, resumeUrl: string, detail: ?string}|null */
    public static function resolve(Container $c): ?array
    {
        if ($c->bulkIntakeQueue()->exists()) {
            return [
                'title' => 'Transcripció en massa',
                'resumeUrl' => '?action=bulk-progress',
                'detail' => null,
            ];
        }

        if (!$c->jobManager->exists()) {
            return null;
        }

        $job = $c->jobManager->read();

        if (($job['job_type'] ?? '') === 'transcription') {
            return [
                'title' => (string) ($job['original_filename'] ?? 'Transcripció'),
                'resumeUrl' => '?action=resume-job',
                'detail' => null,
            ];
        }

        $step = (string) ($job['step'] ?? 'translation');
        if ($step === 'subtitle-editor') {
            $step = 'translation';
        }

        $resumeUrl = PipelineSteps::route($step);
        if (($job['intake_mode'] ?? 'upload') === 'generate' && !$c->jobManager->hasDraftVtt()) {
            $resumeUrl = '?action=resume-job';
        }

        return [
            'title' => (string) ($job['video_title'] ?? 'Feina en curs'),
            'resumeUrl' => $resumeUrl,
            'detail' => PipelineSteps::label($step),
        ];
    }
}
