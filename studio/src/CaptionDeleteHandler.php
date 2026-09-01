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

        $path = self::resolveSafeCaptionPath($this->captionsDirPath, $captionFile);
        if ($path !== false) {
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

    /**
     * Resolves a catalog-supplied caption `file` value to a real path
     * strictly inside $captionsDir, or false if it isn't one. Mirrors
     * Studio\Actions\CatalogAction::resolveSafeCaptionPath() — same
     * caption-path trust boundary, applied here before every unlink().
     *
     * basename() strips any directory component (defeats
     * `../../config/config.php`, absolute paths, and encoded-looking
     * values like `..%2f..%2f...`), and the realpath containment check is
     * the actual guarantee that the resolved file is truly inside the
     * captions directory.
     */
    private static function resolveSafeCaptionPath(string $captionsDir, ?string $file): string|false
    {
        if ($file === null || trim($file) === '') {
            return false;
        }

        $safeName = basename($file);
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            return false;
        }

        $realDir = realpath($captionsDir);
        if ($realDir === false) {
            return false;
        }

        $realCandidate = realpath($realDir . DIRECTORY_SEPARATOR . $safeName);
        if ($realCandidate === false) {
            return false;
        }

        if (!str_starts_with($realCandidate, $realDir . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return $realCandidate;
    }
}
