<?php

namespace Studio;

class TranscriptionPipelineStatus
{
    public function __construct(private readonly JobManager $jobManager) {}

    /** @return 'transcribing'|'translating'|'translation_error'|'download_ready' */
    public function getState(): string
    {
        if (!$this->jobManager->hasDraftVtt()) {
            return 'transcribing';
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
