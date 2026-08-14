<?php
/**
 * Caption cue helpers for preview playback (PHP 5.6).
 */

if (!function_exists('vpc_caption_target_max_chars')) {
    /** @return int */
    function vpc_caption_target_max_chars() {
        return 60;
    }
}

if (!function_exists('vpc_caption_max_chars_tolerance')) {
    /** @return int */
    function vpc_caption_max_chars_tolerance() {
        return 5;
    }
}

if (!function_exists('vpc_caption_display_max_length')) {
    /** @return int */
    function vpc_caption_display_max_length() {
        return vpc_caption_target_max_chars() + vpc_caption_max_chars_tolerance();
    }
}

if (!function_exists('vpc_caption_text_length')) {
    /**
     * @param string $text
     * @return int
     */
    function vpc_caption_text_length($text) {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text, 'UTF-8');
        }
        return strlen($text);
    }
}

if (!function_exists('vpc_caption_text_slice')) {
    /**
     * @param string $text
     * @param int $start
     * @param int $length
     * @return string
     */
    function vpc_caption_text_slice($text, $start, $length) {
        if (function_exists('mb_substr')) {
            return mb_substr($text, $start, $length, 'UTF-8');
        }
        return substr($text, $start, $length);
    }
}

if (!function_exists('vpc_normalize_caption_display_text')) {
    /**
     * Single-line caption text for preview (full cue; no truncation).
     *
     * @param string $text
     * @return string
     */
    function vpc_normalize_caption_display_text($text) {
        $text = str_replace(array("\r", "\n"), ' ', (string) $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}

if (!function_exists('vpc_caption_timestamp_to_ms')) {
    /**
     * Timestamp to milliseconds, accepting either decimal separator.
     *
     * WebVTT writes 00:00:01.500, SubRip writes 00:00:01,500. PHP's (float)
     * cast stops at the first non-numeric character, so casting an SRT
     * fraction directly silently discards the milliseconds — 01,500 becomes
     * 1.0. Normalise the separator before casting.
     *
     * @param string $ts
     * @return int
     */
    function vpc_caption_timestamp_to_ms($ts) {
        $ts = str_replace(',', '.', trim((string) $ts));
        $parts = explode(':', $ts);

        if (count($parts) === 3) {
            return (int) round((((int) $parts[0]) * 3600 + ((int) $parts[1]) * 60 + (float) $parts[2]) * 1000);
        }
        if (count($parts) === 2) {
            return (int) round((((int) $parts[0]) * 60 + (float) $parts[1]) * 1000);
        }

        return (int) round(((float) $ts) * 1000);
    }
}

if (!function_exists('vpc_parse_caption_cues')) {
    /**
     * Parse WebVTT or SubRip into display cues: array('start','end','text') in ms.
     *
     * Format-agnostic on purpose — data/captions/ holds a mix of both during
     * the VTT to SRT migration, and the player must render either.
     *
     * Neither format needs its non-cue lines handled specially: a WEBVTT
     * header block carries no "-->" so it is skipped as a block, and a
     * SubRip numeric index line sits before the timing line, which the
     * scan below ignores.
     *
     * @param string $content
     * @return array
     */
    function vpc_parse_caption_cues($content) {
        $content = (string) $content;
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $content = str_replace(array("\r\n", "\r"), "\n", $content);

        $cues   = array();
        $blocks = preg_split('/\n[ \t]*\n/', trim($content));

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines    = explode("\n", $block);
            $tsLine   = null;
            $txtLines = array();

            foreach ($lines as $line) {
                $line = trim($line);
                if ($tsLine === null && strpos($line, ' --> ') !== false) {
                    $tsLine = $line;
                } elseif ($tsLine !== null && $line !== '') {
                    $txtLines[] = $line;
                }
            }

            if (!$tsLine || empty($txtLines)) {
                continue;
            }

            /* Comma must be in the class or SubRip timings match nothing at all. */
            if (!preg_match('/^([\d:\.,]+)\s+-->\s+([\d:\.,]+)/', $tsLine, $m)) {
                continue;
            }

            $text = strip_tags(implode(' ', $txtLines));
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = vpc_normalize_caption_display_text($text);

            if ($text === '') {
                continue;
            }

            $cues[] = array(
                'start' => vpc_caption_timestamp_to_ms($m[1]),
                'end'   => vpc_caption_timestamp_to_ms($m[2]),
                'text'  => $text,
            );
        }

        return vpc_align_caption_cues_to_playback($cues);
    }
}

if (!function_exists('vpc_align_caption_cues_to_playback')) {
    /**
     * Map NLE programme timecode onto Vimeo playback time.
     *
     * Premiere / DaVinci sequences often start at 01:00:00,000. The Vimeo
     * Player SDK reports seconds from 0, so a short Video whose Caption file
     * still carries that hour never displays a Subtitle. If every cue sits
     * inside a single hour-long window that itself starts at or after 01:00,
     * drop whole hours from the start of the window.
     *
     * @param array<int, array{start: int, end: int, text: string}> $cues
     * @return array<int, array{start: int, end: int, text: string}>
     */
    function vpc_align_caption_cues_to_playback(array $cues) {
        if (count($cues) === 0) {
            return $cues;
        }

        $minStart = $cues[0]['start'];
        $maxEnd = $cues[0]['end'];
        foreach ($cues as $cue) {
            if ($cue['start'] < $minStart) {
                $minStart = $cue['start'];
            }
            if ($cue['end'] > $maxEnd) {
                $maxEnd = $cue['end'];
            }
        }

        $hourMs = 3600000;
        if ($minStart < $hourMs) {
            return $cues;
        }
        if (($maxEnd - $minStart) >= $hourMs) {
            return $cues;
        }

        $offset = ((int) floor($minStart / $hourMs)) * $hourMs;
        if ($offset <= 0) {
            return $cues;
        }

        foreach ($cues as $i => $cue) {
            $cues[$i]['start'] = $cue['start'] - $offset;
            $cues[$i]['end'] = $cue['end'] - $offset;
        }

        return $cues;
    }
}
