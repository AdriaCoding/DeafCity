<?php

namespace Studio;

class CaptionMigrationPlanner
{
    public function __construct(
        private CaptionFilename $captionFilename = new CaptionFilename(),
    ) {}

    /**
     * @param list<array{vimeo_id?: string, title?: string, captions?: list<array{lang?: string, file?: string}>}> $videos
     * @return list<array{vimeoId: string, lang: string, oldFile: string, newFile: string}>
     */
    public function plan(array $videos): array
    {
        $renames = [];

        foreach ($videos as $video) {
            $title = (string) ($video['title'] ?? '');
            $vimeoId = (string) ($video['vimeo_id'] ?? '');

            foreach ($video['captions'] ?? [] as $caption) {
                $lang = (string) ($caption['lang'] ?? '');
                $oldFile = (string) ($caption['file'] ?? '');
                if ($lang === '' || $oldFile === '') {
                    continue;
                }

                $newFile = $this->captionFilename->forVideo($title, $lang);
                if ($newFile === $oldFile) {
                    continue;
                }

                $renames[] = [
                    'vimeoId' => $vimeoId,
                    'lang' => $lang,
                    'oldFile' => $oldFile,
                    'newFile' => $newFile,
                ];
            }
        }

        return $renames;
    }

    /**
     * @param list<array<string, mixed>> $videos
     * @param list<array{vimeoId: string, lang: string, oldFile: string, newFile: string}> $renames
     * @return list<array<string, mixed>>
     */
    public function applyRenames(array $videos, array $renames): array
    {
        $newFileByKey = [];
        foreach ($renames as $rename) {
            $newFileByKey[$rename['vimeoId'] . "\0" . $rename['oldFile']] = $rename['newFile'];
        }

        foreach ($videos as $videoIndex => $video) {
            $vimeoId = (string) ($video['vimeo_id'] ?? '');
            if (!isset($video['captions']) || !is_array($video['captions'])) {
                continue;
            }

            foreach ($video['captions'] as $captionIndex => $caption) {
                $key = $vimeoId . "\0" . (string) ($caption['file'] ?? '');
                if (isset($newFileByKey[$key])) {
                    $videos[$videoIndex]['captions'][$captionIndex]['file'] = $newFileByKey[$key];
                }
            }
        }

        return $videos;
    }
}
