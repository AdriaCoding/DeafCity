<?php

namespace Studio;

class SrtParser
{
    /**
     * @return array{cues: list<array{start: float, end: float, text: string, opaque: string, id: string}>}
     */
    public function parse(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \InvalidArgumentException('No s\'ha pogut llegir el fitxer SubRip pujat.');
        }

        return $this->parseString($content);
    }

    /**
     * @return array{cues: list<array{start: float, end: float, text: string, opaque: string, id: string}>}
     */
    public function parseString(string $content): array
    {
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new \InvalidArgumentException('El fitxer SubRip ha d\'estar codificat en UTF-8.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $content = trim($content);

        if ($content === '') {
            throw new \InvalidArgumentException('El fitxer SubRip està buit.');
        }

        $blocks = preg_split('/\n\n+/', $content);
        if ($blocks === false || $blocks === []) {
            throw new \InvalidArgumentException('El fitxer SubRip està buit.');
        }

        $cues = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $cues[] = $this->parseBlock($block);
        }

        if ($cues === []) {
            throw new \InvalidArgumentException('El fitxer SubRip està buit.');
        }

        return ['cues' => $cues];
    }

    /**
     * @return array{start: float, end: float, text: string, opaque: string, id: string}
     */
    private function parseBlock(string $block): array
    {
        $lines = explode("\n", $block);

        if (count($lines) < 3) {
            throw new \InvalidArgumentException('Bloc SubRip no vàlid: falta índex, marca de temps o text.');
        }

        $indexLine = trim($lines[0]);
        if (!preg_match('/^\d+$/', $indexLine)) {
            throw new \InvalidArgumentException('Bloc SubRip no vàlid: l\'índex de la línia ha de ser numèric.');
        }

        $timingLine = trim($lines[1]);
        if (!preg_match(
            '/^((?:\d{2}:)?\d{2}:\d{2},\d{3})\s+-->\s+((?:\d{2}:)?\d{2}:\d{2},\d{3})$/',
            $timingLine,
            $m
        )) {
            throw new \InvalidArgumentException('Bloc SubRip no vàlid: la marca de temps no és vàlida.');
        }

        $textLines = array_slice($lines, 2);
        $text = implode("\n", $textLines);
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Bloc SubRip no vàlid: cal almenys una línia de text.');
        }

        return [
            'start' => $this->parseTime($m[1]),
            'end' => $this->parseTime($m[2]),
            'text' => $text,
            'opaque' => '',
            'id' => $indexLine,
        ];
    }

    private function parseTime(string $ts): float
    {
        $ts = str_replace(',', '.', $ts);
        $parts = explode(':', $ts);
        if (count($parts) === 3) {
            [$h, $m, $s] = $parts;
            return (float) $h * 3600 + (float) $m * 60 + (float) $s;
        }
        [$m, $s] = $parts;
        return (float) $m * 60 + (float) $s;
    }
}
