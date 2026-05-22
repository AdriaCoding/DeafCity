<?php

namespace Studio;

class VimeoIdParser
{
    public function parse(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidVimeoIdException('Cal una URL o un ID de Vimeo.');
        }

        if (preg_match('/^\d+$/', $input)) {
            return $input;
        }

        if (preg_match('#^https?://(?:www\.)?vimeo\.com/(?:manage/videos/)?(\d+)#i', $input, $matches)) {
            return $matches[1];
        }

        throw new InvalidVimeoIdException('No s\'ha pogut extreure un ID de Vimeo d\'aquesta entrada.');
    }
}
