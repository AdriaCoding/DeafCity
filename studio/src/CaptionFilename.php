<?php

namespace Studio;

class CaptionFilename
{
    public function forVideo(string $title, string $lang): string
    {
        return $this->stem($title) . '_' . strtoupper($lang) . '.srt';
    }

    private function stem(string $title): string
    {
        $parts = explode('_', $title);
        $count = count($parts);
        if ($count >= 2 && ctype_digit($parts[$count - 2])) {
            array_pop($parts);
        }

        return implode('_', $parts);
    }
}
