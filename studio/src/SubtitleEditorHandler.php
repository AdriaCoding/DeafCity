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
     * @param array $cues  Cue array decoded from the POST body
     * @return array{ok: bool, errors?: string[]}
     */
    public function handle(array $cues): array
    {
        $errors = $this->checker->check($cues);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $draftPath = $this->jobManager->draftVttPath();
        $existing = $this->vttParser->parse($draftPath);
        $existing['cues'] = $cues;
        $vttContent = $this->vttParser->write($existing);

        file_put_contents($draftPath, $vttContent);

        $this->jobManager->update(['step' => 'translation']);

        return ['ok' => true];
    }

    /**
     * Decode and handle a raw JSON POST body.
     *
     * @return array{ok: bool, errors?: string[]}
     */
    public function handleRawJson(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['cues']) || !is_array($decoded['cues'])) {
            return ['ok' => false, 'errors' => ['Invalid request body.']];
        }

        return $this->handle($decoded['cues']);
    }
}
