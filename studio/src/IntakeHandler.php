<?php

namespace Studio;

class IntakeHandler
{
    public function __construct(
        private readonly VimeoIdParser $idParser,
        private readonly VimeoClient $vimeoClient,
        private readonly StudioConfig $studioConfig,
        private readonly JobManager $jobManager,
        private readonly WebVttValidator $vttValidator,
    ) {
    }

    /**
     * @return array{errors: array<string, string>, values: array<string, string>}
     */
    public function handlePost(array $post, array $files): array
    {
        $values = [
            'vimeo_input' => trim($post['vimeo_input'] ?? ''),
            'sign_language' => $post['sign_language'] ?? '',
            'edition' => $post['edition'] ?? '',
            'subtitle_language' => $post['subtitle_language'] ?? '',
        ];
        $errors = [];

        if ($this->jobManager->exists()) {
            $errors['_form'] = 'A job is already in progress. Cancel it before starting a new one.';
            return ['errors' => $errors, 'values' => $values];
        }

        try {
            $vimeoId = $this->idParser->parse($values['vimeo_input']);
        } catch (InvalidVimeoIdException $e) {
            $errors['vimeo_input'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        if (!$this->isValidChoice($values['sign_language'], $this->studioConfig->getSignLanguages())) {
            $errors['sign_language'] = 'Select a sign language.';
        }
        if (!$this->isValidChoice($values['edition'], $this->studioConfig->getEditions())) {
            $errors['edition'] = 'Select an edition.';
        }
        if (!$this->isValidChoice($values['subtitle_language'], $this->studioConfig->getSubtitleLanguages())) {
            $errors['subtitle_language'] = 'Select a subtitle language.';
        }

        $upload = $files['subtitle_file'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['subtitle_file'] = 'Upload a WebVTT subtitle file.';
        } elseif (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['subtitle_file'] = 'The subtitle file could not be uploaded.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        try {
            $videoTitle = $this->vimeoClient->getVideo($vimeoId);
        } catch (VimeoNotFoundException $e) {
            $errors['vimeo_input'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        try {
            $this->vttValidator->validate($upload['tmp_name'], $upload['name']);
        } catch (\InvalidArgumentException $e) {
            $errors['subtitle_file'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        try {
            $this->jobManager->create(
                [
                    'vimeo_id' => $vimeoId,
                    'video_title' => $videoTitle,
                    'sign_language' => $values['sign_language'],
                    'edition' => $values['edition'],
                    'subtitle_language' => $values['subtitle_language'],
                    'step' => 'subtitle-editor',
                ],
                new UploadedFile($upload['tmp_name'], $upload['name'])
            );
        } catch (\RuntimeException $e) {
            $errors['_form'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        return ['errors' => [], 'values' => $values, 'created' => true];
    }

    private function isValidChoice(string $id, array $options): bool
    {
        if ($id === '') {
            return false;
        }
        foreach ($options as $option) {
            if (($option['id'] ?? '') === $id) {
                return true;
            }
        }
        return false;
    }
}
