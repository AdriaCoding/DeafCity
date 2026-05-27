<?php

namespace Studio;

class PublicationHandler
{
    public function __construct(
        private VimeoClient $vimeoClient,
        private JobManager $jobManager,
        private StudioConfig $studioConfig,
        private string $captionsDirPath,
        private string $catalogFilePath,
    ) {}

    /** @return array{ok: bool, vimeoWarnings: string[]} */
    public function handle(): array
    {
        $job = $this->jobManager->read();
        $vimeoId = (string) ($job['vimeo_id'] ?? '');
        $captionFiles = $this->buildCaptionFiles($job);

        // Best-effort: delete existing text tracks before re-uploading
        try {
            $tracks = $this->vimeoClient->getTextTracks($vimeoId);
            foreach ($tracks as $track) {
                try {
                    $this->vimeoClient->deleteTextTrack($track['uri']);
                } catch (\Throwable) {
                    // ignore
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $vimeoWarnings = [];
        foreach ($captionFiles as $caption) {
            if (!is_file($caption['sourcePath'])) {
                continue;
            }
            @copy($caption['sourcePath'], $this->captionsDirPath . '/' . $caption['filename']);
            try {
                $this->vimeoClient->uploadAndActivateTextTrack(
                    $vimeoId,
                    $caption['sourcePath'],
                    $caption['lang'],
                    $caption['label'],
                );
            } catch (\Throwable) {
                $vimeoWarnings[] = $caption['label'];
            }
        }

        $this->writeCatalogEntry($job, $captionFiles);
        $this->jobManager->cancel();

        return ['ok' => true, 'vimeoWarnings' => $vimeoWarnings];
    }

    /** @return list<array{lang: string, label: string, sourcePath: string, filename: string}> */
    private function buildCaptionFiles(array $job): array
    {
        $vimeoId = (string) ($job['vimeo_id'] ?? '');
        $masterLang = (string) ($job['subtitle_language'] ?? '');
        $labelMap = [];
        foreach ($this->studioConfig->getSubtitleLanguages() as $l) {
            $labelMap[$l['id']] = $l['label'];
        }

        $files = [];

        if ($masterLang !== '' && is_file($this->jobManager->draftVttPath())) {
            $files[] = [
                'lang' => $masterLang,
                'label' => $labelMap[$masterLang] ?? $masterLang,
                'sourcePath' => $this->jobManager->draftVttPath(),
                'filename' => "$vimeoId.$masterLang.vtt",
            ];
        }

        foreach ($this->studioConfig->getSubtitleLanguages() as $l) {
            $lang = $l['id'];
            if ($lang === $masterLang) {
                continue;
            }
            $path = $this->jobManager->draftVttPathForLang($lang);
            if (!is_file($path)) {
                continue;
            }
            $files[] = [
                'lang' => $lang,
                'label' => $labelMap[$lang] ?? $lang,
                'sourcePath' => $path,
                'filename' => "$vimeoId.$lang.vtt",
            ];
        }

        return $files;
    }

    private function writeCatalogEntry(array $job, array $captionFiles): void
    {
        $vimeoId = (string) ($job['vimeo_id'] ?? '');
        $signLang = (string) ($job['sign_language'] ?? '');

        $captions = [];
        foreach ($captionFiles as $caption) {
            if (!is_file($caption['sourcePath'])) {
                continue;
            }
            $captions[] = [
                'lang' => $caption['lang'],
                'label' => $caption['label'],
                'file' => $caption['filename'],
            ];
        }

        $newEntry = [
            'id' => $signLang . '_' . $vimeoId,
            'vimeo_id' => $vimeoId,
            'title' => (string) ($job['video_title'] ?? ''),
            'sign_language' => $signLang,
            'edition' => (string) ($job['edition'] ?? ''),
            'tags' => $job['tags'] ?? [],
            'captions' => $captions,
        ];

        $fp = fopen($this->catalogFilePath, 'c+');
        if ($fp === false) {
            throw new \RuntimeException('No s\'ha pogut obrir el catàleg per escriptura.');
        }
        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $catalog = json_decode($raw ?: '', true);
        if (!is_array($catalog) || !isset($catalog['videos'])) {
            $catalog = ['videos' => []];
        }

        $replaced = false;
        foreach ($catalog['videos'] as &$entry) {
            if (($entry['vimeo_id'] ?? '') === $vimeoId) {
                $entry = $newEntry;
                $replaced = true;
                break;
            }
        }
        unset($entry);

        if (!$replaced) {
            $catalog['videos'][] = $newEntry;
        }

        ftruncate($fp, 0);
        fseek($fp, 0);
        fwrite($fp, json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
