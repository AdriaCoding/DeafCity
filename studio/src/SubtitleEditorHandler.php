<?php

namespace Studio;

class SubtitleEditorHandler
{
    public function __construct(
        private VttParser $vttParser,
        private CaptionFileIntegrityChecker $checker,
        private JobManager $jobManager,
    ) {}

    /**
     * Handle a save request.
     *
     * @param array $cues
     * @param array{savePath?: string, advanceStep?: bool, translate?: bool} $options
     * @return array{ok: bool, errors?: string[], translate?: bool}
     */
    public function handle(array $cues, array $options = []): array
    {
        $errors = $this->checker->check($cues);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $savePath = $options['savePath'] ?? $this->jobManager->draftVttPath();
        $existing = $this->vttParser->parse($savePath);
        $existing['cues'] = $cues;
        $vttContent = $this->vttParser->write($existing);

        file_put_contents($savePath, $vttContent);

        if (!empty($options['advanceStep'])) {
            $this->jobManager->update(['step' => 'translation']);
        }

        $result = ['ok' => true];
        if (!empty($options['translate'])) {
            $result['translate'] = true;
        }

        return $result;
    }

    /**
     * Decode and handle a raw JSON POST body.
     *
     * @param array{savePath?: string, advanceStep?: bool, translate?: bool} $options
     * @return array{ok: bool, errors?: string[], translate?: bool}
     */
    public function handleRawJson(string $body, array $options = []): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['cues']) || !is_array($decoded['cues'])) {
            return ['ok' => false, 'errors' => ['Cos de la sol·licitud no vàlid.']];
        }

        if (array_key_exists('translate', $decoded)) {
            $options['translate'] = (bool) $decoded['translate'];
            if ($options['translate']) {
                $options['advanceStep'] = true;
            }
        }

        return $this->handle($decoded['cues'], $options);
    }
}
