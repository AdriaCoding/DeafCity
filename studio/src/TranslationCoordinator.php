<?php

namespace Studio;

class TranslationCoordinator
{
    public function __construct(
        private JobManager $jobManager,
        private StudioConfig $studioConfig,
        private BackgroundJobLauncher $launcher,
    ) {}

    public function spawn(string $masterLang, ?string $singleLang = null): void
    {
        $translationState = new TranslationJobState($this->jobManager);
        $targets = $singleLang !== null
            ? [$singleLang]
            : $this->targetLanguages($masterLang);

        if ($targets === []) {
            $translationState->initiate([]);
            if ($singleLang === null) {
                $this->jobManager->update(['step' => 'tagging']);
            }
            return;
        }

        if ($singleLang === null) {
            $translationState->initiate($targets);
        } else {
            $translationState->resetLanguage($singleLang);
        }

        $this->launcher->launchTranslation(
            $this->jobManager->draftVttPath(),
            $this->jobManager->translationStatePath(),
            $masterLang,
            dirname($this->jobManager->draftVttPath()),
            $targets,
        );
    }

    /** @return list<string> */
    private function targetLanguages(string $masterLang): array
    {
        $targets = [];
        foreach ($this->studioConfig->getTranslationTargetLanguages() as $language) {
            $id = $language['id'] ?? '';
            if ($id !== '' && $id !== $masterLang) {
                $targets[] = $id;
            }
        }
        return $targets;
    }
}
