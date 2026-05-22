<?php

namespace Studio;

class VimeoIdParser
{
    public function parse(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidVimeoIdException('Vimeo URL or ID is required.');
        }

        if (preg_match('/^\d+$/', $input)) {
            return $input;
        }

        if (preg_match('#^https?://(?:www\.)?vimeo\.com/(?:manage/videos/)?(\d+)#i', $input, $matches)) {
            return $matches[1];
        }

        throw new InvalidVimeoIdException('Could not extract a Vimeo ID from that input.');
    }
}
