<?php

namespace Studio;

class EditedCaptionSrtDownload
{
    public function __construct(private readonly SrtParser $srtParser = new SrtParser())
    {
    }

    /**
     * @param list<mixed> $cues
     * @return array{ok: true, filename: string, body: string}|array{ok: false, error: string}
     */
    public function build(string $vimeoId, string $lang, array $cues): array
    {
        $filename = $this->filename($vimeoId, $lang);
        if ($filename === null) {
            return ['ok' => false, 'error' => 'Identificador o llengua no vàlids.'];
        }

        $normalized = [];
        foreach ($cues as $cue) {
            if (
                !is_array($cue)
                || !array_key_exists('start', $cue)
                || !array_key_exists('end', $cue)
                || !array_key_exists('text', $cue)
                || !is_numeric($cue['start'])
                || !is_numeric($cue['end'])
            ) {
                return ['ok' => false, 'error' => 'Cos de la sol·licitud no vàlid.'];
            }
            $normalized[] = [
                'start' => (float) $cue['start'],
                'end' => (float) $cue['end'],
                'text' => (string) $cue['text'],
            ];
        }

        return [
            'ok' => true,
            'filename' => $filename,
            'body' => $this->srtParser->write($normalized),
        ];
    }

    private function filename(string $vimeoId, string $lang): ?string
    {
        $vimeoId = trim($vimeoId);
        $lang = trim($lang);
        if ($vimeoId === '' || $lang === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $vimeoId)) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $lang)) {
            return null;
        }

        return $vimeoId . '_' . strtoupper($lang) . '.srt';
    }
}
