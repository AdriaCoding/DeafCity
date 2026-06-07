<?php

namespace Studio;

class SubtitleLanguageTranslationTargetHandler
{
    public function __construct(private readonly StudioConfig $studioConfig)
    {
    }

    /**
     * @return array{ok: bool, translation_target?: bool, errors?: string[]}
     */
    public function handle(string $id, bool $value): array
    {
        $id = trim($id);
        if ($id === '') {
            return ['ok' => false, 'errors' => ['ID no especificat.']];
        }

        try {
            $this->studioConfig->setSubtitleLanguageTranslationTarget($id, $value);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'errors' => [$e->getMessage()]];
        }

        return ['ok' => true, 'translation_target' => $value];
    }
}
