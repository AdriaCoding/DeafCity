<?php

namespace Studio;

class CaptionDeleteHandler
{
    public function __construct(
        private CatalogEditor $catalogEditor,
        private string $captionsDirPath,
        private string $captionTranslationDirPath,
    ) {}

    /**
     * @return array{ok: bool, error?: string, newMaster?: string}
     */
    public function handle(string $vimeoId, string $lang): array
    {
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            return ['ok' => false, 'error' => "Video $vimeoId not found in catalog."];
        }

        $captionFile = null;
        foreach ($video['captions'] ?? [] as $caption) {
            if (($caption['lang'] ?? '') === $lang) {
                $captionFile = $caption['file'] ?? null;
                break;
            }
        }

        if ($captionFile === null) {
            return ['ok' => false, 'error' => "Caption lang '$lang' not found for video $vimeoId."];
        }

        $wasMaster = ($video['master_caption_lang'] ?? '') === $lang;

        try {
            $this->catalogEditor->deleteCaption($vimeoId, $lang);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $path = $this->captionsDirPath . '/' . $captionFile;
        if (is_file($path)) {
            unlink($path);
        }

        // A caption changed, so any prior auto-translate job's premise
        // (its captured master caption) may no longer hold. Drop it rather
        // than risk it silently re-merging stale translations back in.
        (new JobManager($this->captionTranslationDirPath . '/' . $vimeoId))->cancel();

        $result = ['ok' => true];
        if ($wasMaster) {
            $updated = $this->catalogEditor->findVideoByVimeoId($vimeoId);
            $remaining = $updated['captions'] ?? [];
            if ($remaining !== []) {
                $result['newMaster'] = $updated['master_caption_lang'] ?? $remaining[0]['lang'];
            }
        }

        return $result;
    }

    /**
     * Deletes every caption except the Master subtitle, one at a time, so a
     * Producer can clear a video's translations without deleting them by hand.
     *
     * @return array{ok: bool, error?: string, deletedLangs?: list<string>}
     */
    public function handleAllNonMaster(string $vimeoId): array
    {
        $video = $this->catalogEditor->findVideoByVimeoId($vimeoId);
        if ($video === null) {
            return ['ok' => false, 'error' => "Video $vimeoId not found in catalog."];
        }

        $masterLang = $video['master_caption_lang'] ?? '';
        $langsToDelete = [];
        foreach ($video['captions'] ?? [] as $caption) {
            $lang = $caption['lang'] ?? '';
            if ($lang !== '' && $lang !== $masterLang) {
                $langsToDelete[] = $lang;
            }
        }

        $deleted = [];
        foreach ($langsToDelete as $lang) {
            $result = $this->handle($vimeoId, $lang);
            if (!$result['ok']) {
                return $result + ['deletedLangs' => $deleted];
            }
            $deleted[] = $lang;
        }

        return ['ok' => true, 'deletedLangs' => $deleted];
    }
}
