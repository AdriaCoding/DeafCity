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
     * @param array{lang?: string|null, advanceStep?: bool, translate?: bool} $options
     * @return array{ok: bool, errors?: string[], cueErrors?: array, translate?: bool}
     */
    public function handle(array $cues, array $options = []): array
    {
        $cueErrors = $this->checker->check($cues);
        if ($cueErrors !== []) {
            return [
                'ok' => false,
                'errors' => array_values(array_unique(array_column($cueErrors, 'message'))),
                'cueErrors' => $cueErrors,
            ];
        }

        $lang = $options['lang'] ?? null;
        $readPath = $lang !== null
            ? $this->jobManager->draftVttPathForLang($lang)
            : $this->jobManager->draftVttPath();

        $existing = $this->vttParser->parse($readPath);
        $existing['cues'] = $cues;
        $vttContent = $this->vttParser->write($existing);

        if ($lang !== null) {
            $this->jobManager->writeDraftVttForLang($lang, $vttContent);
        } else {
            $this->jobManager->writeDraftVtt($vttContent);
        }

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
     * @param array $cues
     * @return array{ok: bool, errors?: string[], cueErrors?: array}
     */
    public function handleForFilePath(string $vttPath, array $cues): array
    {
        $cueErrors = $this->checker->check($cues);
        if ($cueErrors !== []) {
            return [
                'ok' => false,
                'errors' => array_values(array_unique(array_column($cueErrors, 'message'))),
                'cueErrors' => $cueErrors,
            ];
        }

        $existing = $this->vttParser->parse($vttPath);
        $existing['cues'] = $cues;
        $vttContent = $this->vttParser->write($existing);

        if (file_put_contents($vttPath, $vttContent) === false) {
            return ['ok' => false, 'errors' => ['No s\'ha pogut desar el fitxer de subtítols.']];
        }

        return ['ok' => true];
    }

    /**
     * Decode and handle a raw JSON POST body.
     *
     * @param array{lang?: string|null, advanceStep?: bool, translate?: bool} $options
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
