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
        private readonly SrtParser $srtParser = new SrtParser(),
        private readonly IntakeSourceDetector $sourceDetector = new IntakeSourceDetector(),
    ) {
    }

    /**
     * @return array{
     *   errors: array<string, string>,
     *   values: array<string, string>,
     *   created?: bool,
     *   intake_format?: 'vtt'|'srt'
     * }
     */
    public function handlePost(array $post, array $files): array
    {
        $values = [
            'vimeo_input' => trim($post['vimeo_input'] ?? ''),
            'sign_language' => $post['sign_language'] ?? '',
            'edition' => $post['edition'] ?? '',
            'subtitle_language' => $post['subtitle_language'] ?? '',
            'intake_mode' => 'upload',
        ];
        $errors = [];

        if ($this->jobManager->exists()) {
            $errors['_form'] = 'Ja hi ha una feina en curs. Cancel·leu-la abans d\'en començar una de nova.';
            return ['errors' => $errors, 'values' => $values];
        }

        try {
            $vimeoId = $this->idParser->parse($values['vimeo_input']);
        } catch (InvalidVimeoIdException $e) {
            $errors['vimeo_input'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        if ($values['sign_language'] === '__new__' || !$this->isValidChoice($values['sign_language'], $this->studioConfig->getSignLanguages())) {
            $errors['sign_language'] = 'Seleccioneu una llengua de signes.';
        }
        if ($values['edition'] === '__new__' || !$this->isValidChoice($values['edition'], $this->studioConfig->getEditions())) {
            $errors['edition'] = 'Seleccioneu una edició.';
        }
        if (!$this->isValidChoice($values['subtitle_language'], $this->studioConfig->getSubtitleLanguages())) {
            $errors['subtitle_language'] = 'Seleccioneu una llengua de subtítols.';
        }

        $upload = $files['intake_file'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['intake_file'] = 'Pugeu un fitxer WebVTT, SubRip (.srt) o un fitxer d\'àudio de l\'intèrpret.';
        } elseif (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['intake_file'] = 'No s\'ha pogut pujar el fitxer.';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        try {
            $intakeMode = $this->sourceDetector->detect($upload['tmp_name'], $upload['name']);
        } catch (\InvalidArgumentException $e) {
            $errors['intake_file'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        $values['intake_mode'] = $intakeMode;

        try {
            $videoTitle = $this->vimeoClient->getVideo($vimeoId);
        } catch (VimeoNotFoundException $e) {
            $errors['vimeo_input'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
        }

        $meta = [
            'vimeo_id' => $vimeoId,
            'video_title' => $videoTitle,
            'sign_language' => $values['sign_language'],
            'edition' => $values['edition'],
            'subtitle_language' => $values['subtitle_language'],
            'step' => 'subtitle-editor',
            'intake_mode' => $intakeMode,
        ];

        try {
            if ($intakeMode === 'upload') {
                if ($this->sourceDetector->isSubRip($upload['tmp_name'], $upload['name'])) {
                    $this->srtParser->parse($upload['tmp_name']);
                    $this->jobManager->createWithSrt($meta, new UploadedFile($upload['tmp_name'], $upload['name']));

                    return [
                        'errors' => [],
                        'values' => $values,
                        'created' => true,
                        'intake_format' => 'srt',
                    ];
                }

                $this->vttValidator->validate($upload['tmp_name'], $upload['name']);
                $this->jobManager->create($meta, new UploadedFile($upload['tmp_name'], $upload['name']));

                return [
                    'errors' => [],
                    'values' => $values,
                    'created' => true,
                    'intake_format' => 'vtt',
                ];
            }

            $this->jobManager->createWithAudio($meta, new UploadedFile($upload['tmp_name'], $upload['name']));
        } catch (\InvalidArgumentException $e) {
            $errors['intake_file'] = $e->getMessage();
            return ['errors' => $errors, 'values' => $values];
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
