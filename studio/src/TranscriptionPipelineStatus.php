<?php

namespace Studio;

class TranscriptionPipelineStatus
{
    public function __construct(private readonly JobManager $jobManager) {}

    /** @return 'transcribing'|'revising'|'revision_error'|'translating'|'translation_error'|'download_ready' */
    public function getState(): string
    {
        if (!$this->jobManager->hasDraftVtt()) {
            return 'transcribing';
        }

        $revisionPath = $this->jobManager->revisionStatePath();
        if (is_file($revisionPath)) {
            $revisionData = json_decode(file_get_contents($revisionPath) ?: '{}', true);
            $revisionStatus = is_array($revisionData) ? ($revisionData['status'] ?? '') : '';
            if (in_array($revisionStatus, ['pending', 'running'], true)) {
                return 'revising';
            }
            if ($revisionStatus === 'error') {
                return 'revision_error';
            }
        }

        if ($this->isEnglishSource()) {
            return 'download_ready';
        }

        $raw = $this->jobManager->readTranslationState();
        if ($raw === null) {
            return 'translating';
        }

        $data = json_decode($raw, true);
        $topStatus = $data['status'] ?? 'pending';

        if (in_array($topStatus, ['pending', 'running'], true)) {
            return 'translating';
        }

        $enStatus = $data['languages']['en']['status'] ?? 'pending';

        if ($enStatus === 'error') {
            return 'translation_error';
        }

        if ($enStatus === 'done' && is_file($this->jobManager->draftVttPathForLang('en'))) {
            return 'download_ready';
        }

        return 'translating';
    }

    private function isEnglishSource(): bool
    {
        $job = $this->jobManager->read();

        return ($job['subtitle_language'] ?? '') === 'en';
    }
}
